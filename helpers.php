<?php

function success($message, $data = []) {
    echo json_encode([
        "status" => true,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

function error($message) {
    echo json_encode([
        "status" => false,
        "message" => $message
    ]);
    exit();
}
?>