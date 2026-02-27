<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

$case_id = $_GET['case_id'] ?? null;

if (!$case_id) {
    error("Case ID required");
}

$result = $conn->query("
SELECT f.*, u.name as followed_by_name
FROM follow_ups f
JOIN users u ON f.followed_by = u.id
WHERE f.case_id = $case_id
ORDER BY f.follow_up_date DESC
");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

success("Follow-ups", $data);