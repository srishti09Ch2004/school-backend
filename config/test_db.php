<?php

header("Content-Type: application/json");

include("db.php");

if ($conn) {
    echo json_encode([
        "status" => true,
        "message" => "Database connected successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ]);
}

?>