<?php
require_once("../database.php");
$response = [
    "lookup" => []
];
try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if (!isset($input)) {
	throw new Exception('request body is no valid json');
    }

    if (!isset($input["lookup"])) {
        throw new Exception('"lookup" is not defined');
    }
    if (!is_array($input["lookup"])) {
        throw new Exception('"lookup" is not an array');
    }
    foreach ($input["lookup"] as $lookup) {
        if (!isset($lookup["medium"])) {
            throw new Exception('"lookup.medium" is not defined');
        }
        if (!isset($lookup["address"])) {
            throw new Exception('"lookup.address" is not defined');
        }
        $res2 = array();
        switch ($lookup["medium"]) {
            case "email":
                $res2 = $mx_db->searchUserByEmail($lookup["address"]);
                if (!empty($res2)) {
                    array_push($response["lookup"], [
                            "medium" => $lookup["medium"],
                            "address" => $lookup["address"],
                            "id" => [
                                "type" => "localpart",
                                "value" => $res2[0]["user_id"],
                            ]
                        ]
                    );
                }
		break;
            case "msisdn":
                break;
            default:
                throw new Exception("unknown type for \"by\" param");
        }
    }
} catch (Exception $e) {
    error_log("ídentity_bulk failed with error: " . $e->getMessage());
    $response["error"] = $e->getMessage();
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
