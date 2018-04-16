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
require_once "../language.php";
if (!file_exists("../config.php")) {
    print($language["NO_CONFIGURATION"]);
    exit();
}
require_once "../config.php";
require_once "../mail_templates.php";

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
    if (!isset($_GET["t"])) {
        throw new Exception("UNKNOWN_TOKEN");
    }
    $token = filter_var($_GET["t"], FILTER_SANITIZE_STRING);

    require_once("../database.php");

    $action = NULL;
    if (isset($_GET["allow"])) {
        $action = RegisterState::RegistrationAccepted;
    }
    $decline_reason = NULL;
    if (isset($_GET["deny"])) {
        $action = RegisterState::RegistrationDeclined;
        if (isset($_GET["reason"])) {
            $decline_reason = filter_var($_GET["reason"], FILTER_SANITIZE_STRING);
        }
    }

    $user = $mx_db->getUserForApproval($token);
    if ($user == NULL) {
        throw new Exception("UNKNOWN_TOKEN");
    }

    $first_name = $user["first_name"];
    $last_name = $user["last_name"];
    $username = $user["username"];
    $note = $user["note"];
    $email = $user["email"];

    if ($action == RegisterState::RegistrationAccepted) {
        $mx_db->setRegistrationStateAdmin(RegisterState::PendingRegistration, $token);

        // register user
        require_once("../MatrixConnection.php");
        $mxConn = new MatrixConnection($config["homeserver"], $config["access_token"]);

        // generate a password with 8 characters
        $password = $mx_db->addUser($first_name, $last_name, $username, $email);
        if ($password != NULL) {
            // send registration_success
            $res = send_mail_registration_success($config["homeserver"], $first_name . " " . $last_name, $email, $username, $password, $config["howToURL"]);
            if ($res) {
                $mx_db->setRegistrationStateAdmin(RegisterState::AllDone, $token);
            } else {
                $mx_db->setRegistrationStateAdmin(RegisterState::PendingSendRegistrationMail, $token);
            }
        } else {
            send_mail_registration_allowed_but_failed($config["homeserver"], $first_name . " " . $last_name, $email);
            $mxMsg = new MatrixMessage();
            $mxMsg->set_type("m.text");
            $mxMsg->set_body(strtr($language["REGISTRATION_FAILED_FOR"], [ "@name" => $first_name . " " . $last_name]));
            $mxConn->send($config["register_room"], $mxMsg);
            throw new Exception("REGISTRATION_FAILED");
        }

        print("<title>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["ADMIN_VERIFY_SITE_TITLE"] . "</h1>");
        print("<p>" . $language["ADMIN_REGISTER_ACCEPTED_BODY"] . "</p>");
    } elseif ($action == RegisterState::RegistrationDeclined) {
        $mx_db->setRegistrationStateAdmin(RegisterState::RegistrationDeclined, $token);
        send_mail_registration_decline($config["homeserver"], $first_name . " " . $last_name, $email, $decline_reason);
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
                            <form name="appForm" role="form" action="verify_admin.php" method="GET">
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

                                <div class="form-group">
                                    <input type="text" id="note" class="form-control input-sm" value="<?php echo $note; ?>" disabled=true>
                                </div>

                                <div class="form-group">
                                    <input type="text" id="username" class="form-control input-sm"
                                           value="<?php echo $username; ?>" disabled=true>
                                </div>
                                <input type="hidden" name="t" id="token" value="<?php echo $token; ?>">
                                <input type="submit" name="allow" value="<?php echo $language["ACCEPT"]; ?>" class="btn btn-info btn-block">
                                <input type="submit" name="deny" value="<?php echo $language["DECLINE"]; ?>" class="btn btn-info btn-block">

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">

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
                    < /body>
         </html>
