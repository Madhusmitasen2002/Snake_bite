<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// get user role safely
$user_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows == 0) {
    error("User not found");
}

$role = $user_result->fetch_assoc()['role'];

$case_id = $_POST['case_id'] ?? null;
$status = $_POST['status'] ?? null;

$allowed_status = [
    'verified',
    'assigned',
    'under_treatment',
    'recovered',
    'death'
];

if (!$case_id || !in_array($status, $allowed_status)) {
    error("Invalid input");
}

// check case exists safely
$case_stmt = $conn->prepare("SELECT id FROM bite_cases WHERE id = ?");
$case_stmt->bind_param("i", $case_id);
$case_stmt->execute();
$case_result = $case_stmt->get_result();

if ($case_result->num_rows == 0) {
    error("Case not found");
}

# 🔒 ROLE RESTRICTIONS

// allow admin to bypass everything
if ($role != 'admin') {

    if ($status == 'verified' && $role != 'chw') {
        error("Only CHW can verify cases");
    }

    if ($status == 'assigned' && $role != 'programme_manager') {
        error("Only programme manager can assign");
    }

    if (in_array($status, ['under_treatment', 'recovered', 'death']) && $role != 'treatment_provider') {
        error("Only treatment provider can update treatment");
    }
}

// update safely
$update_stmt = $conn->prepare("UPDATE bite_cases SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $status, $case_id);
$update_stmt->execute();

// log history
$log_stmt = $conn->prepare("
INSERT INTO case_status_logs (case_id, status, updated_by)
VALUES (?, ?, ?)
");
$log_stmt->bind_param("isi", $case_id, $status, $user_id);
$log_stmt->execute();

success("Status updated");