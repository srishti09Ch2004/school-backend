<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

include("../../config/db.php");

// Only GET request allowed

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}
// Get student ID

$student_id = isset($_GET["student_id"])
    ? intval($_GET["student_id"])
    : 0;

if ($student_id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "student_id is required"
    ]);

    exit;
}
// Fetch payment history

$sql = "
SELECT
    fp.id,
    fp.fee_id,
    fp.student_id,
    fp.amount,
    fp.payment_date,
    fp.payment_method,
    fp.transaction_id,
    fp.receipt_no,
    fp.remarks,
    fp.created_at
FROM fee_payments fp
WHERE fp.student_id = ?
ORDER BY fp.payment_date DESC, fp.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$payments = [];

while ($row = mysqli_fetch_assoc($result)) {

    $payments[] = $row;
}

mysqli_stmt_close($stmt);

//  Response

echo json_encode([
    "status" => true,
    "student_id" => $student_id,
    "total_payments" => count($payments),
    "data" => $payments
]);

?>