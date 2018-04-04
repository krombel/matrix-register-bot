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
require_once("config.php");
if (!isset($config["databaseURI"])) {
	throw new Exception ("malformed configuration: databaseURI not defined");
}

abstract class RegisterState
{
	// Sending an E-Mail failed in the first attempt. Will retry later
	const PendingEmailSend = 0;
	// User got a mail. We wait for it to verfiy
	const PendingEmailVerify = 1;
	// Sending a message to the register room failed on first attempt
	const PendingAdminSend = 5;
	// No admin has verified the registration yet
	const PendingAdminVerify = 6;
	// Registration failed on first attempt. Will retry
	const PendingRegistration = 7;

	// in this case we have to reset the password of the user (or should we store it for this case?)
	const PendingSendRegistrationMail = 8;

	// State to allow persisting in the database although an admin declined it.
	// Will be removed regularly
	const RegistrationAccepted = 7;
	const RegistrationDeclined = 13;

	// User got successfully registered. Will be cleaned up later
	const AllDone = 100;
}

class mxDatabase
{
	private $db = NULL;

	/**
	 * Creates mxDatabase object
	 * @param config object which has following members:
	 *     databaseURI: path to the sqlite file where the credentials should be stored
	 *     or a param which can be used to connect to a database with PDO
	 * 	   databaseUser and databasePass when authentication is required
	 *     register_email which email does the register bot have (here used for providing lookup)
	 */
	function __construct($config) {
		if (empty($config)) {
			throw new Exception("config is empty");
		}
		if (!isset($config["databaseURI"])) {
			throw new Exception("'databaseURI' not defined");
		}
		$db_input = $config["databaseURI"];
		$user = '';
		$password = '';
		if (isset($config["databaseUser"]) && isset($config["databasePass"])) {
			// only use it when both are defined
			$user = $config["databaseUser"];
			$password = $config["databasePass"];
		}
		// create database file when not existent yet
		$this->db = new PDO($db_input, $user, $password);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db->exec("CREATE TABLE IF NOT EXISTS registrations(
			id SERIAL PRIMARY KEY,
			state INT DEFAULT 0,
			first_name TEXT,
			last_name TEXT,
			username TEXT,
			password_hash TEXT DEFAULT '',
			note TEXT,
			email TEXT,
			verify_token TEXT,
			admin_token TEXT,
			request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)");
		$this->db->exec("CREATE TABLE IF NOT EXISTS logins (
			id SERIAL PRIMARY KEY,
			active INT DEFAULT 1,
			first_name TEXT,
			last_name TEXT,
			localpart TEXT,
			password_hash TEXT,
			email TEXT,
			create_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)");
		// make sure the bot is allowed to login
		if (!$this->userRegistered("register_bot")) {
			$password = $this->addUser("Register", "Bot", "register_bot", $config["register_email"]);
			$config["register_password"] = $password;
			$myfile = fopen(dirname(__FILE__) . "/config.json", "w");
			fwrite($myfile, json_encode($config, JSON_PRETTY_PRINT));
			fclose($myfile);
		}

		// set writeable when not set already
		if (strpos($db_input, "sqlite") === 0) {
			$sqlite_file = substr($db_input, strlen("sqlite:"));
			if (!is_writable($sqlite_file)) {
				chmod($sqlite_file, 0660);
			}
			unset($sqlite_file);
		}
	}

	/**
	 * WARNING: This allows accessing the database directly.
	 * This was only be added for convenience. You are advised to not use this function extensively
	 *
	 * @param sql String wich will be passed directly to the database
	 * @return Response of PDO::query()
	 */
	function query($sql) {
		return $this->db->query($sql);
	}

	function setRegistrationStateVerify($state, $token) {
		$sql = "UPDATE registrations SET state = " . $state
			. " WHERE verify_token = '" . $token . "';";

		return $this->db->exec($sql);
	}

	function setRegistrationStateById($state, $id) {
		$sql = "UPDATE registrations SET state = " . $state
			. " WHERE id = '" . $id . "';";

		return $this->db->exec($sql);
	}

