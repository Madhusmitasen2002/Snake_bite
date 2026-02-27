<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

// role check
$user = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
if ($user['role'] != 'admin') {
    error("Only admin can add snakes");
}

// inputs
$name = $_POST['snake_name'] ?? null;
$scientific = $_POST['scientific_name'] ?? null;
$description = $_POST['description'] ?? null;
$is_venomous = $_POST['is_venomous'] ?? null;

if (!$name || $is_venomous === null) {
    error("Missing required fields");
}

// image upload (optional but good)
$image_path = "";

if (isset($_FILES['image'])) {
    $target_dir = "../uploads/snakes/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];

    if (!in_array($ext, $allowed)) {
        error("Invalid image type");
    }

    $file_name = time() . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $file_name);

    $image_path = "uploads/snakes/" . $file_name;
}

// insert
$stmt = $conn->prepare("
INSERT INTO snakes (snake_name, scientific_name, description, image_url, is_venomous, created_at)
VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param("ssssi", $name, $scientific, $description, $image_path, $is_venomous);

if ($stmt->execute()) {
    success("Snake added");
} else {
    error("Failed to add snake");
}