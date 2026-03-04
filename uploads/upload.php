<?php
var_dump(getenv('CLOUDINARY_API_KEY'));
exit;
header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/cloudinary.php';

use Cloudinary\Api\Upload\UploadApi;

if (!isset($_FILES['image'])) {
    echo json_encode([
        "status" => false,
        "message" => "No file provided"
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