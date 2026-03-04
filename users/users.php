<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../db.php';
include '../helpers.php';

/*
---------------------------------------
Helper Response Function
---------------------------------------
*/
function response($success, $message, $data = null) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

/*
---------------------------------------
Get Action
---------------------------------------
*/
$action = $_GET['action'] ?? '';

if (!$action) {
    response(false, "No action specified");
}

/*
=======================================
GET ALL USERS (WITH LIMIT)
=======================================
*/
if ($action === "getAll") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 500) {
        $limit = 100;
    }

    $stmt = $conn->prepare("SELECT id, name, email, role, phone_number, address, district, latitude, longitude, created_at, updated_at 
                            FROM users 
                            ORDER BY id DESC 
                            LIMIT ? OFFSET ?");
    
    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
    response(true, "Users fetched successfully", $users);
}

/*
=======================================
GET SINGLE USER
=======================================
*/
if ($action === "get") {
    if (!isset($_GET['id'])) {
        response(false, "User ID required");
    }

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT id, name, email, role, phone_number, address, district, latitude, longitude, created_at, updated_at FROM users WHERE id=?");
    
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if (!$user) {
        response(false, "User not found");
    }

    response(true, "User fetched", $user);
}

/*
=======================================
CREATE USER
=======================================
*/
if ($action === "create") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        response(false, "Invalid request data");
    }

    // Validate required fields
    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;
    $phone_number = $data['phone_number'] ?? null;
    $role = $data['role'] ?? 'community';
    $address = $data['address'] ?? null;
    $district = $data['district'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;

    // Validation
    if (!$name || !trim($name)) {
        response(false, "Name is required");
    }

    if (!$email || !trim($email)) {
        response(false, "Email is required");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        response(false, "Invalid email format");
    }

    if ($phone_number && !preg_match('/^\d{10}$/', $phone_number)) {
        response(false, "Phone number must be exactly 10 digits");
    }

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        response(false, "Email already exists");
    }

    $checkStmt->close();

    // Insert user
    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, phone_number, role, address, district, latitude, longitude, created_at, updated_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("ssssssdd", 
        $name, 
        $email, 
        $phone_number, 
        $role, 
        $address, 
        $district, 
        $latitude, 
        $longitude
    );

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "User created successfully", ["id" => $conn->insert_id]);
    } else {
        response(false, "Creation failed: " . $stmt->error);
    }
}

/*
=======================================
UPDATE USER
=======================================
*/
if ($action === "update") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        response(false, "User ID required");
    }

    $id = intval($data['id']);
    $editorId = intval($data['editorId'] ?? 0);
    
    if (!$editorId) {
        response(false, "Editor ID required");
    }

    // Check if editor has permission (admin or community role)
    $stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $editorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editor = $result->fetch_assoc();
    $stmt->close();

    if (!$editor) {
        response(false, "Editor user not found");
    }

    if ($editor['role'] !== 'admin' && $editor['role'] !== 'community') {
        response(false, "Only admins and community members can edit users");
    }

    // Extract and validate fields
    $name = $data['name'] ?? null;
    $newRole = $data['role'] ?? null;
    $phone_number = $data['phone_number'] ?? null;
    $address = $data['address'] ?? null;
    $district = $data['district'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;

    if (!$name || !trim($name)) {
        response(false, "Name cannot be empty");
    }

    if ($phone_number && !preg_match('/^\d{10}$/', $phone_number)) {
        response(false, "Phone number must be exactly 10 digits");
    }

    // Convert empty strings to null for coordinates
    $latitude = ($latitude === '' || $latitude === null) ? null : floatval($latitude);
    $longitude = ($longitude === '' || $longitude === null) ? null : floatval($longitude);

    $stmt = $conn->prepare(
        "UPDATE users 
         SET name=?, role=?, phone_number=?, address=?, district=?, latitude=?, longitude=?, updated_at=NOW() 
         WHERE id=?"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("ssssssdi", 
        $name, 
        $newRole, 
        $phone_number, 
        $address, 
        $district, 
        $latitude, 
        $longitude, 
        $id
    );

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "User updated successfully");
    } else {
        response(false, "Update failed: " . $stmt->error);
    }
}

/*
=======================================
DELETE USER
=======================================
*/
if ($action === "delete") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id'])) {
        response(false, "User ID required");
    }

    $id = intval($data['id']);

    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE id=?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        response(false, "User not found");
    }

    $checkStmt->close();

    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "User deleted successfully");
    } else {
        response(false, "Deletion failed: " . $stmt->error);
    }
}

/*
=======================================
VERIFY USER (Admin Use)
=======================================
*/
if ($action === "verify") {
    if (!isset($_GET['id'])) {
        response(false, "User ID required");
    }

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("UPDATE users SET verified=1, updated_at=NOW() WHERE id=?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "User verified successfully");
    } else {
        response(false, "Verification failed: " . $stmt->error);
    }
}

/*
=======================================
INVALID ACTION
=======================================
*/
response(false, "Invalid action");
?>