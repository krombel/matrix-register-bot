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
$response = [
    "auth" => [
        "success" => false,
    ]
];

require_once("../database.php");
abstract class LoginRequester {
    const UNDEFINED = 0;
    const MXISD = 1;
    const RestAuth = 2;
}
$loginRequester = LoginRequester::UNDEFINED;

try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $mxid = NULL;
    $localpart = NULL;
    if (isset($input["user"])) {
        if (isset($input["user"]["localpart"])) {
            $localpart = $input["user"]["localpart"];
            $loginRequester = LoginRequester::MXISD;
        } elseif (isset($input["user"]["id"])) {
            // compatibility for matrix-synapse-rest-auth
            $mxid = $input["user"]["id"];
            $loginRequester = LoginRequester::RestAuth;
        } elseif (isset($input["user"]["mxid"])) {
            // compatibility for mxisd
            $mxid = $input["user"]["mxid"];
            $loginRequester = LoginRequester::MXISD;
        }
    }

    // prefer the localpart attribute of mxisd. But in case of matrix-synapse-rest-auth
    // we have to parse it on our own
    if (empty($localpart))
	require_once("../helpers.php");
	$localpart = stripLocalpart($input["auth"]["user"]);
    }

    if (empty($localpart)) {
        throw new Exception ("localpart cannot be identified");
    }

    $password = NULL;
    if (isset($input["user"]) && isset($input["user"]["password"])) {
        $password = $input["user"]["password"];
    }
    if (empty($password)) {
        throw new Exception ("password is not present");
    }

    $user = $mx_db->getUserForLogin($localpart, $password);
    if (!$user) {
        throw new Exception("user not found or password did not match");
    }
    $response["auth"]["success"] = true;
    $response["auth"]["profile"] = [
        "display_name" => $user["first_name"] . " " . $user["last_name"],
        "three_pids" => [
            [
                "medium" => "email",
                "address" => $user["email"],
            ],
        ],
    ];

    switch ($loginRequester) {
        case LoginRequester::RestAuth:
            $response["auth"]["mxid"] = $mxid;
            break;
        case LoginRequester::MXISD;
            $response["auth"]["id"] = [
                "type" => "localpart",
                "value" => $localpart,
            ];
            break;
        default:
            // only return that it was successful.
            // we do not know how the data shall be transmitted so we do nothing with it
            $response["auth"]["success"] = false;
            break;
    }
} catch (Exception $e) {
    error_log("Auth failed with error: " . $e->getMessage());
    $response["auth"]["error"] = $e->getMessage();
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
