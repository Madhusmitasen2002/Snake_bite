<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';
include '../middleware/auth.php';

$user_id = validateToken();

$case_id = $_GET['case_id'] ?? null;

if (!$case_id) {
    error("case_id required");
}

// get case info
$case = $conn->query("
SELECT 
  bite_cases.*, 
  users.name as reported_by_name
FROM bite_cases
JOIN users ON users.id = bite_cases.reported_by
WHERE bite_cases.id = $case_id
");

if ($case->num_rows == 0) {
    error("Case not found");
}

$case_data = $case->fetch_assoc();

// get timeline
$logs = $conn->query("
SELECT 
  case_status_logs.*, 
  users.name as updated_by_name
FROM case_status_logs
LEFT JOIN users ON users.id = case_status_logs.updated_by
WHERE case_id = $case_id
ORDER BY created_at ASC
");

$timeline = [];

while ($row = $logs->fetch_assoc()) {
    $timeline[] = $row;
}

// attach timeline
$case_data['timeline'] = $timeline;

success("Case detail fetched", $case_data);