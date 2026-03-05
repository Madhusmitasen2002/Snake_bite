<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../db.php';
include '../helpers.php';

function response($success, $message, $data = null) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

$action = $_GET['action'] ?? '';

if (!$action) {
    response(false, "No action specified");
}

/*
=======================================
GET ALL NOTIFICATIONS FOR USER
=======================================
*/
if ($action === "getAll") {
    if (!isset($_GET['user_id'])) {
        response(false, "User ID required");
    }

    $user_id = intval($_GET['user_id']);
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 100) {
        $limit = 20;
    }

    $stmt = $conn->prepare(
        "SELECT 
            id,
            user_id,
            title,
            message,
            related_case_id,
            is_read,
            created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = intval($row['id']);
        $row['user_id'] = intval($row['user_id']);
        $row['is_read'] = (bool)$row['is_read'];
        $row['related_case_id'] = $row['related_case_id'] ? intval($row['related_case_id']) : null;
        $notifications[] = $row;
    }

    $stmt->close();
    response(true, "Notifications fetched successfully", $notifications);
}

/*
=======================================
GET UNREAD COUNT
=======================================
*/
if ($action === "getUnreadCount") {
    if (!isset($_GET['user_id'])) {
        response(false, "User ID required");
    }

    $user_id = intval($_GET['user_id']);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    response(true, "Unread count fetched", [
        "unread_count" => intval($row['unread_count'])
    ]);
}

/*
=======================================
MARK AS READ
=======================================
*/
if ($action === "markAsRead") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['notification_id'])) {
        response(false, "Notification ID required");
    }

    $notification_id = intval($data['notification_id']);

    $stmt = $conn->prepare(
        "UPDATE notifications SET is_read = 1 WHERE id = ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $notification_id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Notification marked as read");
    } else {
        response(false, "Failed to mark as read");
    }
}

/*
=======================================
MARK ALL AS READ
=======================================
*/
if ($action === "markAllAsRead") {
    if (!isset($_GET['user_id'])) {
        response(false, "User ID required");
    }

    $user_id = intval($_GET['user_id']);

    $stmt = $conn->prepare(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "All notifications marked as read");
    } else {
        response(false, "Failed to mark as read");
    }
}

/*
=======================================
CREATE NOTIFICATION
=======================================
*/
if ($action === "create") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        response(false, "Invalid request data");
    }

    $user_id = intval($data['user_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');
    $related_case_id = isset($data['related_case_id']) ? intval($data['related_case_id']) : null;

    if (!$user_id) {
        response(false, "User ID is required");
    }

    if (!$title) {
        response(false, "Title is required");
    }

    if (!$message) {
        response(false, "Message is required");
    }

    // Check if user exists
    $userStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if (!$userStmt) {
        response(false, "Database error");
    }

    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        response(false, "User not found");
    }

    $userStmt->close();

    // Insert notification
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, related_case_id, is_read, created_at) 
         VALUES (?, ?, ?, ?, 0, NOW())"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("issi", $user_id, $title, $message, $related_case_id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Notification created successfully", [
            "id" => $conn->insert_id,
            "user_id" => $user_id,
            "is_read" => false
        ]);
    } else {
        response(false, "Failed to create notification: " . $stmt->error);
    }
}

/*
=======================================
DELETE NOTIFICATION
=======================================
*/
if ($action === "delete") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['notification_id'])) {
        response(false, "Notification ID required");
    }

    $notification_id = intval($data['notification_id']);

    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $notification_id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Notification deleted successfully");
    } else {
        response(false, "Failed to delete notification");
    }
}

/*
=======================================
GET NOTIFICATION TYPES
=======================================
*/
if ($action === "getTypes") {
    $types = [
        [
            "id" => "snake_report",
            "label" => "Snake Report",
            "icon" => "Report",
            "color" => "#ef4444"
        ],
        [
            "id" => "asv_stock",
            "label" => "ASV Stock Alert",
            "icon" => "Inventory",
            "color" => "#f59e0b"
        ],
        [
            "id" => "rescuer_application",
            "label" => "Rescuer Application",
            "icon" => "People",
            "color" => "#10b981"
        ],
        [
            "id" => "system",
            "label" => "System Notice",
            "icon" => "Info",
            "color" => "#3b82f6"
        ],
        [
            "id" => "hospital_update",
            "label" => "Hospital Update",
            "icon" => "LocalHospital",
            "color" => "#8b5cf6"
        ],
    ];

    response(true, "Notification types fetched", $types);
}

response(false, "Invalid action");
?>