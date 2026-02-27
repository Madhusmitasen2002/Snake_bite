<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../config/config.php';

$sql = "SELECT * FROM bite_cases ORDER BY created_at DESC";
$result = $conn->query($sql);

$cases = [];

while ($row = $result->fetch_assoc()) {
    if ($row['image_url']) {
        $row['image_url'] = BASE_URL . $row['image_url'];
    }
    $cases[] = $row;
}

success("Cases fetched", $cases);
?>