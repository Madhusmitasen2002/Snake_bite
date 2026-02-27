<?php
header("Content-Type: application/json");

include '../db.php';
include '../helpers.php';

$result = $conn->query("SELECT * FROM snakes ORDER BY id DESC");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

success("Snake list", $data);