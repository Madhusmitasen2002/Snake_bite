<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../vendor/autoload.php';

include '../db.php';
include '../helpers.php';

use Firebase\JWT\JWT;

$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;

if (!$email || !$password) {
    error("Email and password required");
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error("User not found");
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    error("Invalid password");
}

// 🔐 REAL JWT
$key = $_ENV['JWT_SECRET'];

$payload = [
    "user_id" => $user['id'],
    "role" => $user['role'],
    "iat" => time(),
    "exp" => time() + (60 * 60 * 24) // 24 hours
];

$jwt = JWT::encode($payload, $key, 'HS256');

success("Login success", [
    "token" => $jwt,
    "user_id" => $user['id'],
    "role" => $user['role']
]);