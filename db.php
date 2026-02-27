<?php

require_once __DIR__ . '/config/env.php';

$conn = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME'],
    $_ENV['DB_PORT'] // <-- IMPORTANT
);
if ($conn->connect_error) {
    die(json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ]));
}