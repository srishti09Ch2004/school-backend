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

$id = (int)($data["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid bus ID."
    ]);
    exit;
}

$sql = "DELETE FROM transport_buses WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "message" => "Bus Deleted Successfully"
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "Bus Delete Failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>