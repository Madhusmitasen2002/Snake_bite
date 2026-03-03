<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/cors.php';
include '../db.php';
include '../helpers.php';
$data = json_decode(file_get_contents("php://input"), true);
// inputs
$name = $data['name'] ?? null;
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;
$role ="community"; // default role

if (!$name || !$email || !$password) {
    error("All fields required");
}
// check if the user already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    error("User already exists");
}   

// hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// insert user
$stmt = $conn->prepare("
INSERT INTO users (name, email, password,role)
VALUES (?, ?, ?, ?)
");

$stmt->bind_param("ssss", $name, $email, $hashedPassword, $role );

if ($stmt->execute()) {
    success("User registered",[
        "user_id" => $stmt->insert_id,
        "name" => $name,
        "email" => $email,
        "role" => $role
    ]);
} else {
    error("Registration failed");
}
?>