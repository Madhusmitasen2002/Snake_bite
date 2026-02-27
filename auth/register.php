<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

// inputs
$name = $_POST['name'] ?? null;
$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$role = $_POST['role'] ?? 'community';

if (!$name || !$email || !$password) {
    error("All fields required");
}

// allowed roles
$all_roles = [
    'community',
    'chw',
    'treatment_provider',
    'programme_manager',
    'admin'
];

if (!in_array($role, $all_roles)) {
    error("Invalid role");
}

# ✅ BOOTSTRAP (FIRST ADMIN FIX)

// check if admin exists
$checkAdmin = $conn->query("SELECT id FROM users WHERE role = 'admin'");

if ($checkAdmin->num_rows == 0) {
    // first user becomes admin automatically
    $role = 'admin';
} else {

    # 🔒 ROLE SECURITY

    if ($role != 'community') {

        include '../middleware/auth.php';
        $creator_id = validateToken();

        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $creator_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 0) {
            error("Invalid creator");
        }

        $creator_role = $res->fetch_assoc()['role'];

        if ($creator_role != 'admin') {
            error("Only admin can create this role");
        }
    }
}

// hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// insert user
$stmt = $conn->prepare("
INSERT INTO users (name, email, password, role)
VALUES (?, ?, ?, ?)
");

$stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    success("User registered", ["role" => $role]);
} else {
    error("Registration failed");
}
?>