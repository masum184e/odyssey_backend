<?php
require './../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function create_jwt($user_email, $role) {
    $issuedAt = time();
    $expirationTime = $issuedAt + EXPIRATION_TIME;
    $payload = [
        'iat' => $issuedAt, 
        'exp' => $expirationTime,
        'email' => $user_email,
        'role' => $role
    ];

    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
    return $jwt;
}

?>