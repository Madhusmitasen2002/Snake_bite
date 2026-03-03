<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../vendor/autoload.php';

include '../db.php';
include '../helpers.php';

use Firebase\JWT\JWT;

// Input
$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$email || !$password) {
    error("Email and password required");
}

// DB safety check
if (!$conn) {
    error("DB connection failed");
}

if ($conn->connect_error) {
    error($conn->connect_error);
}

// Query
$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");

if (!$stmt) {
    error($conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    error("User not found");
}

$user = $result->fetch_assoc();

// Password check
if (!password_verify($password, $user['password'])) {
    error("Invalid password");
}

// JWT
$key = getenv('JWT_SECRET');

if (!$key) {
    error("JWT secret missing");
}

$payload = [
    "name" => $user['name'],
    "user_id" => $user['id'],
    "role" => $user['role'],
    "iat" => time(),
    "exp" => time() + (60 * 60 * 24)
];

$jwt = JWT::encode($payload, $key, 'HS256');

// Response
success("Login success", [
    "name" => $user['name'],
    "token" => $jwt,
    "user_id" => $user['id'],
    "role" => $user['role']
]);