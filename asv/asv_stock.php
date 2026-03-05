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
 * Helper function to get all non-community users
 * Returns: admin, chw, treatment_provider, programme_manager
 */
function getNonCommunityUsers($conn) {
    $stmt = $conn->prepare(
        "SELECT id, name, role FROM users WHERE role IN ('admin', 'chw', 'treatment_provider', 'programme_manager')"
    );
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'role' => $row['role']
        ];
    }
    
    $stmt->close();
    return $users;
}

/**
 * Helper function to get hospital details
 */
function getHospitalDetails($conn, $hospital_id) {
    $stmt = $conn->prepare("SELECT id, name, district FROM hospitals WHERE id = ?");
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $hospital = $result->fetch_assoc();
    $stmt->close();
    
    return $hospital;
}

$action = $_GET['action'] ?? '';

if (!$action) {
    response(false, "No action specified");
}

/*
=======================================
GET ALL ASV STOCK (WITH HOSPITAL DETAILS)
=======================================
*/
if ($action === "getAll") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    $stmt = $conn->prepare(
        "SELECT
            h.id as hospital_id,
            h.name as hospital,
            h.address,
            h.district,
            h.contact_number,
            COALESCE(a.available_quantity,0) as available_quantity,
            a.id,
            a.updated_at,
            a.updated_by
        FROM hospitals h
        LEFT JOIN asv_stock a ON a.hospital_id = h.id
        ORDER BY a.updated_at DESC
        LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $stocks = [];

    while ($row = $result->fetch_assoc()) {
        $row['available_quantity'] = intval($row['available_quantity']);
        $row['updated_by'] = intval($row['updated_by']);
        $stocks[] = $row;
    }

    $stmt->close();
    response(true, "ASV stock fetched successfully", $stocks);
}

/*
=======================================
CREATE ASV STOCK RECORD FOR HOSPITAL
=======================================
*/
if ($action === "create") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        response(false, "Invalid request data");
    }

    $hospital_id = intval($data['hospital_id'] ?? 0);
    $available_quantity = intval($data['available_quantity'] ?? 0);
    $updated_by = intval($data['updated_by'] ?? 0);

    if (!$hospital_id) {
        response(false, "Hospital ID is required");
    }

    if (!$updated_by) {
        response(false, "Updated by (user ID) is required");
    }

    if ($available_quantity < 0) {
        response(false, "Quantity cannot be negative");
    }

    // Get hospital details
    $hospital = getHospitalDetails($conn, $hospital_id);
    if (!$hospital) {
        response(false, "Hospital not found");
    }

    // Check if stock already exists
    $existStmt = $conn->prepare("SELECT id FROM asv_stock WHERE hospital_id = ?");
    if (!$existStmt) {
        response(false, "Database error");
    }

    $existStmt->bind_param("i", $hospital_id);
    $existStmt->execute();
    $existResult = $existStmt->get_result();

    if ($existResult->num_rows > 0) {
        response(false, "ASV stock already exists for this hospital");
    }

    $existStmt->close();

    // Insert ASV stock record
    $stmt = $conn->prepare(
        "INSERT INTO asv_stock (hospital_id, available_quantity, updated_by, updated_at) 
         VALUES (?, ?, ?, NOW())"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("iii", $hospital_id, $available_quantity, $updated_by);

    if ($stmt->execute()) {
        $asv_stock_id = $conn->insert_id;
        $stmt->close();

        // 🔔 Get all non-community users
        $users = getNonCommunityUsers($conn);

        if (!empty($users)) {
            $notificationTitle = "🏥 New ASV Stock Registered";
            $notificationMessage = "A new hospital '" . $hospital['name'] . "' in " . $hospital['district'] . " district has been registered for ASV stock management with initial stock of " . $available_quantity . " vials.";

            // Create notification for each non-community user
            foreach ($users as $user) {
                createNotification(
                    $conn,
                    $user['id'],
                    $notificationTitle,
                    $notificationMessage,
                    $asv_stock_id
                );
            }
        }

        response(true, "ASV stock created successfully. Notifications sent to all staff.", [
            "id" => $asv_stock_id,
            "hospital_id" => $hospital_id,
            "notifications_sent" => count($users)
        ]);
    } else {
        response(false, "Creation failed: " . $stmt->error);
    }
}