	function setRegistrationStateAdmin($state, $token) {
		$sql = "UPDATE registrations SET state = " . $state
			. " WHERE admin_token = '" . $token . "';";

		return $this->db->exec($sql);
	}

	function setRegistrationState($state, $token) {
		$sql = "UPDATE registrations SET state = " . $state
			. " WHERE verify_token = '" . $token . "' OR admin_token = '" . $token . "';";

		return $this->db->exec($sql);
	}

	function userPendingRegistrations($username) {
		$sql = "SELECT COUNT(*) FROM registrations WHERE username = '" . $username . "' AND NOT state = "
		       . RegisterState::RegistrationDeclined . " LIMIT 1;";
		$res = $this->db->query($sql);
		if ($res->fetchColumn() > 0) {
			return true;
		}
		return false;
	}
	function userRegistered($username) {
		$sql = "SELECT COUNT(*) FROM logins WHERE localpart = '" . $username . "' LIMIT 1;";
		$res = $this->db->query($sql);
		if ($res->fetchColumn() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * Adds user to the database. Next steps should be sending a verify-mail to the user
	 * @param first_name First name of the user
	 * @param last_name Sirname of the user
	 * @param username the future localpart of that user
	 * @param note Note the user typed in to give a hint
	 * @param email E-Mail-Adress which will be stored into the database.
	 * 			This will be send to the server on first login
	 *
	 * @return ["verify_token"]
	 */
	function addRegistration($first_name, $last_name, $username, $note, $email) {
		if ($this->userPendingRegistrations($username)) {
			throw new Exception("USERNAME_PENDING_REGISTRATION");
		}
		if ($this->userRegistered($username)) {
			throw new Exception("USERNAME_REGISTERED");
		}

		$verify_token = bin2hex(random_bytes(16));
		$admin_token = bin2hex(random_bytes(16));

		$this->db->exec("INSERT INTO registrations
			(first_name, last_name, username, note, email, verify_token, admin_token)
			VALUES ('" . $first_name."','" . $last_name . "','" . $username . "','"	. $note . "','"
			. $email."','"	.$verify_token."','"	.$admin_token."')");

		return [
			"verify_token"=> $verify_token,
		];
	}

	/**
	 * Gets the user for the verify_admin page.
	 *
	 * @return ArrayOfUser|NULL Array with "first_name, last_name, username, note and email"
	 * 		as members
	 */
	function getUserForApproval($admin_token) {
		$sql = "SELECT COUNT(*) FROM registrations WHERE admin_token = '" . $admin_token . "'"
			. " AND state = " . RegisterState::PendingAdminVerify . " LIMIT 1;";
		$res = $this->db->query($sql);

		if ($res->fetchColumn() > 0) {
			$sql = "SELECT first_name, last_name, username, note, email FROM registrations"
				. " WHERE admin_token = '" . $admin_token . "'"
				. " AND state = " . RegisterState::PendingAdminVerify
				. " LIMIT 1;";
			foreach ($this->db->query($sql) as $row) {
				// will only be executed once
				return $row;
			}
		}
		return NULL;
	}

	/**
	 * Gets the user when it opens the page to verify its mail
	 *
	 * @return ArrayOfUser|NULL Array with "first_name, last_name, note, email and admin_token"
	 * 		as members
	 */
	function getUserForVerify($verify_token) {
		$sql = "SELECT COUNT(*) FROM registrations WHERE verify_token = '" . $verify_token . "'"
			. " AND state = " . RegisterState::PendingEmailVerify . " LIMIT 1;";
		$res = $this->db->query($sql);

		if ($res->fetchColumn() > 0) {
			$sql = "SELECT first_name, last_name, note, email, admin_token FROM registrations "
				. " WHERE verify_token = '" . $verify_token . "'"
				. " AND state = " . RegisterState::PendingEmailVerify . " LIMIT 1;";
			foreach ($this->db->query($sql) as $row) {
				// will only be executed once
				return $row;
			}
		}
		return NULL;
	}

	function getUserForLogin($localpart, $password) {
		$sql = "SELECT COUNT(*) FROM logins WHERE localpart = '" . $localpart
			. "' AND active = 1 LIMIT 1;";
		$res = $this->db->query($sql);

		if ($res->fetchColumn() > 0) {
			$sql = "SELECT first_name, last_name, email, password_hash FROM logins "
				. " WHERE localpart = '" . $localpart . "' AND active = 1 LIMIT 1;";
			foreach ($this->db->query($sql) as $row) {
				if (password_verify($password, $row["password_hash"])) {
					return $row;
				}
			}
		}
		return NULL;
	}

	/**
	 * adds User to be able to login afterwards.
	 * @param first_name First name of the user
	 * @param last_name Sirname of the user
	 * @param username the future localpart of that user
	 * @param email E-Mail-Adress which will be stored into the database.
	 * 			This will be send to the server on first login
	 *
	 * @return password|NULL with member password as this method generates a
	 * 			password and saves that into the database
	 * 			NULL when failed
	 *
	 */
	function addUser($first_name, $last_name, $username, $email) {
		// check if user already exists and abort in that case
		if ($this->userRegistered($username)) {
			return NULL;
		}

		// generate a password with 10 characters
		$password = bin2hex(openssl_random_pseudo_bytes(5));
		$password_hash = password_hash($password, PASSWORD_BCRYPT, ["cost"=>12]);

		$sql = "INSERT INTO logins (first_name, last_name, localpart, password_hash, email) VALUES "
			. "('" . $first_name."','" . $last_name . "','" . $username . "','"
			. $password_hash . "','" . $email . "');";

		if ($this->db->exec($sql)) {
			return $password;
		}
		return NULL;
	}

	function updatePassword($localpart, $old_password, $new_password) {
		$user = $this->getUserForLogin($localpart, $old_password);
		if ($user == NULL) {
		    throw new Exception ("user with that credentials not found");
		}

		// The credentials were fine. So now set the new password
		$password_hash = password_hash($new_password, PASSWORD_BCRYPT, ["cost"=>12]);

		$sql = "UPDATE logins SET password_hash = '" . $password_hash . "'"
			. "WHERE localpart = '" . $localpart . "'";

		if ($this->db->exec($sql)) {
			return true;
		}
		return false;
	}

	function searchUserByName($search_term) {
		$term = filter_var($search_term, FILTER_SANITIZE_STRING);
		$result = array();
		$sql = "SELECT COUNT(*) FROM logins WHERE"
			. " localpart LIKE '" . $term . "%' AND active = 1;";
		$res = $this->db->query($sql);

		if ($res->fetchColumn() > 0) {
			$sql = "SELECT first_name, last_name, localpart FROM logins WHERE"
				. " localpart LIKE '" . $term . "%' AND active = 1;";
			foreach ($this->db->query($sql) as $row) {
				array_push($result, [
					"display_name" => $row["first_name"] . " " . $row["last_name"],
					"user_id" => $row["localpart"],
				]);
			}
		}
		return $result;
	}

	function searchUserByEmail($search_term) {
		$term = filter_var($search_term, FILTER_SANITIZE_STRING);
		$result = array();
		$sql = "SELECT COUNT(*) FROM logins WHERE"
			. " email = '" . $term . "' AND active = 1;";
		$res = $this->db->query($sql);

		if ($res->fetchColumn() > 0) {
			$sql = "SELECT first_name, last_name, localpart FROM logins WHERE"
				. " email = '" . $term . "' AND active = 1;";
			foreach ($this->db->query($sql) as $row) {
				array_push($result, [
					"display_name" => $row["first_name"] . " " . $row["last_name"],
					"user_id" => $row["localpart"],
				]);
			}
		}
		return $result;
	}
}

if (!isset($mx_db)) {
		$mx_db = new mxDatabase($config);
}
?>
