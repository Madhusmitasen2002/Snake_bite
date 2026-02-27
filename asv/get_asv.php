<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

$hospital_id = $_GET['hospital_id'] ?? null;

if (!$hospital_id) {
    error("Hospital ID required");
}

$result = $conn->query("
SELECT * FROM asv_stock 
WHERE hospital_id = $hospital_id 
ORDER BY updated_at DESC 
LIMIT 1
");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

success("ASV list", $data);
?>