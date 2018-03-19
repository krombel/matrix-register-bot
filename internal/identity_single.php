<?php
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
    $res2 = array();
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
        default:
            throw new Exception("unknown type for \"by\" param");
    }
} catch (Exception $e) {
    error_log("ídentity_bulk failed with error: " . $e->getMessage());
    $response["error"] = $e->getMessage();
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