/*
=======================================
UPDATE ASV STOCK (ADD OR REMOVE)
=======================================
*/
if ($action === "update") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        response(false, "ASV stock ID required");
    }

    $id = intval($data['id']);
    $quantity_change = intval($data['quantity_change'] ?? 0);
    $updated_by = intval($data['updated_by'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    if (!$updated_by) {
        response(false, "Updated by (user ID) is required");
    }

    if ($quantity_change === 0) {
        response(false, "Quantity change cannot be zero");
    }

    // Get current quantity and hospital info
    $checkStmt = $conn->prepare(
        "SELECT a.available_quantity, a.hospital_id, h.name as hospital_name, h.district 
         FROM asv_stock a
         LEFT JOIN hospitals h ON a.hospital_id = h.id
         WHERE a.id = ?"
    );
    
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $stock = $checkResult->fetch_assoc();

    $checkStmt->close();

    if (!$stock) {
        response(false, "ASV stock not found");
    }

    // Calculate new quantity
    $newQuantity = $stock['available_quantity'] + $quantity_change;

    if ($newQuantity < 0) {
        response(false, "Cannot remove more stock than available. Current: " . $stock['available_quantity']);
    }

    // Update stock
    $stmt = $conn->prepare(
        "UPDATE asv_stock 
         SET available_quantity = ?, updated_by = ?, updated_at = NOW() 
         WHERE id = ?"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("iii", $newQuantity, $updated_by, $id);

    if ($stmt->execute()) {
        $stmt->close();

        // 🔔 Send low stock alert if quantity drops below 50
        if ($newQuantity < 50 && $stock['available_quantity'] >= 50) {
            $adminUsers = getNonCommunityUsers($conn);
            
            if (!empty($adminUsers)) {
                $alertTitle = "⚠️ Low ASV Stock Alert";
                $alertMessage = "ASV stock at " . $stock['hospital_name'] . " (" . $stock['district'] . ") has dropped to " . $newQuantity . " vials. Please replenish stock.";

                foreach ($adminUsers as $user) {
                    createNotification(
                        $conn,
                        $user['id'],
                        $alertTitle,
                        $alertMessage,
                        $id
                    );
                }
            }
        }

        response(true, "ASV stock updated successfully", [
            "id" => $id,
            "new_quantity" => $newQuantity,
            "quantity_change" => $quantity_change
        ]);
    } else {
        response(false, "Update failed: " . $stmt->error);
    }
}

/*
=======================================
DELETE ASV STOCK RECORD
=======================================
*/
if ($action === "delete") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        response(false, "ASV stock ID required");
    }

    $id = intval($data['id']);

    $checkStmt = $conn->prepare("SELECT id FROM asv_stock WHERE id = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        response(false, "ASV stock not found");
    }

    $checkStmt->close();

    $stmt = $conn->prepare("DELETE FROM asv_stock WHERE id = ?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "ASV stock deleted successfully");
    } else {
        response(false, "Deletion failed: " . $stmt->error);
    }
}

/*
=======================================
GET STOCK SUMMARY
=======================================
*/
if ($action === "getSummary") {
    $stmt = $conn->prepare(
        "SELECT 
            COUNT(*) as total_hospitals,
            SUM(available_quantity) as total_vials,
            MIN(available_quantity) as min_quantity,
            MAX(available_quantity) as max_quantity,
            AVG(available_quantity) as avg_quantity
         FROM asv_stock"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Count low stock
    $lowStmt = $conn->prepare("SELECT COUNT(*) as count FROM asv_stock WHERE available_quantity < 50");
    if ($lowStmt) {
        $lowStmt->execute();
        $lowResult = $lowStmt->get_result();
        $lowRow = $lowResult->fetch_assoc();
        $summary['low_stock_count'] = intval($lowRow['count']);
        $lowStmt->close();
    }

    // Count good stock
    $goodStmt = $conn->prepare("SELECT COUNT(*) as count FROM asv_stock WHERE available_quantity >= 100");
    if ($goodStmt) {
        $goodStmt->execute();
        $goodResult = $goodStmt->get_result();
        $goodRow = $goodResult->fetch_assoc();
        $summary['good_stock_count'] = intval($goodRow['count']);
        $goodStmt->close();
    }

    $summary['total_hospitals'] = intval($summary['total_hospitals']);
    $summary['total_vials'] = intval($summary['total_vials']);
    $summary['min_quantity'] = intval($summary['min_quantity']);
    $summary['max_quantity'] = intval($summary['max_quantity']);
    $summary['avg_quantity'] = intval($summary['avg_quantity']);

    response(true, "Stock summary fetched", $summary);
}

response(false, "Invalid action");
?>