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

$bus_id = $data["bus_id"] ?? "";
$latitude = $data["latitude"] ?? "";
$longitude = $data["longitude"] ?? "";

if ($bus_id === "" || $latitude === "" || $longitude === "") {
    echo json_encode([
        "status" => false,
        "message" => "Bus ID, latitude and longitude are required."
    ]);
    exit;
}

$sql = "UPDATE transport_buses
        SET latitude = ?,
            longitude = ?,
            last_location_update = NOW()
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ddi",
    $latitude,
    $longitude,
    $bus_id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "message" => "Bus location updated successfully."
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "Failed to update bus location."
    ]);
}

$stmt->close();
$conn->close();

?>