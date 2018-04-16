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
	include("config.php");
	$headers = "From: " . $config["register_email"] . "\r\n"
		. "Content-Type: text/plain;charset=utf-8";
	return mail($receiver, $subject, $body, $headers);
}

function send_mail_pending_verification($homeserver, $user, $receiver, $verify_url) {
	$subject = "Pleast approve your registration request on $homeserver";
	$body = "Dear " . $user . ",

It seems that you tried to register on $homeserver.
This homeserver requires a two step registration.

For this we want you to verify that you want to register. For this please click on this link:
$verify_url

The admins will informed about your registration request once you clicked on this link.

Note: This registration request will be cleaned up in 48 hours.
Others might take your username afterwards.

Thanks for your patience.

The admin team of " . $homeserver;
	return send_mail($receiver, $subject, $body );
}

function send_mail_pending_approval($homeserver, $user, $receiver) {
	$subject = "Registration is pending verification from an admin";
	$body = "Dear " . $user . ",

You have verified your registration request. The admins are now checking your request.

You will get an email once they approve or decline your request.

Sincerely,

The admin team of " . $homeserver;
	return send_mail($receiver, $subject, $body );
}

function send_mail_registration_allowed_but_failed($homeserver, $user, $receiver) {
	$subject = "Registration on $homeserver got approved";
	$body = "Dear " . $user . ",

Your registration request got approved by the admin team.

But there was a problem when triggering the registration request. It will be retried in a few minutes.
We hope that the issue will be fixed soon.
You will get another email with initial credentials once the registration got handled completely.

The admin team of " . $homeserver;
	return send_mail($receiver, $subject, $body);

}

function send_mail_registration_success($homeserver, $user, $receiver, $username, $password, $howToURL) {
	$subject = "Registration on $homeserver got approved";
	$body = "Dear " . $user . ",

Your registration request got verified by the admin team.

To log in you can use the following credentials::
Username: $username
Password: $password

Important: Please change your password as soon as possible after your first login.
The password is not stored in clear text on the server but people could get access to this mail
and compromise your account.
";
if (!empty($howToURL)) {
	$body .= "
You can find further help here::
$howToURL\n";
}
	$body .= "
Enjoy your usage of $homeserver.
You can ask further questions inside of the chat system.

The admin team of " . $homeserver;
	return send_mail($receiver, $subject, $body);

}
function send_mail_registration_decline($homeserver, $user, $receiver, $reason) {
	$subject = "Registration on $homeserver declined.";
	$body = "Guten Tag " . $user . ",

Your registration request got declined by the admin team.\n";

	if (empty($reason)) {
		$body .= "\nThey did not provide any reason for this\n";
	} else {
		$body .= "\nThey provide following hint for you:\n$reason\n";
	}

	$body .= "
We hope that you can understand this reason.

The admin team of " . $homeserver;
	return send_mail($receiver, $subject, $body );
}
?>
