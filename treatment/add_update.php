<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// get role
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
$role = $user['role'];

// ONLY treatment_provider
if ($role != 'treatment_provider') {
    error("Only treatment provider can add treatment updates");
}

// input
$case_id = $_POST['case_id'] ?? null;
$status = $_POST['status'] ?? null;
$note = $_POST['note'] ?? null;

if (!$case_id || !$status) {
    error("Missing fields");
}

// check case exists
$check = $conn->query("SELECT id FROM bite_cases WHERE id = $case_id");
if ($check->num_rows == 0) {
    error("Case not found");
}

// insert
$stmt = $conn->prepare("
INSERT INTO treatment_updates (case_id, updated_by, status, note, updated_at)
VALUES (?, ?, ?, ?, NOW())
");

$stmt->bind_param("iiss", $case_id, $user_id, $status, $note);

if ($stmt->execute()) {
    success("Treatment update added");
} else {
    error("Failed to add update");
}