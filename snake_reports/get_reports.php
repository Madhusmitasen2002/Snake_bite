<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

$result = $conn->query("
SELECT sr.*, u.name as reported_by_name
FROM snake_reports sr
JOIN users u ON sr.reported_by = u.id
ORDER BY sr.id DESC
");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

success("Snake reports", $data);