<?php
require_once("../database.php");
$response=[
    "limited" => false,
    "result" => [],
];

try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if (empty($input)) {
	    throw new Exception('no valid json as input present');
    }
    if (!isset($input["by"])) {
        throw new Exception('"by" is not defined');
    }
    if (!isset($input["search_term"])) {
        throw new Exception('"search_term" is not defined');
    }
    switch ($input["by"]) {
        case "name":
            $response["result"] = $mx_db->searchUserByName($input["search_term"]);
            break;
        case "threepid":
            $response["result"] = $mx_db->searchUserByEmail($input["search_term"]);
            break;
        default:
            throw new Exception("unknown type for \"by\" param");
    }
    
} catch (Exception $e) {
    error_log("failed with error: " . $e->getMessage());
    $response["error"] = $e->getMessage();
}
print (json_encode($response, JSON_PRETTY_PRINT) . "\n");
?>
