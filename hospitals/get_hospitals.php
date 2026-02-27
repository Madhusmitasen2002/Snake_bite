<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// optional filter
$district = $_GET['district'] ?? null;

if ($district) {
    $stmt = $conn->prepare("SELECT * FROM hospitals WHERE district = ?");
    $stmt->bind_param("s", $district);
} else {
    $stmt = $conn->prepare("SELECT * FROM hospitals");
}

$stmt->execute();
$result = $stmt->get_result();

$hospitals = [];

while ($row = $result->fetch_assoc()) {
    $hospitals[] = $row;
}

success("Hospitals fetched", $hospitals);