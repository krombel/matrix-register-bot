<?php
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
    if (empty($localpart) && !empty($mxid)) {
        // A mxid would start with an @ so we start at the 2. position
        $sepPos = strpos($mxid,':', 1);
        if ($sepPos === false) {
            // : not found. Assume mxid is localpart
            // TODO: further checks
            $localpart = $mxid;
        } else {
            $localpart = substr($mxid, 1, strpos($mxid,':') - 1 );
        }
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