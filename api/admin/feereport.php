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
            f.id,
            f.student_id,
            u.full_name,
            s.admission_no,
            s.class,
            s.section,
            f.total_fee,
            f.paid_fee,
            f.due_fee,
            f.payment_date,
            f.status
        FROM fees f
        LEFT JOIN students s
            ON f.student_id = s.id
        LEFT JOIN users u
            ON s.user_id = u.id
        ORDER BY f.payment_date DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$fees = [];

while ($row = $result->fetch_assoc()) {
    $fees[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $fees
]);

$conn->close();

?>