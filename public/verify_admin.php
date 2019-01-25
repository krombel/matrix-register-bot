<html><head><?php
/**
 * Copyright 2018 Matthias Kesler
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
require_once(__DIR__ . "/../language.php");
if (!file_exists("../config.php")) {
    print($language["NO_CONFIGURATION"]);
    exit();
}
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../mail_templates.php");

// enforce admin via https
if (!isset($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit();
}

session_start();

try {
    if ($_SERVER["REQUEST_METHOD"] != "GET") {
        throw new Exception("Method not allowed");
    }
    $token = filter_input(INPUT_GET, "t", FILTER_SANITIZE_STRING);
    if (empty($token)) {
        throw new Exception("UNKNOWN_TOKEN");
    }

    require_once(__DIR__ . "/../database.php");

    $param_action = filter_input(INPUT_GET, "d", FILTER_SANITIZE_STRING);
    if ($param_action == "allow") {
        $action = RegisterState::RegistrationAccepted;
    } elseif ($param_action == "deny") {
        $action = RegisterState::RegistrationDeclined;
        $decline_reason = filter_input(INPUT_GET, "decline_reason", FILTER_SANITIZE_STRING);
    }

    $user = $mx_db->getUserForApproval($token);
    if ($user == NULL) {
        throw new Exception("UNKNOWN_TOKEN");
    }

    $first_name = $user["first_name"];
    $last_name = $user["last_name"];
    $username = $user["username"];
    // we have 2 cases: first and last name or just the username
    $call_name = strlen($first_name . $last_name) > 0 ? $first_name . " " . $last_name : $username;

    $note = $user["note"];
    $email = $user["email"];

    if ($action == RegisterState::RegistrationAccepted) {
        $mx_db->setRegistrationStateAdmin(RegisterState::PendingRegistration, $token);

        // register user
        require_once(__DIR__ . "/../MatrixConnection.php");
        $mxConn = new MatrixConnection($config["homeserver"], $config["access_token"]);

        $password = NULL;
        $use_db_password = (isset($config["getPasswordOnRegistration"]) && $config["getPasswordOnRegistration"]);
        if ($use_db_password && isset($user["password"]) && strlen($user["password"]) > 0) {
            $password = $user["password"];
        } else {
            $use_db_password = false;
            // generate a password with 10 characters
            $password = bin2hex(openssl_random_pseudo_bytes(5));
        }
        switch ($config["operationMode"]) {
            case "synapse":
                // register with registration_shared_secret
                $res = $mxConn->register($username, $password, $config["registration_shared_secret"]);
                if (!$res) {
                    // something went wrong while registering
                    $password = NULL;
                }
                break;
            case "local":
                // register by adding a user to the local database
                $password = $mx_db->addUser($first_name, $last_name, $username, $password, $email);
                break;
            default:
                throw new Exception("Unknown operationMode");
        }
        if ($password != NULL) {
            // send registration_success
            $res = send_mail_registration_success(
                    $config["homeserver"],
                    $call_name,
                    $email,
                    $username,
                    // only send password when auto-created
                    ($use_db_password ? NULL : $password),
                    $config["howToURL"]
            );
            if ($res) {
                $mx_db->setRegistrationStateAdmin(RegisterState::AllDone, $token);
            } else {
                $mx_db->setRegistrationStateAdmin(RegisterState::PendingSendRegistrationMail, $token);
            }
        } else {
            send_mail_registration_allowed_but_failed($config["homeserver"], $call_name, $email);
            $mxMsg = new MatrixMessage();
            $mxMsg->set_type("m.text");
            $mxMsg->set_body(strtr($language["REGISTRATION_FAILED_FOR"], [
                "@name" => $call_name,
            ]));
            $mxConn->send($config["register_room"], $mxMsg);
            throw new Exception("REGISTRATION_FAILED");
        }

        print("<title>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</h1>");
        print("<p>" . $language["ADMIN_REGISTER_ACCEPTED_BODY"] . "</p>");
    } elseif ($action == RegisterState::RegistrationDeclined) {
        $mx_db->setRegistrationStateAdmin(RegisterState::RegistrationDeclined, $token);
        send_mail_registration_decline(
                $config["homeserver"], $call_name, $email, $decline_reason
        );
        print("<title>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</h1>");
        print("<p>" . $language["ADMIN_REGISTER_DECLINED_BODY"] . "</p>");
    } else {
        print("<title>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</title>");
        ?>
        <link href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body{
                background-color: #525252;
            }
            .centered-form{
                margin-top: 60px;
            }

            .centered-form .panel{
                background: rgba(255, 255, 255, 0.8);
                box-shadow: rgba(0, 0, 0, 0.3) 20px 20px 20px;
            }
        </style>
        <script type="text/javascript" src="//code.jquery.com/jquery-1.11.1.min.js"></script>
        <script type="text/javascript" src="//netdna.bootstrapcdn.com/bootstrap/3.1.0/js/bootstrap.min.js"></script>
    </head>
    <body>
        <div class="container">
            <div class="row centered-form">
                <div class="col-xs-12 col-sm-8 col-md-4 col-sm-offset-2 col-md-offset-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><?php echo $language["ADMIN_VERIFY_SITE_TITLE"]; ?></h3>
                        </div>
                        <div class="panel-body">
                            <form name="appForm" role="form" onsubmit="return submitForm()" action="verify_admin.php" method="GET">
                                <?php
                                if (isset($config["operationMode"]) && $config["operationMode"] === "local") {
                                    // this values will not be used when using the register operation type
                                    ?>
                                    <div class="row">
                                        <div class="col-xs-6 col-sm-6 col-md-6">
                                            <div class="form-group">
                                                <input type="text" id="first_name" class="form-control input-sm"
                                                       value="<?php echo $first_name; ?>" disabled=true>
                                            </div>
                                        </div>
                                        <div class="col-xs-6 col-sm-6 col-md-6">
                                            <div class="form-group">
                                                <input type="text" id="last_name" class="form-control input-sm"
                                                       value="<?php echo $last_name; ?>" disabled=true>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="form-group">
                                    <input type="text" id="note" class="form-control input-sm" value="<?php echo $note; ?>" disabled=true>
                                </div>

                                <div class="form-group">
                                    <input type="text" id="username" class="form-control input-sm"
                                           value="<?php echo $username; ?>" disabled=true>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="decline_reason" class="form-control input-sm"
                                           placeholder="<?php echo $language["DECLINE_REASON"]; ?>">
                                </div>
                                <input type="hidden" name="t" id="token" value="<?php echo $token; ?>">
                                <div class="form-group">
                                    <input type="radio" name="d" value="allow"><?php echo $language["ACCEPT"]; ?>
                                    <input type="radio" name="d" value="deny"><?php echo $language["DECLINE"]; ?>
                                </div>
                                <input type="submit" value="<?php echo $language["SUBMIT"]; ?>" class="btn btn-info btn-block">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            var rad = document.appForm.d;
            function isSelected() {
                for (var i=0; i<rad.length; i++)
                    if (rad[i].checked)
                        return true;
                return false;
            }
            function submitForm() {
                if (isSelected()) {
                    return true;
                }
                alert("<?php echo $language["MAKE_A_SELECTION"];?>");
                return false;
            }
            for(var i = 0; i < rad.length; i++) {
                rad[i].onclick = function() {
                    if (this.value === "deny") {
                        document.appForm.decline_reason.type = "text";
                    } else {
                        document.appForm.decline_reason.type = "hidden";
                    }
                };
            }
        </script>
            <?php
        } // else - no action provided
    } catch (Exception $e) {
        print("<title>" . $language["REGISTRATION_FAILED"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["REGISTRATION_FAILED"] . "</h1>");
        if (isset($language[$e->getMessage()])) {
            print("<p>" . $language[$e->getMessage()] . "</p>");
        } else {
            print("<p>" . $e->getMessage() . "</p>");
        }
        print("<a href=\"" . $config["webroot"] . "/index.php" . "\">" . $language["JUMP_TO_HOMEPAGE"] . "</a>");
    }
    ?>
    </body>
</html>
