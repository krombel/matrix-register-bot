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
    if (!isset($_GET["t"])) {
        throw new Exception("UNKNOWN_TOKEN");
    }
    $token = filter_var($_GET["t"], FILTER_SANITIZE_STRING);

    require_once(__DIR__ . "/../database.php");

    $user = $mx_db->getUserForVerify($token);
    if ($user == NULL) {
        throw new Exception("UNKNOWN_TOKEN");
    }
    $first_name = $user["first_name"];
    $last_name = $user["last_name"];
    $username = $user["username"];
    $note = $user["note"];
    $email = $user["email"];
    $admin_token = $user["admin_token"];

    require_once(__DIR__ . "/../MatrixConnection.php");
    $adminUrl = $config["webroot"] . "/verify_admin.php?t=" . $admin_token;
    $mxConn = new MatrixConnection($config["homeserver"], $config["access_token"]);
    $mxMsg = new MatrixMessage();
    $mxMsg->set_body(strtr($language["MSG_USER_WANTS_REGISTER"], [
        "@name" => (strlen($first_name . $last_name) > 0 ? $first_name . " " . $last_name : $username),
        "@note" => $note,
        "@adminUrl" => $adminUrl
    ]));
    if (isset($language["MSG_USER_WANTS_REGISTER_FORMATTED"])) {
        $mxMsg->set_formatted_body(strtr($language["MSG_USER_WANTS_REGISTER_FORMATTED"], [
            "@name" => (strlen($first_name . $last_name) > 0 ? $first_name . " " . $last_name : $username),
            "@note" => $note,
            "@adminUrl" => $adminUrl
        ]));
    }
    $mxMsg->set_type("m.text");
    $response = $mxConn->send($config["register_room"], $mxMsg);

    if ($response) {
        $message = $language["SEND_MATRIX_FAIL"];
    }
    $mx_db->setRegistrationStateVerify(
            ($response ? RegisterState::PendingAdminVerify : RegisterState::PendingAdminSend), $token);

    send_mail_pending_approval($config["homeserver"], $first_name . " " . $last_name, $email);

    print("<title>" . $language["VERIFICATION_SUCEEDED"] . "</title>");
    print("</head><body>");
    print("<h1>" . $language["VERIFICATION_SUCEEDED"] . "</h1>");
    print("<p>" . $language["VERIFICATION_SUCCESS_BODY"] . "</p>");
    print("<a href=\"" . $config["webroot"] . "/index.php" . "\">" . $language["JUMP_TO_HOMEPAGE"] . "</a>");
} catch (Exception $e) {
    print("<title>" . $language["VERIFICATION_FAILED"] . "</title>");
    print("</head><body>");
    print("<h1>" . $language["VERIFICATION_FAILED"] . "</h1>");
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
