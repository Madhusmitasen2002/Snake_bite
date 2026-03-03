<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

// 🔐 AUTH → get logged-in user
$admin_id = validateToken();

// 🔹 CHECK ADMIN ROLE
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    error("Invalid user");
}

$admin = $res->fetch_assoc();

if ($admin['role'] !== 'admin') {
    error("Only admin can update roles");
}

// 🔹 INPUT
$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$new_role = $data['role'] ?? null;

if (!$user_id || !$new_role) {
    error("user_id and role required");
}

// 🔹 ALLOWED ROLES
$allowed_roles = [
    'community',
    'chw',
    'treatment_provider',
    'programme_manager',
    'admin'
];

if (!in_array($new_role, $allowed_roles)) {
    error("Invalid role");
}

// 🔹 CHECK USER EXISTS
$stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    error("User not found");
}

$user = $res->fetch_assoc();

// 🔴 PREVENT SELF DEMOTION (important)
if ($admin_id == $user_id && $new_role !== 'admin') {
    error("Admin cannot remove their own admin role");
}

// 🔹 UPDATE ROLE
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$stmt->bind_param("si", $new_role, $user_id);

if ($stmt->execute()) {
    success("Role updated", [
        "user_id" => $user_id,
        "new_role" => $new_role
    ]);
} else {
    error("Failed to update role");
}
?>