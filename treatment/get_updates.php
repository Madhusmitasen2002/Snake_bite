<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

$case_id = $_GET['case_id'] ?? null;

if (!$case_id) {
    error("Case ID required");
}

$result = $conn->query("
SELECT tu.*, u.name as updated_by_name
FROM treatment_updates tu
JOIN users u ON tu.updated_by = u.id
WHERE tu.case_id = $case_id
ORDER BY tu.updated_at DESC
");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

success("Treatment updates", $data);