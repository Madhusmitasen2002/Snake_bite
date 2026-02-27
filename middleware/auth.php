<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function validateToken() {

    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        error("No token provided");
    }

    $token = str_replace("Bearer ", "", $headers['Authorization']);

    try {

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        return $decoded->user_id;

    } catch (Exception $e) {
        error("Invalid or expired token");
    }
}