<?php
header('Content-Type: application/json');
require './../vendor/autoload.php';
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

function userAuthentication() {
    $headers = apache_request_headers() ?? $_SERVER;
    $bearerToken = $headers['Authorization'] ?? null;
    
    if (isset($bearerToken)) {
        
        if (strpos($bearerToken, 'Bearer ') === 0) {
            $authorizationToken = explode(" ", $bearerToken)[1];
            if ($authorizationToken) {
                try {
                    $decoded = JWT::decode($authorizationToken, new Key(JWT_SECRET_KEY, 'HS256'));
                    return $decoded;
                } catch (Exception $e) {
                    echo json_encode(["status" => "false", "message" => "Invalid Token"]);
                    exit;
                }
            } else {
                echo json_encode(["status" => "false", "message" => "Something Went Wrong"]);
                exit;
            }
        } else {
            echo json_encode(["status" => "false", "message" => "Unauthorized User"]);
            exit;
        }
    } else {
        echo json_encode(["status" => "false", "message" => "Unauthorized User"]);
        exit;
    }
}
?>