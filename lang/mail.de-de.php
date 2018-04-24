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
function send_mail($receiver, $subject, $body) {
    include(__DIR__ . "/../config.php");
    $headers = "From: " . $config["register_email"] . "\r\n"
            . "Content-Type: text/plain;charset=utf-8";
    return mail($receiver, $subject, $body, $headers);
}

function send_mail_pending_verification($homeserver, $user, $receiver, $verify_url) {
    $subject = "Bitte bestätige Registrierung auf $homeserver";
    $body = "Guten Tag " . $user . ",

Du hast anscheinend versucht dich auf $homeserver zu registrieren.
Hier gibt es eine zweistufige Registrierung.

Wir möchten dich bitten, dass du kurz bestätigst, dass du die Registrierung durchgeführt hast.
Gehe dafür auf folgenden Link:
$verify_url

Erst anschließend werden die Administratoren über deine Registrierungsanfrage informiert.

Hinweis: Du hast ca. 48 Stunden Zeit um die Bestätigung durchzuführen.
Danach ist eine Re-Registrierung mit deinem gewünschten Nutzernamen für andere wieder möglich.

Vielen Dank für dein Verständnis.

Das Administratoren-Team von " . $homeserver;
    return send_mail($receiver, $subject, $body);
}

function send_mail_pending_approval($homeserver, $user, $receiver) {
    $subject = "Registrierung wartet auf Bestätigung durch Administratoren";
    $body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde verifiziert und wird nun durch die Administratoren überprüft.

Du bekommst eine weitere E-Mail, sobald deine Registrierung bestätigt oder ablehnt wurde.

Vielen Dank für dein Verständnis.

Das Administratoren-Team von " . $homeserver;
    return send_mail($receiver, $subject, $body);
}

function send_mail_registration_allowed_but_failed($homeserver, $user, $receiver) {
    $subject = "Registrierung auf $homeserver genehmigt";
    $body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren bestätigt.

Leider ist beim Registrieren ein Fehler aufgetaucht. Der Registrierungversuch wird bald wiederholt.
Wir hoffen, das Problem ist bald behoben.
Wir melden uns, wenn die Registrierung erfolgreich war.

Das Administratoren-Team von " . $homeserver;
    return send_mail($receiver, $subject, $body);
}

function send_mail_registration_success($homeserver, $user, $receiver, $username, $password, $howToURL) {
    $subject = "Registrierung auf $homeserver erfolgreich";
    $body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren bestätigt.

Zum Anmelden kannst du folgende Zugangsdaten verwenden:
Nutzername: $username
Passwort: $password

Hinweis: Das Passwort kannst du aktuell über die App selbst ändern. Auch wenn das Passwort nirgends
im Klartext gespeichert wird, kann jemand Zugriff auf diese Mail erlangen und so den Zugriff bekommen.
";
    /*
      Wichtig: Bitte ändere das Passwort direkt nach der Anmeldung.
      Es wird zwar von unserer Seite nicht gespeichert, doch fremde könnten Zugriff auf diese E-Mail
      erhalten und so deinen Account kompromittieren.
     */
    if (!empty($howToURL)) {
        $body .= "
Zu weiteren Hilfestellungen findest du hier eine Auflistung von verschiedenen
Anleitungen zu verschiedenen Clients:
$howToURL\n";
    }
    $body .= "
Viel Spaß bei der Verwendung von $homeserver.
Bei Fragen findest du nach der Anmeldung ein paar Räume in denen du sie stellen kannst.

Das Administratoren-Team von " . $homeserver;
    return send_mail($receiver, $subject, $body);
}

function send_mail_registration_decline($homeserver, $user, $receiver, $reason) {
    $subject = "Registrierung auf $homeserver abgelehnt";
    $body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren abgelehnt.\n";

    if (empty($reason)) {
        $body .= "\nEs wurde kein Grund angegeben\n";
    } else {
        $body .= "\nAls Grund wurde folgendes angegeben:\n$reason\n";
    }

    $body .= "
Wir hoffen, dass du dies akzeptieren kannst.

Das Administratoren-Team von " . $homeserver;
    return send_mail($receiver, $subject, $body);
}

?>
