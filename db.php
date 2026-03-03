<?php

require_once __DIR__ . '/config/env.php';

$conn = new mysqli(
    getenv()['DB_HOST'],
    getenv()['DB_USER'],
    getenv()['DB_PASS'],
    getenv()['DB_NAME'],
    getenv()['DB_PORT'] // <-- IMPORTANT
);
if ($conn->connect_error) {
    die(json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ]));
}