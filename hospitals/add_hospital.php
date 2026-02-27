<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// check role
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();

if ($user['role'] != 'admin') {
    error("Only admin can add hospital");
}

$name = $_POST['name'] ?? null;
$address = $_POST['address'] ?? null;
$district = $_POST['district'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$contact_number = $_POST['contact_number'] ?? null;

if (!$name || !$district) {
    error("Missing required fields");
}

$stmt = $conn->prepare("
INSERT INTO hospitals 
(name, address, district, latitude, longitude, contact_number, created_at)
VALUES (?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param("ssssss", $name, $address, $district, $latitude, $longitude, $contact_number);

$stmt->execute();

success("Hospital added");