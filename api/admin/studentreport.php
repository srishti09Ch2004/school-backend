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

$sql = "SELECT
            s.id,
            s.user_id,
            s.admission_no,
            s.class,
            s.section,
            s.roll_no,
            s.gender,
            s.dob,
            s.phone,
            s.address,
            s.status,
            u.full_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.id DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $students
]);

$conn->close();

?>