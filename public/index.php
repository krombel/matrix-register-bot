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
// enforce admin via https
if (!isset($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit();
}

require_once(__DIR__ . "/../language.php");
if (!file_exists("../config.php")) {
    print($language["NO_CONFIGURATION"]);
    exit();
}
require_once(__DIR__ . "/../config.php");

// this values will not be used when using the register operation type
$storeFirstLastName = false;
if (isset($config["operationMode"]) && $config["operationMode"] === "local") {
    $storeFirstLastName = true;
}

// currently the case to store the password on our own is the only supported one
$storePassword = false;
if (isset($config["getPasswordOnRegistration"]) && $config["getPasswordOnRegistration"] &&
        isset($config["operationMode"]) && $config["operationMode"] === "synapse") {
    $storePassword = true;
}
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!isset($_SESSION["token"]) || !isset($_POST["token"]) || $_SESSION["token"] != $_POST["token"]) {
            // token not present or invalid
            throw new Exception("UNKNOWN_SESSION");
        }
        if (!isset($_POST["username"])) {
            throw new Exception("UNKNOWN_USERNAME");
        }
        if (strlen($_POST["username"]) > 20 ||
                strlen($_POST["username"]) < 3 ||
                !ctype_lower($_POST["username"])) {
            throw new Exception("USERNAME_INVALID");
        }
        if (ctype_alnum($_POST['username']) != true) {
            throw new Exception("USERNAME_NOT_ALNUM");
        }
        if (isset($config["getPasswordOnRegistration"]) && $config["getPasswordOnRegistration"] &&
                $_POST["password"] != $_POST["password_confirm"]) {
            throw new Exception("PASSWORD_NOT_MATCH");
        }
        if (isset($_POST["note"]) && strlen($_POST["note"]) > 50) {
            throw new Exception("NOTE_LENGTH_EXEEDED");
        }
        if (!isset($_POST["email"]) || !filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("EMAIL_INVALID_FORMAT");
        }
        if ($storeFirstLastName) {
            // only require first_name and last_name when we will evaluate them
            if (!isset($_POST["first_name"]) || !preg_match("/[A-Z][a-z]+/", $_POST["first_name"])) {
                throw new Exception("FIRSTNAME_INVALID_FORMAT");
            }
            if (!isset($_POST["last_name"]) || !preg_match("/[A-Z][a-z]+/", $_POST["last_name"])) {
                throw new Exception("SIRNAME_INVALID_FORMAT");
            }
            $first_name = filter_var($_POST["first_name"], FILTER_SANITIZE_STRING);
            $last_name = filter_var($_POST["last_name"], FILTER_SANITIZE_STRING);
        } else {
            $first_name = $last_name = "";
        }

        $username = filter_var($_POST["username"], FILTER_SANITIZE_STRING);
        if ($storePassword && isset($_POST["password"])) {
            $password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
        }
        $note = filter_var($_POST["note"], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST["email"], FILTER_VALIDATE_EMAIL);

        require_once(__DIR__ . "/../database.php");
        $res = $mx_db->addRegistration($first_name, $last_name, $username, $note, $email);

        if (!isset($res["verify_token"])) {
            error_log("sth. went wrong. registration did not throw but admin_token not set");
            throw Exception("UNKNOWN_ERROR");
        }
        $verify_token = $res["verify_token"];

        $verify_url = $config["webroot"] . "/verify.php?t=" . $verify_token;
        require_once(__DIR__ . "/../mail_templates.php");
        $success = send_mail_pending_verification(
                $config["homeserver"], $storeFirstLastName ? $first_name . " " . $last_name : $username, $email, $verify_url);

        $mx_db->setRegistrationStateVerify(
                ($success ? RegisterState::PendingEmailVerify : RegisterState::PendingEmailSend), $verify_token);

        print("<title>" . $language["SUCCESS"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["SUCCESS"] . "</h1>");
        print("<p>" . $language["TASK_CHECK_YOUR_EMAIL_VERIFY"] . "</p>");
        print("<a href=\"" . $config["webroot"] . "/index.php" . "\">" . $language["JUMP_TO_HOMEPAGE"] . "</a>");
    } catch (Exception $e) {
        print("<title>" . $language["REGISTRATION_REQUEST_FAILED"] . "</title>");
        print("</head><body>");
        print("<h1>" . $language["REGISTRATION_REQUEST_FAILED"] . "</h1>");
        if (isset($language[$e->getMessage()])) {
            print("<p>" . $language[$e->getMessage()] . "</p>");
        } else {
            print("<p>" . $e->getMessage() . "</p>");
        }
        print("<a href=\"" . $config["webroot"] . "/index.php" . "\">" . $language["JUMP_TO_HOMEPAGE"] . "</a>");
    }
} else {
    $_SESSION["token"] = bin2hex(random_bytes(16));
    ?>
    <title><?php echo strtr($language["TOPIC_PLEASE_REGISTER"], ["@homeserver" => $config["homeserver"]]); ?></title>
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
                        <h3 class="panel-title"><?php
                            echo strtr($language["TOPIC_PLEASE_REGISTER"], ["@homeserver" => $config["homeserver"]])
                            . "<small>" . $language["TOPIC_PLEASE_REGISTER_NOTE"] . "</small>";
                            ?></h3>
                    </div>
                    <div class="panel-body">
                        <form name="regForm" role="form" action="index.php" method="post">
                            <?php if ($storeFirstLastName) { ?>
                                <div class="row">
                                    <div class="col-xs-6 col-sm-6 col-md-6">
                                        <div class="form-group">
                                            <input type="text" name="first_name" id="first_name" class="form-control input-sm"
                                                   placeholder="<?php echo $language["FIRST_NAME"]; ?>" pattern="[A-Z][a-z]+">
                                        </div>
                                    </div>
                                    <div class="col-xs-6 col-sm-6 col-md-6">
                                        <div class="form-group">
                                            <input type="text" name="last_name" id="last_name" class="form-control input-sm"
                                                   placeholder="<?php echo $language["LAST_NAME"]; ?>" pattern="[A-Z][a-z]+">
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="form-group">
                                <input type="email" name="email" id="email" class="form-control input-sm" placeholder="<?php echo $language["EMAIL_ADDRESS"]; ?>" required>
                            </div>

                            <div class="form-group">
                                <input type="text" name="note" id="note" class="form-control input-sm" placeholder="<?php echo $language["PLACEHOLDER_NOTE_ABOUT_YOURSELF"]; ?>">
                            </div>

                            <div class="form-group">
                                <input type="text" name="username" id="username" class="form-control input-sm"
                                       placeholder="<?php echo $language["USERNAME"]; ?>" pattern="[a-z1-9]{3,20}" required>
                            </div>
                            <?php if ($storePassword) { ?>
                                <div class="row">
                                    <div class="col-xs-6 col-sm-6 col-md-6">
                                        <div class="form-group">
                                            <input type="password" name="password" id="password" class="form-control input-sm" placeholder="<?php echo $language["PASSWORD"]; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-xs-6 col-sm-6 col-md-6">
                                        <div class="form-group">
                                            <input type="password" name="password_confirm" id="password_confirm" class="form-control input-sm" placeholder="<?php echo $language["PASSWORD_CONFIRM"]; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <input type="hidden" name="token" id="token" value="<?php echo $_SESSION["token"]; ?>">
                            <input type="submit" value="<?php echo $language["REGISTER"]; ?>" class="btn btn-info btn-block">

                        </form>
                        <?php
                        if (isset($language["NOTE_FOR_REGISTRATION"])) {
                            echo "<p>" . $language["NOTE"] . ": <br />";
                            echo strtr($language["NOTE_FOR_REGISTRATION"], ["@homeserver" => $config["homeserver"]]);
                            echo "</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var user_name = document.getElementById("username");
        user_name.oninvalid = function (event) {
            event.target.setCustomValidity("<?php echo $language["USERNAME_INVALID"]; ?>");
        }
        user_name.onkeyup = function (event) {
            event.target.setCustomValidity("");
        }
<?php if ($storeFirstLastName) { ?>
            var first_name = document.getElementById("first_name");
            first_name.oninvalid = function (event) {
                event.target.setCustomValidity("<?php echo $language["FIRSTNAME_INVALID_FORMAT"]; ?>");
            }
            first_name.onkeyup = function (event) {
                event.target.setCustomValidity("");
            }
            var last_name = document.getElementById("last_name");
            last_name.oninvalid = function (event) {
                event.target.setCustomValidity("<?php echo $language["SIRNAME_INVALID_FORMAT"]; ?>");
            }
            last_name.onkeyup = function (event) {
                event.target.setCustomValidity("");
            }
<?php } if ($storePassword) { ?>
            var password = document.getElementById("password")
                    , confirm_password = document.getElementById("password_confirm");
            function validatePassword() {
                if (password.value != confirm_password.value) {
                    confirm_password.setCustomValidity("<?php echo $language["PASSWORD_NOT_MATCH"]; ?>");
                } else {
                    confirm_password.setCustomValidity('');
                }
            }
            password.onchange = validatePassword;
            confirm_password.onkeyup = validatePassword;
<?php } ?>
    </script>
<?php } ?>
</body></html>
