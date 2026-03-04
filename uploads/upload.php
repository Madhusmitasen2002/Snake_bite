<?php

header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/cloudinary.php';

use Cloudinary\Api\Upload\UploadApi;

$allowed = ['image/jpeg','image/png','image/jpg'];

if (!in_array($_FILES['image']['type'], $allowed)) {
    echo json_encode([
        "status"=>false,
        "message"=>"Invalid file type"
    ]);
    exit;
} 

$tmp = $_FILES['image']['tmp_name'];

try {

    $result = (new UploadApi())->upload($tmp, [
        "folder" => "snakebite"
    ]);

    echo json_encode([
        "status" => true,
        "image_url" => $result['secure_url'],
        "public_id" => $result['public_id']
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}