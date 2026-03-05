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

/**
 * Helper function to create notification
 */
function createNotification($conn, $user_id, $title, $message, $related_case_id = null) {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, related_case_id, is_read, created_at) 
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("issi", $user_id, $title, $message, $related_case_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Helper function to get all admin users
 */
function getAdminUsers($conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $adminIds = [];
    
    while ($row = $result->fetch_assoc()) {
        $adminIds[] = intval($row['id']);
    }
    
    $stmt->close();
    return $adminIds;
}

$action = $_GET['action'] ?? '';

if (!$action) {
    response(false, "No action specified");
}

/*
=======================================
APPLY AS RESCUER
=======================================
*/
if ($action === "apply") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        response(false, "Invalid request data");
    }

    $user_id = intval($data['user_id'] ?? 0);
    $district = trim($data['district'] ?? '');
    $latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;

    // Validation
    if (!$user_id) {
        response(false, "User ID is required");
    }

    if (!$district) {
        response(false, "District is required");
    }

    if ($latitude === null || $longitude === null) {
        response(false, "Location coordinates are required");
    }

    // Validate coordinates
    if ($latitude < -90 || $latitude > 90) {
        response(false, "Invalid latitude");
    }

    if ($longitude < -180 || $longitude > 180) {
        response(false, "Invalid longitude");
    }

    // Check if user exists
    $userStmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
    if (!$userStmt) {
        response(false, "Database error");
    }

    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        response(false, "User not found");
    }

    $userData = $userResult->fetch_assoc();
    $userName = $userData['name'];
    $userStmt->close();

    // Check if user already applied/is a rescuer
    $checkStmt = $conn->prepare("SELECT id, is_verified FROM snake_rescuers WHERE user_id = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        if ($existing['is_verified']) {
            response(false, "You are already a verified rescuer");
        } else {
            response(false, "Your rescuer application is already pending approval");
        }
    }

    $checkStmt->close();

    // Insert rescuer application
    $stmt = $conn->prepare(
        "INSERT INTO snake_rescuers (user_id, district, latitude, longitude, is_verified, created_at) 
         VALUES (?, ?, ?, ?, 0, NOW())"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("isdd", $user_id, $district, $latitude, $longitude);

    if ($stmt->execute()) {
        $rescuer_id = $conn->insert_id;
        $stmt->close();

        // 🔔 Create notifications for all admin users
        $adminUsers = getAdminUsers($conn);
        
        if (!empty($adminUsers)) {
            $notificationTitle = "🚑 New Rescuer Application";
            $notificationMessage = "$userName has applied to become a snake rescuer in " . $district . " district.";
            
            foreach ($adminUsers as $adminId) {
                createNotification(
                    $conn,
                    $adminId,
                    $notificationTitle,
                    $notificationMessage,
                    $rescuer_id // Related rescuer ID
                );
            }
        }

        // 🔔 Also create notification for the rescuer
        createNotification(
            $conn,
            $user_id,
            "✅ Application Submitted",
            "Your rescuer application has been submitted successfully. An admin will review and verify your details soon.",
            $rescuer_id
        );

        response(true, "Rescuer application submitted successfully. Admin will verify your details soon.", [
            "id" => $rescuer_id,
            "user_id" => $user_id,
            "is_verified" => false
        ]);
    } else {
        response(false, "Failed to submit application: " . $stmt->error);
    }
}

/*
=======================================
GET RESCUER STATUS
=======================================
*/
if ($action === "getStatus") {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if (!$user_id) {
        response(false, "User ID required");
    }

    $stmt = $conn->prepare(
        "SELECT id, user_id, district, latitude, longitude, is_verified, created_at 
         FROM snake_rescuers 
         WHERE user_id = ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        response(true, "No rescuer record found", null);
    }

    $rescuer = $result->fetch_assoc();
    $stmt->close();

    $rescuer['latitude'] = floatval($rescuer['latitude']);
    $rescuer['longitude'] = floatval($rescuer['longitude']);
    $rescuer['is_verified'] = (bool)$rescuer['is_verified'];

    response(true, "Rescuer status fetched", $rescuer);
}

