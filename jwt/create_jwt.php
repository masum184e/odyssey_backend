<?php
require './../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function create_jwt($user_email) {
    $issuedAt = time();
    $expirationTime = $issuedAt + EXPIRATION_TIME; // Token valid for 1 hour
    $payload = [
        'iat' => $issuedAt,          // Issued at
        'exp' => $expirationTime,    // Expiration time
        'userEmail' => $user_email
    ];

    // Encode the payload to create the JWT
    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
    return $jwt;
}

?>