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

    if ($limit <= 0) {
        response(false, "Limit must be greater than 0");
    }

    $stmt = $conn->prepare("SELECT id, name, email, role, created_at 
                            FROM users 
                            ORDER BY id DESC 
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    response(true, "Users fetched successfully", $users);
}

/*
=======================================
GET USER
=======================================
*/
if ($action === "get") {

    if (!isset($_GET['id'])) {
        response(false, "User ID required");
    }

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        response(false, "User not found");
    }

    response(true, "User fetched", $user);
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
    $name = $data['name'] ?? null;

    if (!$name) {
        response(false, "Name cannot be empty");
    }

    $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $id);

    if ($stmt->execute()) {
        response(true, "User updated successfully");
    } else {
        response(false, "Update failed");
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

    $stmt = $conn->prepare("UPDATE users SET verified=1 WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        response(true, "User verified successfully");
    } else {
        response(false, "Verification failed");
    }
}

/*
=======================================
INVALID ACTION
=======================================
*/
response(false, "Invalid action");