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
GET ALL HOSPITALS (WITH PAGINATION)
=======================================
*/
if ($action === "getAll") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    $stmt = $conn->prepare(
        "SELECT id, name, address, district, latitude, longitude, contact_number, created_at 
         FROM hospitals 
         ORDER BY created_at DESC 
         LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    $result = $stmt->get_result();
    $hospitals = [];

    while ($row = $result->fetch_assoc()) {
        // Convert lat/lng to float for JSON
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $hospitals[] = $row;
    }

    $stmt->close();
    response(true, "Hospitals fetched successfully", $hospitals);
}

/*
=======================================
GET SINGLE HOSPITAL
=======================================
*/
if ($action === "get") {
    if (!isset($_GET['id'])) {
        response(false, "Hospital ID required");
    }

    $id = intval($_GET['id']);

    $stmt = $conn->prepare(
        "SELECT id, name, address, district, latitude, longitude, contact_number, created_at 
         FROM hospitals 
         WHERE id = ?"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $hospital = $result->fetch_assoc();

    $stmt->close();

    if (!$hospital) {
        response(false, "Hospital not found");
    }

    $hospital['latitude'] = floatval($hospital['latitude']);
    $hospital['longitude'] = floatval($hospital['longitude']);

    response(true, "Hospital fetched", $hospital);
}

/*
=======================================
CREATE HOSPITAL
=======================================
*/
if ($action === "create") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        response(false, "Invalid request data");
    }

    // Validate required fields
    $name = trim($data['name'] ?? '');
    $address = trim($data['address'] ?? '');
    $district = trim($data['district'] ?? '');
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $contact_number = trim($data['contact_number'] ?? '');

    // Validation
    if (!$name) {
        response(false, "Hospital name is required");
    }

    if ($address === '') {
        $address = null;
    }

    if ($district === '') {
        $district = null;
    }

    if ($contact_number === '') {
        $contact_number = null;
    }

    // Validate and convert coordinates
    if ($latitude !== null && $latitude !== '') {
        $latitude = floatval($latitude);
        if ($latitude < -90 || $latitude > 90) {
            response(false, "Invalid latitude (must be between -90 and 90)");
        }
    } else {
        $latitude = null;
    }

    if ($longitude !== null && $longitude !== '') {
        $longitude = floatval($longitude);
        if ($longitude < -180 || $longitude > 180) {
            response(false, "Invalid longitude (must be between -180 and 180)");
        }
    } else {
        $longitude = null;
    }

    // Check if hospital already exists (by name in same district)
    $checkStmt = $conn->prepare("SELECT id FROM hospitals WHERE name = ? AND district = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("ss", $name, $district);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        response(false, "Hospital already exists in this district");
    }

    $checkStmt->close();

    // Insert hospital
    $stmt = $conn->prepare(
        "INSERT INTO hospitals (name, address, district, latitude, longitude, contact_number, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("sssdds", 
        $name, 
        $address, 
        $district, 
        $latitude, 
        $longitude, 
        $contact_number
    );

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Hospital created successfully", ["id" => $conn->insert_id]);
    } else {
        response(false, "Creation failed: " . $stmt->error);
    }
}

/*
=======================================
UPDATE HOSPITAL
=======================================
*/
if ($action === "update") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        response(false, "Hospital ID required");
    }

    $id = intval($data['id']);

    // Extract and validate fields
    $name = trim($data['name'] ?? '');
    $address = trim($data['address'] ?? '');
    $district = trim($data['district'] ?? '');
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $contact_number = trim($data['contact_number'] ?? '');

    if (!$name) {
        response(false, "Hospital name cannot be empty");
    }

    if ($address === '') {
        $address = null;
    }

    if ($district === '') {
        $district = null;
    }

    if ($contact_number === '') {
        $contact_number = null;
    }

    // Validate and convert coordinates
    if ($latitude !== null && $latitude !== '') {
        $latitude = floatval($latitude);
        if ($latitude < -90 || $latitude > 90) {
            response(false, "Invalid latitude (must be between -90 and 90)");
        }
    } else {
        $latitude = null;
    }

    if ($longitude !== null && $longitude !== '') {
        $longitude = floatval($longitude);
        if ($longitude < -180 || $longitude > 180) {
            response(false, "Invalid longitude (must be between -180 and 180)");
        }
    } else {
        $longitude = null;
    }

    // Check if hospital exists
    $checkStmt = $conn->prepare("SELECT id FROM hospitals WHERE id = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        response(false, "Hospital not found");
    }

    $checkStmt->close();

    // Update hospital
    $stmt = $conn->prepare(
        "UPDATE hospitals 
         SET name = ?, address = ?, district = ?, latitude = ?, longitude = ?, contact_number = ? 
         WHERE id = ?"
    );

    if (!$stmt) {
        response(false, "Database error: " . $conn->error);
    }

    $stmt->bind_param("sssddsi", 
        $name, 
        $address, 
        $district, 
        $latitude, 
        $longitude, 
        $contact_number, 
        $id
    );

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Hospital updated successfully");
    } else {
        response(false, "Update failed: " . $stmt->error);
    }
}

