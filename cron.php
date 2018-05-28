<?php

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
require_once(__DIR__ . "/config.php");
require_once(__DIR__ . "/language.php");
require_once(__DIR__ . "/mail_templates.php");
require_once(__DIR__ . "/database.php");

$sql = "SELECT id, first_name, last_name, username, password, email,"
        . " state, note, verify_token, admin_token "
        . "FROM registrations "
        . "WHERE state = " . RegisterState::PendingEmailSend
        . " OR state = " . RegisterState::PendingAdminSend
        . " OR state = " . RegisterState::PendingRegistration
        . " OR state = " . RegisterState::PendingSendRegistrationMail . ";";
foreach ($mx_db->query($sql) as $row) {
    $first_name = $row["first_name"];
    $last_name = $row["last_name"];
    $username = $row["username"];
    $email = $row["email"];
    $state = $row["state"];

    try {
        switch ($state) {
            case RegisterState::PendingEmailSend:
                $verify_url = $config["webroot"] . "/verify.php?t=" . $row["verify_token"];
                $success = send_mail_pending_verification(
                        $config["homeserver"], $row["first_name"] . " " . $row["last_name"], $row["email"], $verify_url);

                if ($success) {
                    $mx_db->setRegistrationStateById(RegisterState::PendingEmailVerify, $row["id"]);
                } else {
                    throw new Exception("Could not send mail to " . $row["first_name"] . " " . $row["last_name"] . "(" . $row["id"] . ")");
                }
                break;
            case RegisterState::PendingAdminSend:
                require_once(__DIR__ . "/MatrixConnection.php");
                $adminUrl = $config["webroot"] . "/verify_admin.php?t=" . $row["admin_token"];
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
                    $mx_db->setRegistrationStateById(RegisterState::PendingAdminVerify, $row["id"]);

                    send_mail_pending_approval($config["homeserver"], $first_name . " " . $last_name, $email);
                } else {
                    throw new Exception("Could not send notification for " . $row["first_name"] . " " . $row["last_name"] . "(" . $row["id"] . ") to admins.");
                }
                break;
            case RegisterState::PendingRegistration:
                // Registration got accepted but registration failed
                switch ($config["operationMode"]) {
                    case "synapse":
                        // register with registration_shared_secret
                        // generate a password with 10 characters
                        $password = bin2hex(openssl_random_pseudo_bytes(5));
                        $res = $mxConn->register($row["username"], $password, $config["registration_shared_secret"]);
                        if (!$res) {
                            // something went wrong while registering
                            $password = NULL;
                        }
                        break;
                    case "local":
                        // register by adding a user to the local database
                        $password = $mx_db->addUser($row["first_name"], $row["last_name"], $row["username"], $row["password"], $row["email"]);
                        break;
                    default:
                        throw new Exception("Unknown operationMode");
                }
                if ($password != NULL) {
                    // send registration_success
                    $res = send_mail_registration_success(
                            $config["homeserver"], strlen($first_name . $last_name) > 0 ? $first_name . " " . $last_name : $username, $email, $username, $password, $config["howToURL"]
                    );
                    if ($res) {
                        $mx_db->setRegistrationStateById(RegisterState::AllDone, $row["id"]);
                    } else {
                        $mx_db->setRegistrationStateById(RegisterState::PendingSendRegistrationMail, $row["id"]);
                    }
                } else {
                    send_mail_registration_allowed_but_failed($config["homeserver"], $first_name . " " . $last_name, $email);
                    $mxMsg = new MatrixMessage();
                    $mxMsg->set_type("m.text");
                    $mxMsg->set_body(strtr($language["REGISTRATION_FAILED_FOR"], [
                        "@name" => strlen($first_name . $last_name) > 0 ? $first_name . " " . $last_name : $username,
                    ]));
                    $mxConn->send($config["register_room"], $mxMsg);
                    throw new Exception($language["REGISTRATION_FAILED"]);
                }
                break;
            case RegisterState::PendingSendRegistrationMail:
                print ("Error: Unhandled state: PendingSendRegistrationMail for " . $first_name . " " . $last_name . " (" . $username . ")\n");
                break;
        }
    } catch (Exception $e) {
        print("Error while handling cron for " . $first_name . " " . $last_name . " (" . $username . ")\n");
        print($e->getMessage());
    }
}

try {
    //cleanup: all finished entries older than one month
    $timestamp = date('Y-m-d H:m:s', strtotime("-1 month"));
    $mx_db->query("DELETE FROM registrations "
            . "WHERE request_date < '$timestamp'"
            . " AND (state = " . RegisterState::RegistrationDeclined
            . " OR state = " . RegisterState::AllDone . " );"
    );
    //cleanup: all entries which are pending email registration older than two days
    $timestamp = date('Y-m-d H:m:s', strtotime("-2 days"));
    $mx_db->query("DELETE FROM registrations "
            . "WHERE request_date < '$timestamp'"
            . " AND state = " . RegisterState::PendingEmailVerify . ";"
    );
} catch (Exception $e) {
    print("Error while database cleanup\n");
    print($e->getMessage());
}
?>
