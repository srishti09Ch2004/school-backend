<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "status" => false,
        "message" => "No Data Received"
    ]);
    exit;
}

$bus_number = trim($data["bus_number"] ?? "");
$bus_type = trim($data["bus_type"] ?? "School Bus");
$driver_name = trim($data["driver_name"] ?? "");
$driver_phone = trim($data["driver_phone"] ?? "");
$route_name = trim($data["route_name"] ?? "");
$capacity = (int)($data["capacity"] ?? 0);
$status = trim($data["status"] ?? "Running");

if ($bus_number === "" || $capacity <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Bus number and capacity are required."
    ]);
    exit;
}

$students_count = 0;

$sql = "INSERT INTO transport_buses
(
    bus_number,
    bus_type,
    driver_name,
    driver_phone,
    route_name,
    capacity,
    students_count,
    status
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    "sssssiis",
    $bus_number,
    $bus_type,
    $driver_name,
    $driver_phone,
    $route_name,
    $capacity,
    $students_count,
    $status
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "message" => "Bus Added Successfully",
        "id" => $stmt->insert_id
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "Bus Add Failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>