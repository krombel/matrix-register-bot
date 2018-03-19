<html>
	<head>
<?php
require_once "../language.php";
if (!file_exists("../config.php")) {
	print($language["NO_CONFIGURATION"]);
	exit();
}
require_once "../config.php";

// enforce admin via https
if (!isset($_SERVER['HTTPS'])) {
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], true, 301);
	exit();
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
		if (strlen($_POST["username"] > 20 || strlen($_POST["username"]) < 3)) {
			throw new Exception("USERNAME_LENGTH_INVALID");
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
		if (isset($_POST["first_name"]) && ! preg_match("/[A-Z][a-z]+/", $_POST["first_name"])) {
			throw new Exception("FIRSTNAME_INVALID_FORMAT");
		}
		if (isset($_POST["last_name"]) && ! preg_match("/[A-Z][a-z]+/", $_POST["last_name"])) {
			throw new Exception("SIRNAME_INVALID_FORMAT");
		}

		$first_name = filter_var($_POST["first_name"], FILTER_SANITIZE_STRING);
		$last_name = filter_var($_POST["last_name"], FILTER_SANITIZE_STRING);
		$username = filter_var($_POST["username"], FILTER_SANITIZE_STRING);
		if (isset($_POST["password"])) {
			$password  = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
		}
		$note   = filter_var($_POST["note"], FILTER_SANITIZE_STRING);
		$email  = filter_var($_POST["email"], FILTER_VALIDATE_EMAIL);

		require_once("../database.php");
		$res = $mx_db->addRegistration($first_name, $last_name, $username, $note, $email);

		if (!isset($res["verify_token"])) {
			error_log("sth. went wrong. registration did not throw but admin_token not set");
			throw Exception ("Unknown Error");
		}
		$verify_token = $res["verify_token"];

		$verify_url = $config["webroot"] . "/verify.php?t=" . $verify_token;
		require_once "../mail_templates.php";
		$success = send_mail_pending_verification(
			$config["homeserver"],
			$first_name . " " . $last_name,
			$email,
			$verify_url);

		$mx_db->setRegistrationStateVerify(
			($success ? RegisterState::PendingEmailVerify : RegisterState::PendingEmailSend),
			$verify_token);

		print("<title>Erfolgreich</title>");
		print("</head><body>");
		print("<h1>Erfolgreich</h1>");
		print("<p>Bitte überprüfe deine E-Mails um deine E-Mail-Adresse zu bestätigen.</p>");
		print("<a href=\"" . $config["webroot"] . "/index.php" . "\">Zur Registrierungsseite</a>");
	} catch (Exception $e) {
		print("<title>" . $language["REGISTRATION_REQUEST_FAILED"] . "</title>");
		print("</head><body>");
		print("<h1>" . $language["REGISTRATION_REQUEST_FAILED"] . "</h1>");
		if (isset($language[$e->getMessage()])) {
			print("<p>" . $language[$e->getMessage()] . "</p>");
		} else {
			print("<p>" . $e->getMessage() . "</p>");
		}
		print("<a href=\"" . $config["webroot"] . "/index.php" . "\">Zur Registrierungsseite</a>");
	}
} else {
	$_SESSION["token"] = bin2hex(random_bytes(16));
?>
		<title>Registriere dich für <?php echo $config["homeserver"]; ?></title>
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
							<h3 class="panel-title">Bitte für <?php echo $config["homeserver"]; ?> registrieren<small>2-Schritt-Registrierung</small></h3>
						</div>
						<div class="panel-body">
							<form name="regForm" role="form" action="index.php" method="post">
								<div class="row">
									<div class="col-xs-6 col-sm-6 col-md-6">
										<div class="form-group">
											<input type="text" name="first_name" id="first_name" class="form-control input-sm"
												placeholder="Vorname" pattern="[A-Z][a-z]+">
										</div>
									</div>
									<div class="col-xs-6 col-sm-6 col-md-6">
										<div class="form-group">
											<input type="text" name="last_name" id="last_name" class="form-control input-sm"
												placeholder="Nachname" pattern="[A-Z][a-z]+">
										</div>
									</div>
								</div>

								<div class="form-group">
									<input type="email" name="email" id="email" class="form-control input-sm" placeholder="E-Mail-Adresse" required>
								</div>

								<div class="form-group">
									<input type="text" name="note" id="note" class="form-control input-sm" placeholder="Notiz zu dir (max. 50 Zeichen)">
								</div>

								<div class="form-group">
									<input type="text" name="username" id="username" class="form-control input-sm"
										placeholder="Nutzername (für den Login)" pattern="[a-z1-9]{3,20}" required>
								</div>
<?php if (isset($config["getPasswordOnRegistration"]) && $config["getPasswordOnRegistration"]) { ?>
								<div class="row">
									<div class="col-xs-6 col-sm-6 col-md-6">
										<div class="form-group">
											<input type="password" name="password" id="password" class="form-control input-sm" placeholder="Passwort" required>
										</div>
									</div>
									<div class="col-xs-6 col-sm-6 col-md-6">
										<div class="form-group">
											<input type="password" name="password_confirm" id="password_confirm" class="form-control input-sm" placeholder="Passwort bestätigen" required>
										</div>
									</div>
								</div>
<?php } ?>
								<input type="hidden" name="token" id="token" value="<?php echo $_SESSION["token"]; ?>">
								<input type="submit" value="Registrieren" class="btn btn-info btn-block">

							</form>
							<p>Hinweis: <br />
							<?php echo $config["homeserver"]; ?> ist ein geschlossenes Chat-Netzwerk in dem jeder Nutzer bestätigt werden muss.<br />
							Du bekommst eine E-Mail wenn jemand deine Mitgliedschaft bestätigt hat. An diese wird auch dein initiales Passwort gesendet.
							Hinterlasse also bitte einen Hinweis zu dir (der nur den entsprechenden Personen gezeigt wird).<br />
							Liebe Grüße vom Team von <?php echo $config["homeserver"]; ?>
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
 <script type="text/javascript">
 var first_name = document.getElementById("first_name");
	first_name.oninvalid = function(event) {
		event.target.setCustomValidity("Vorname muss das Format <Großbuchstabe><Kleinbuchstaben> haben");
	}
	first_name.onkeyup = function(event) {
		event.target.setCustomValidity("");
	}
	var last_name = document.getElementById("last_name");
	last_name.oninvalid = function(event) {
		event.target.setCustomValidity("Nachname muss das Format <Großbuchstabe><Kleinbuchstaben> haben");
	}
	last_name.onkeyup = function(event) {
		event.target.setCustomValidity("");
	}
	var user_name = document.getElementById("username");
	user_name.oninvalid = function(event) {
		event.target.setCustomValidity("Nutzername darf zwischen 3 und 20 kleine Buchstaben und Zahlen enthalten");
	}
	user_name.onkeyup = function (event) {
		event.target.setCustomValidity("");
	}
<?php	if (isset($config["getPasswordOnRegistration"]) && $config["getPasswordOnRegistration"]) { ?>
	var password = document.getElementById("password")
		, confirm_password = document.getElementById("password_confirm");
	function validatePassword(){
		if(password.value != confirm_password.value) {
			confirm_password.setCustomValidity("Passwörter stimmen nicht überein");
		} else {
			confirm_password.setCustomValidity('');
		}
	}
	password.onchange = validatePassword;
	confirm_password.onkeyup = validatePassword;
<?php	} ?>
</script>
		<?php } ?>
	</body>
</html>
