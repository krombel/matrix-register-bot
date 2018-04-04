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
require_once("../database.php");
$response = new stdClass;
try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if (empty($input)) {
        throw new Exception('no valid json as input present');
    }
    if (!isset($input["lookup"])) {
        throw new Exception('"lookup" is not defined');
    }
    if (!isset($input["lookup"]["medium"])) {
        throw new Exception('"lookup.medium" is not defined');
    }
    if (!isset($input["lookup"]["address"])) {
        throw new Exception('"lookup.address" is not defined');
    }
    $res2 = NULL;
    switch ($input["lookup"]["medium"]) {
        case "email":
            $res2 = $mx_db->searchUserByEmail($input["lookup"]["address"]);
            if (!empty($res2)) {
                $response = [
                    "lookup" => [
                        "medium" => $input["lookup"]["medium"],
                        "address" => $input["lookup"]["address"],
                        "id" => [
                            "type" => "localpart",
                            "value" => $res2[0]["user_id"],
                        ]
                    ]
                ];
            }
            break;
        case "msisdn":
            // This is reserved for number lookups
            throw new Exception("unimplemented lookup medium");
            break;
        default:
            throw new Exception("unknown lookup medium");
    }
} catch (Exception $e) {
    error_log("ídentity_single failed with error: " . $e->getMessage());
    $response = [
        "error" => $e->getMessage()
    ];
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
