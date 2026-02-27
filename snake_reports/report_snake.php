<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

$snake_id = $_POST['snake_id'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

if (!$latitude || !$longitude) {
    error("Location required");
}

// image upload
$image_path = "";

if (isset($_FILES['photo'])) {
    $target_dir = "../uploads/reports/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];

    if (!in_array($ext, $allowed)) {
        error("Invalid image type");
    }

    $file_name = time() . "." . $ext;
    move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $file_name);

    $image_path = "uploads/reports/" . $file_name;
}

// insert
$stmt = $conn->prepare("
INSERT INTO snake_reports 
(reported_by, snake_id, photo_url, latitude, longitude, status, created_at)
VALUES (?, ?, ?, ?, ?, 'pending', NOW())
");

$stmt->bind_param("iisss", $user_id, $snake_id, $image_path, $latitude, $longitude);

if ($stmt->execute()) {
    success("Snake reported");
} else {
    error("Failed to report snake");
}