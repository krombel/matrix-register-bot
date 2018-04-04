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
class MatrixConnection
{
	private $hs;
	private $at;

	function __construct($homeserver, $access_token) {
		$this->hs = $homeserver;
		$this->at = $access_token;
	}

	function send($room_id, $message) {
		if (!$this->at) {
			error_log("No access token defined");
			return false;
		}

		$send_message = NULL;
		if (!$message) {
			error_log("no message to send");
			return false;
		} elseif(is_array($message)) {
			$send_message = $message;
		} elseif ($message instanceof MatrixMessage) {
			$send_message = $message->get_object();
		} else {
			error_log("message is of not valid type\n");
			return false;
		}

		$url="https://".$this->hs."/_matrix/client/r0/rooms/"
			. urlencode($room_id) ."/send/m.room.message?access_token=".$this->at;
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($send_message));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

		$response = $this->exec_curl_request($handle);
		return isset($response["event_id"]);
	}

	function send_msg($room_id, $message) {
		return $this->send($room_id, array(
					"msgtype" => "m.notice",
					"body" => $message
					)
				);
	}

	function hasUser($username) {
		if (!$username) {
			throw new Exception ("no user given to lookup");
		}

		$url = "https://".$this->hs."/_matrix/client/r0/profile/@" . $username . ":" . $this->hs;
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

		$res = $this->exec_curl_request($handle);
		return !(isset($res["errcode"]) && $res["errcode"] == "M_UNKNOWN");
	}

	function register($username, $password, $shared_secret) {
		if (!$username) {
			error_log("no username provided");
		}
		if (!$password) {
			error_log("no message to send");
		}

		$mac = hash_hmac('sha1', $username, $shared_secret);

		$data = array(
				"username" => $username,
				"password" => $password,
				"mac" => $mac,
			     );
		$url = "https://".$this->hs."/_matrix/client/v2_alpha/register";
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));

		return $this->exec_curl_request($handle);
	}

	function exec_curl_request($handle) {
		$response = curl_exec($handle);
		if ($response === false) {
			$errno = curl_errno($handle);
			$error = curl_error($handle);
			error_log("Curl returned error $errno: $error\n");
			curl_close($handle);
			return false;
		}
		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
		curl_close($handle);

		if ($http_code >= 500) {
			// do not want to DDOS server if something goes wrong
			sleep(10);
			return false;
		} else if ($http_code != 200) {
			$response = json_decode($response, true);
			error_log("Request has failed with error {$response['error']}\n");
			if ($http_code == 401) {
				throw new Exception('Invalid access token provided');
			}
		} else {
			$response = json_decode($response, true);
		}
		return $response;
	}
}

class MatrixMessage
{
	private $message;

	function __construct() {
		$this->message = ["msgtype" => "m.notice"];
	}

	function set_type($msgtype) {
		$this->message["msgtype"] = $msgtype;
	}

	function set_format($format) {
		$this->message["format"] = $format;
	}

	function set_body($body) {
		$this->message["body"] = $body;
	}

	function set_formatted_body($fbody, $format="org.matrix.custom.html") {
		$this->message["formatted_body"] = $fbody;
		$this->message["format"] = $format;
	}

	function get_object() {
		return $this->message;
	}
}
?>
