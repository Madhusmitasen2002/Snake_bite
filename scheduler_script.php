<?php
/**
 * Scheduled Task: Check ASV Stock Levels and Send Notifications
 * 
 * This script should be run periodically (every 1 hour or via cron job)
 * to check for low ASV stock and notify relevant users
 * 
 * Setup Instructions:
 * 1. Place this file in /api/scheduled-tasks/
 * 2. Set up cron job: 0 * * * * php /path/to/asv_stock_low_notification.php
 *    (runs every hour at minute 0)
 * 
 * Or set up as a web trigger:
 * Call from frontend every hour or via external cron service
 */

header("Content-Type: application/json");
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/db.php';
include (__DIR__ . '/helpers.php') ;

function response($success, $message, $data = null) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data,
        "timestamp" => date('Y-m-d H:i:s')
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
 * Get all non-community users
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
 * Get hospitals with low stock (< 20 vials)
 * that haven't been notified yet
 */
function getLowStockHospitals($conn, $threshold = 20) {
    $stmt = $conn->prepare(
        "SELECT 
            a.id,
            a.hospital_id,
            a.available_quantity,
            h.name as hospital_name,
            h.district,
            a.last_low_stock_notification
         FROM asv_stock a
         LEFT JOIN hospitals h ON a.hospital_id = h.id
         WHERE a.available_quantity < ?
         AND (
            a.last_low_stock_notification IS NULL
            OR a.available_quantity <= LEAST(a.available_quantity - 5, a.last_low_stock_notification - 5)
         )"
    );
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    $hospitals = [];
    
    while ($row = $result->fetch_assoc()) {
        $hospitals[] = [
            'id' => intval($row['id']),
            'hospital_id' => intval($row['hospital_id']),
            'quantity' => intval($row['available_quantity']),
            'hospital_name' => $row['hospital_name'],
            'district' => $row['district'],
            'last_notification' => $row['last_low_stock_notification']
        ];
    }
    
    $stmt->close();
    return $hospitals;
}

/**
 * Update last notification time for a hospital
 */
function updateLastNotificationTime($conn, $asv_stock_id, $quantity) {
    $stmt = $conn->prepare(
        "UPDATE asv_stock SET last_low_stock_notification = ? WHERE id = ?"
    );
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $quantity, $asv_stock_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Check if notification already sent in last N hours
 */
function checkRecentNotification($conn, $asv_stock_id, $hours = 6) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE related_case_id = ? 
         AND title LIKE '%Low Stock%'
         AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)"
    );
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $asv_stock_id, $hours);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['count']) > 0;
}

// Main execution
try {
    // Get low stock hospitals
    $lowStockHospitals = getLowStockHospitals($conn, 20);
    
    if (empty($lowStockHospitals)) {
        response(true, "No hospitals with low stock detected", [
            "hospitals_checked" => 0,
            "notifications_sent" => 0,
            "next_check" => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);
    }
    
    // Get all non-community users
    $users = getNonCommunityUsers($conn);
    
    if (empty($users)) {
        response(true, "No staff users found to notify", [
            "hospitals_checked" => count($lowStockHospitals),
            "notifications_sent" => 0
        ]);
    }
    
    $totalNotifications = 0;
    $notifiedHospitals = [];
    
    // Process each low stock hospital
    foreach ($lowStockHospitals as $hospital) {
        // Check if we already notified recently
        $recentNotification = checkRecentNotification($conn, $hospital['id'], 6);
        
        if ($recentNotification) {
            // Skip if notified in last 6 hours
            continue;
        }
        
        // Create notification for each staff user
        foreach ($users as $user) {
            $title = "🚨 CRITICAL: Low ASV Stock Alert";
            
            if ($hospital['quantity'] <= 10) {
                $title = "🚨 CRITICAL: VERY LOW ASV Stock - " . $hospital['quantity'] . " vials!";
            } elseif ($hospital['quantity'] <= 5) {
                $title = "🆘 EMERGENCY: ASV Stock CRITICALLY LOW - " . $hospital['quantity'] . " vials!";
            }
            
            $message = "URGENT: " . $hospital['hospital_name'] . " (" . $hospital['district'] . ") has only " . 
                      $hospital['quantity'] . " vials of ASV stock remaining. " .
                      "Immediate replenishment required. This is below the safety threshold of 20 vials.";
            
            $notificationCreated = createNotification(
                $conn,
                $user['id'],
                $title,
                $message,
                $hospital['id']
            );
            
            if ($notificationCreated) {
                $totalNotifications++;
            }
        }
        
        // Update last notification time
        updateLastNotificationTime($conn, $hospital['id'], $hospital['quantity']);
        
        $notifiedHospitals[] = [
            'hospital_name' => $hospital['hospital_name'],
            'district' => $hospital['district'],
            'quantity' => $hospital['quantity'],
            'users_notified' => count($users)
        ];
    }
    
    response(true, "Low stock notifications processed", [
        "hospitals_with_low_stock" => count($lowStockHospitals),
        "hospitals_notified" => count($notifiedHospitals),
        "total_notifications_sent" => $totalNotifications,
        "notified_hospitals" => $notifiedHospitals,
        "users_per_hospital" => count($users),
        "next_check" => date('Y-m-d H:i:s', strtotime('+1 hour')),
        "timestamp" => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    response(false, "Error executing scheduled task: " . $e->getMessage(), [
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
?>