/*
=======================================
GET ALL RESCUER APPLICATIONS (ADMIN)
=======================================
*/
if ($action === "getPending") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    $stmt = $conn->prepare(
        "SELECT 
            sr.id,
            sr.user_id,
            u.name as user_name,
            u.email,
            u.phone_number,
            sr.district,
            sr.latitude,
            sr.longitude,
            sr.is_verified,
            sr.created_at
         FROM snake_rescuers sr
         LEFT JOIN users u ON sr.user_id = u.id
         WHERE sr.is_verified = 0
         ORDER BY sr.created_at DESC
         LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $applications = [];

    while ($row = $result->fetch_assoc()) {
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $row['is_verified'] = (bool)$row['is_verified'];
        $applications[] = $row;
    }

    $stmt->close();
    response(true, "Pending applications fetched", $applications);
}

/*
=======================================
VERIFY RESCUER (ADMIN)
=======================================
*/
if ($action === "verify") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['rescuer_id'])) {
        response(false, "Rescuer ID required");
    }

    $rescuer_id = intval($data['rescuer_id']);

    // Get rescuer details
    $detailStmt = $conn->prepare(
        "SELECT sr.user_id, u.name 
         FROM snake_rescuers sr
         LEFT JOIN users u ON sr.user_id = u.id
         WHERE sr.id = ?"
    );

    if (!$detailStmt) {
        response(false, "Database error");
    }

    $detailStmt->bind_param("i", $rescuer_id);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    if ($detailResult->num_rows === 0) {
        response(false, "Rescuer not found");
    }

    $rescuerData = $detailResult->fetch_assoc();
    $rescuer_user_id = intval($rescuerData['user_id']);
    $rescuer_name = $rescuerData['name'];
    $detailStmt->close();

    // Update verification status
    $stmt = $conn->prepare(
        "UPDATE snake_rescuers 
         SET is_verified = 1 
         WHERE id = ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $rescuer_id);

    if ($stmt->execute()) {
        $stmt->close();

        // 🔔 Send notification to rescuer that they've been verified
        createNotification(
            $conn,
            $rescuer_user_id,
            "✅ Rescuer Verified!",
            "Congratulations! Your rescuer application has been approved. You will now receive snake incident alerts.",
            $rescuer_id
        );

        response(true, "Rescuer verified successfully");
    } else {
        response(false, "Failed to verify rescuer: " . $stmt->error);
    }
}

/*
=======================================
REJECT RESCUER APPLICATION (ADMIN)
=======================================
*/
if ($action === "reject") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['rescuer_id'])) {
        response(false, "Rescuer ID required");
    }

    $rescuer_id = intval($data['rescuer_id']);
    $reason = trim($data['reason'] ?? 'No reason provided');

    // Get rescuer details
    $detailStmt = $conn->prepare(
        "SELECT sr.user_id, u.name 
         FROM snake_rescuers sr
         LEFT JOIN users u ON sr.user_id = u.id
         WHERE sr.id = ?"
    );

    if (!$detailStmt) {
        response(false, "Database error");
    }

    $detailStmt->bind_param("i", $rescuer_id);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    if ($detailResult->num_rows === 0) {
        response(false, "Rescuer not found");
    }

    $rescuerData = $detailResult->fetch_assoc();
    $rescuer_user_id = intval($rescuerData['user_id']);
    $rescuer_name = $rescuerData['name'];
    $detailStmt->close();

    // Delete the application
    $stmt = $conn->prepare("DELETE FROM snake_rescuers WHERE id = ?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $rescuer_id);

    if ($stmt->execute()) {
        $stmt->close();

        // 🔔 Send notification to rescuer that their application was rejected
        $notificationMessage = "Your rescuer application has been rejected. Reason: " . $reason;
        createNotification(
            $conn,
            $rescuer_user_id,
            "❌ Application Rejected",
            $notificationMessage,
            $rescuer_id
        );

        response(true, "Rescuer application rejected");
    } else {
        response(false, "Failed to reject application: " . $stmt->error);
    }
}

/*
=======================================
GET VERIFIED RESCUERS
=======================================
*/
if ($action === "getVerified") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    $stmt = $conn->prepare(
        "SELECT 
            sr.id,
            sr.user_id,
            u.name as user_name,
            u.email,
            u.phone_number,
            sr.district,
            sr.latitude,
            sr.longitude,
            sr.is_verified,
            sr.created_at
         FROM snake_rescuers sr
         LEFT JOIN users u ON sr.user_id = u.id
         WHERE sr.is_verified = 1
         ORDER BY sr.created_at DESC
         LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $rescuers = [];

    while ($row = $result->fetch_assoc()) {
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $row['is_verified'] = (bool)$row['is_verified'];
        $rescuers[] = $row;
    }

    $stmt->close();
    response(true, "Verified rescuers fetched", $rescuers);
}

response(false, "Invalid action");
?>