<?php

function send_mail($receiver, $subject, $body) {
	include("config.php");
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
	return send_mail($receiver, $subject, $body );
}

function send_mail_pending_approval($homeserver, $user, $receiver) {
	$subject = "Registrierung wartet auf Bestätigung durch Administratoren";
	$body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde verifiziert und wird nun durch die Administratoren überprüft.

Du bekommst eine weitere E-Mail, sobald deine Registrierung bestätigt oder ablehnt wurde.

Vielen Dank für dein Verständnis.

Das Administratoren-Team von " . $homeserver;
	return send_mail($receiver, $subject, $body );
}

function send_mail_registration_allowed_but_failed($homeserver, $user, $receiver) {
	$subject = "Registrierung auf $homeserver genehmigt.";
	$body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren bestätigt.

Leider ist beim Registrieren ein Fehler aufgetaucht. Der Registrierungversuch wird bald wiederholt.
Wir hoffen, das Problem ist bald behoben.
Wir melden uns, wenn die Registrierung erfolgreich war.

Das Administratoren-Team von " . $homeserver;
	return send_mail($receiver, $subject, $body);

}

function send_mail_registration_success($homeserver, $user, $receiver, $username, $password, $howToURL) {
	$subject = "Registrierung auf $homeserver erfolgreich.";
	$body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren bestätigt.

Zum Anmelden kannst du folgende Zugangsdaten verwenden:
Nutzername: $username
Passwort: $password

Hinweis: Aktuell ist es nicht möglich, das Passwort selbst zu ändern. Sobald die Funktionalität zur
Verfügung steht, gibt es aber einen Hinweis.
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
	$subject = "Registrierung auf $homeserver abgelehnt.";
	$body = "Guten Tag " . $user . ",

Deine Registrierungsanfrage wurde durch die Administratoren abgelehnt.\n";

	if (empty($reason)) {
		$body .= "\nEs wurde kein Grund angegeben\n";
	} else {
		$body .= "\nAls Grund wurde folgendes angegeben:\n$reason\n";
	}

	$body .= "\nDas Administratoren-Team von " . $homeserver;
	return send_mail($receiver, $subject, $body );
}
?>