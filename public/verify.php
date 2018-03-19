<html>
	<head>
<?php
require_once "../language.php";
if (!file_exists("../config.php")) {
	print($language["NO_CONFIGURATION"]);
	exit();
}
require_once "../config.php";
require_once "../mail_templates.php";

// enforce admin via https
if (!isset($_SERVER['HTTPS'])) {
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], true, 301);
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

	$user = $mx_db->getUserForVerify($token);
	if ($user == NULL) {
		throw new Exception("UNKNOWN_TOKEN");
	}
	$first_name = $user["first_name"];
	$last_name = $user["last_name"];
	$note = $user["note"];
	$email = $user["email"];
	$admin_token = $user["admin_token"];

	require_once("../MatrixConnection.php");
	$adminUrl = $config["webroot"] . "/verify_admin.php?t=" . $admin_token;
	$mxConn = new MatrixConnection($config["homeserver"], $config["access_token"]);
	$mxMsg = new MatrixMessage();
	$mxMsg->set_body($first_name . ' ' . $last_name . "möchte sich registrieren und hat folgende Notiz hinterlassen:\r\n"
		. $note . "\r\n"
		. "Zum Bearbeiten hier klicken:\r\n" . $adminUrl);
	$mxMsg->set_formatted_body($first_name . ' ' . $last_name . " möchte sich registrieren und hat folgende Notiz hinterlassen:<br />"
		. $note . "<br />"
		. "Zum Bearbeiten <a href=\"". $adminUrl . "\">hier</a> klicken");
	$mxMsg->set_type("m.text");
	$response = $mxConn->send($config["register_room"], $mxMsg);

	if ($response) {
		$message = $language["SEND_MATRIX_FAIL"];
	}
	$mx_db->setRegistrationStateVerify(
		($response ? RegisterState::PendingAdminVerify : RegisterState::PendingAdminSend),
		$token);

	send_mail_pending_approval($config["homeserver"], $first_name . " " . $last_name, $email);

	print("<title>" . $language["VERIFICATION_SUCEEDED"] . "</title>");
	print("</head><body>");
	print("<h1>" . $language["VERIFICATION_SUCEEDED"] . "</h1>");
	print("<p>" . $language["VERIFICATION_SUCCESS_BODY"] . "</p>");
	print("<a href=\"" . $config["webroot"] . "/index.php" . "\">Zur Registrierungsseite</a>");
} catch (Exception $e) {
	print("<title>" . $language["VERIFICATION_FAILED"] . "</title>");
	print("</head><body>");
	print("<h1>" . $language["VERIFICATION_FAILED"] . "</h1>");
	if (isset($language[$e->getMessage()])) {
		print("<p>" . $language[$e->getMessage()] . "</p>");
	} else {
		print("<p>" . $e->getMessage() . "</p>");
	}
	print("<a href=\"" . $config["webroot"] . "/index.php" . "\">Zur Registrierungsseite</a>");
}
?>
	</body>
</html>
