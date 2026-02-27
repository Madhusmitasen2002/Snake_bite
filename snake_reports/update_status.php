<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// role check
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
$role = $user['role'];

if (!in_array($role, ['admin', 'chw'])) {
    error("Not allowed");
}

$report_id = $_POST['report_id'] ?? null;
$status = $_POST['status'] ?? null;

$allowed = ['verified', 'rescued'];

if (!$report_id || !in_array($status, $allowed)) {
    error("Invalid input");
}

// update
$conn->query("
UPDATE snake_reports 
SET status = '$status' 
WHERE id = $report_id
");

success("Status updated");