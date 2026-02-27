<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

$description = $_POST['description'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

if (!$description || !$latitude || !$longitude) {
    error("Missing required fields");
}

$image_path = "";

if (isset($_FILES['image'])) {
    $target_dir = "../uploads/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];

    if (!in_array($ext, $allowed)) {
        error("Invalid file type");
    }

    $file_name = time() . "." . $ext;
    $target_file = $target_dir . $file_name;

    move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);

    $image_path = "uploads/" . $file_name;
}

$stmt = $conn->prepare("
INSERT INTO bite_cases 
(reported_by, description, image_url, latitude, longitude, status, created_at)
VALUES (?, ?, ?, ?, ?, 'reported', NOW())
");

$stmt->bind_param("issdd", $user_id, $description, $image_path, $latitude, $longitude);

if ($stmt->execute()) {
    success("Case created", ["case_id" => $stmt->insert_id]);
} else {
    error("Failed to create case");
}