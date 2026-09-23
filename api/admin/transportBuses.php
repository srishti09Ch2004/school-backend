<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

$sql = "SELECT * FROM transport_buses ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$buses = [];

while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $buses
]);

$conn->close();

?>