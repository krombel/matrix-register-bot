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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once(__DIR__ . "/vendor/autoload.php");

// standard mail implementation
function send_mail($receiver, $subject, $body) {
    // somehow $config is not available when called again => reinit here
    include(__DIR__ . "/config.php");

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'utf-8';                             // Enable utf-8 support for umlauts

        if (is_array($config["smtp"])) {
            $smtp_conf = $config["smtp"];
            $mail->isSMTP();                                  // Set mailer to use SMTP
            $mail->Host = $smtp_conf["host"];                 // Specify main and backup SMTP servers
            $mail->Port = $smtp_conf["port"];                 // TCP port to connect to
            if (!empty($smtp_conf["user"])) {
                $mail->SMTPAuth = true;                       // Enable SMTP authentication
                $mail->Username = $smtp_conf["user"];         // SMTP username
                if (!empty($smtp_conf["password"])) {
                    $mail->Password = $smtp_conf["password"]; // SMTP password
                }
            }
            if (!empty($smtp_conf["encryption"])) {
                $mail->SMTPSecure = $smtp_conf["encryption"]; // Enable TLS encryption, `ssl` also accepted
            }
        } else {
            // fallback to sendmail functionality (as before)
            $mail->isSendmail();
        }

        //Recipients
        $mail->setFrom($config["register_email"], 'Register Service');
        $mail->addAddress($receiver);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return True;
    } catch (Exception $e) {
        error_log('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
        return False;
    }
}

$lang = $config["defaultLanguage"];
if (isset($_GET['lang'])) {
    $lang = filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
}
$lang_file = __DIR__ . "/lang/mail." . $lang . ".php";
if (!file_exists($lang_file)) {
    error_log("Mail templates for '" . $lang . "' not found. Fallback to 'de-de'");
    $lang = "de-de";
}
$lang_file = __DIR__ . "/lang/mail." . $lang . ".php";
require_once($lang_file);
unset($lang_file);
?>