/*
=======================================
DELETE HOSPITAL
=======================================
*/
if ($action === "delete") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        response(false, "Hospital ID required");
    }

    $id = intval($data['id']);

    // Check if hospital exists
    $checkStmt = $conn->prepare("SELECT id FROM hospitals WHERE id = ?");
    if (!$checkStmt) {
        response(false, "Database error");
    }

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        response(false, "Hospital not found");
    }

    $checkStmt->close();

    // Delete hospital
    $stmt = $conn->prepare("DELETE FROM hospitals WHERE id = ?");
    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        response(true, "Hospital deleted successfully");
    } else {
        response(false, "Deletion failed: " . $stmt->error);
    }
}

/*
=======================================
SEARCH HOSPITALS BY DISTRICT
=======================================
*/
if ($action === "searchByDistrict") {
    $district = trim($_GET['district'] ?? '');

    if (!$district) {
        response(false, "District is required");
    }

    $searchTerm = "%$district%";

    $stmt = $conn->prepare(
        "SELECT id, name, address, district, latitude, longitude, contact_number, created_at 
         FROM hospitals 
         WHERE district LIKE ? OR name LIKE ? 
         ORDER BY name ASC"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();

    $result = $stmt->get_result();
    $hospitals = [];

    while ($row = $result->fetch_assoc()) {
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $hospitals[] = $row;
    }

    $stmt->close();
    response(true, "Hospitals found", $hospitals);
}

/*
=======================================
GET HOSPITALS BY COORDINATES RADIUS
=======================================
*/
if ($action === "getNearby") {
    $lat = floatval($_GET['lat'] ?? 0);
    $lng = floatval($_GET['lng'] ?? 0);
    $radius = intval($_GET['radius'] ?? 50); // km

    if ($lat === 0 || $lng === 0) {
        response(false, "Valid latitude and longitude required");
    }

    if ($radius < 1 || $radius > 500) {
        $radius = 50;
    }

    // Get all hospitals with coordinates
    $stmt = $conn->prepare(
        "SELECT id, name, address, district, latitude, longitude, contact_number, created_at 
         FROM hospitals 
         WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $hospitals = [];

    // Haversine formula to calculate distance
    while ($row = $result->fetch_assoc()) {
        $lat1 = floatval($row['latitude']);
        $lng1 = floatval($row['longitude']);

        $R = 6371; // Earth radius in km
        $dLat = deg2rad($lat - $lat1);
        $dLng = deg2rad($lng - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $R * $c;

        if ($distance <= $radius) {
            $row['distance'] = round($distance, 2);
            $row['latitude'] = floatval($row['latitude']);
            $row['longitude'] = floatval($row['longitude']);
            $hospitals[] = $row;
        }
    }

    $stmt->close();

    // Sort by distance
    usort($hospitals, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    response(true, "Nearby hospitals found", $hospitals);
}

/*
=======================================
GET ALL DISTRICTS
=======================================
*/
if ($action === "getDistricts") {
    $stmt = $conn->prepare(
        "SELECT DISTINCT district FROM hospitals WHERE district IS NOT NULL ORDER BY district ASC"
    );

    if (!$stmt) {
        response(false, "Database error");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $districts = [];

    while ($row = $result->fetch_assoc()) {
        if ($row['district']) {
            $districts[] = $row['district'];
        }
    }

    $stmt->close();
    response(true, "Districts fetched", $districts);
}

/*
=======================================
INVALID ACTION
=======================================
*/
response(false, "Invalid action");
?>