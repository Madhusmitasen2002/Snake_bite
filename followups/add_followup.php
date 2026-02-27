<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// role check
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
$role = $user['role'];

if (!in_array($role, ['chw', 'programme_manager'])) {
    error("Not allowed to add follow-ups");
}

// input
$case_id = $_POST['case_id'] ?? null;
$notes = $_POST['notes'] ?? null;
$follow_up_date = $_POST['follow_up_date'] ?? null;

if (!$case_id || !$follow_up_date) {
    error("Missing fields");
}

// check case
$check = $conn->query("SELECT id FROM bite_cases WHERE id = $case_id");
if ($check->num_rows == 0) {
    error("Case not found");
}

// insert
$stmt = $conn->prepare("
INSERT INTO follow_ups (case_id, followed_by, notes, follow_up_date)
VALUES (?, ?, ?, ?)
");

$stmt->bind_param("iiss", $case_id, $user_id, $notes, $follow_up_date);

if ($stmt->execute()) {
    success("Follow-up added");
} else {
    error("Failed to add follow-up");
}