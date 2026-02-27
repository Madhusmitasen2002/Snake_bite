<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// get role
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
$role = $user['role'];

// role check
if (!in_array($role, ['admin', 'treatment_provider', 'programme_manager'])) {
    error("Not allowed to update ASV");
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    error("Invalid JSON");
}

$hospital_id = $data['hospital_id'] ?? null;
$quantity = $data['available_quantity'] ?? null;

if ($hospital_id === null || $quantity === null){
    error("Missing fields");
}

// check hospital exists
$check = $conn->query("SELECT id FROM hospitals WHERE id = $hospital_id");
if ($check->num_rows == 0) {
    error("Hospital not found");
}

// insert/update (simple version = insert new log)
$stmt = $conn->prepare("
INSERT INTO asv_stock (hospital_id, available_quantity, updated_by)
VALUES (?, ?, ?)
");

$stmt->bind_param("iii", $hospital_id, $quantity, $user_id);

if ($stmt->execute()) {
    success("ASV updated");
} else {
    error("Failed to update ASV");
}
?>