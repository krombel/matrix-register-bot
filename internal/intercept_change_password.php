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

// URL for this: /_matrix/client/r0/account/password?access_token=$ACCESS_TOKEN
$response=[
	"errcode" => "M_UNKNOWN",
	"error" => "Unknown error while handling password changing",
];
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	$response = [];
	// return with success
	exit();
}
try {
	$inputJSON = file_get_contents('php://input');
	$input = json_decode($inputJSON, TRUE);
	if (empty($input)) {
		throw new Exception('no valid json as input present');
	}
	if (!isset($input["auth"])) {
		throw new Exception('"auth" is not defined');
	}
	if (!isset($input["auth"]["user"]) || !isset($input["auth"]["password"])) {
		throw new Exception('"auth.user" or "auth.password" is not defined');
	}
	if (!isset($input["auth"]["type"]) || $input["auth"]["type"] !== "m.login.password") {
		throw new Exception('no or unknown auth.type');
	}
	if (!isset($input["new_password"])) {
		throw new Exception('"new_password" is not defined');
	}

	require_once("../helpers.php");
	$localpart = stripLocalpart($input["auth"]["user"]);

	if (empty($localpart)) {
		throw new Exception ("localpart cannot be identified");
	}

	require_once("../database.php");
	if ($mx_db->updatePassword(
		$localpart,
		$input["auth"]["password"],
		$input["new_password"]
	)) {
		$response=[];
	} else {
		throw new Exception("invalid credentials or another error while updating");
	}

} catch (Exception $e) {
	header("HTTP/1.0 500 Internal Error");
	error_log("failed with error: " . $e->getMessage());
	$response["error"] = $e->getMessage();
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
