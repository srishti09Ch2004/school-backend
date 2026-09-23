<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);
    exit;
}

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if (!$data) {
    echo json_encode([
        "status" => false,
        "message" => "No data received"
    ]);
    exit;
}

$student_id = intval($data["student_id"] ?? 0);
$total_fee  = floatval($data["total_fee"] ?? 0);
$payment_date = !empty($data["payment_date"])
    ? $data["payment_date"]
    : date("Y-m-d");


// Validation

if ($student_id <= 0 || $total_fee <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Student and valid total fee are required"
    ]);

    exit;
}

// Check Student

$studentSql = "
    SELECT id
    FROM students
    WHERE id = ?
    LIMIT 1
";

$studentStmt = mysqli_prepare(
    $conn,
    $studentSql
);

if (!$studentStmt) {

    echo json_encode([
        "status" => false,
        "message" => "Student query preparation failed"
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $studentStmt,
    "i",
    $student_id
);

mysqli_stmt_execute($studentStmt);

$studentResult =
    mysqli_stmt_get_result($studentStmt);

$student =
    mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStmt);

if (!$student) {

    echo json_encode([
        "status" => false,
        "message" => "Student not found"
    ]);

    exit;
}


/*
 Check Existing Pending Fee|
 One student should not accidentally receive duplicate
 active fee records.

*/

$checkSql = "
    SELECT
        id,
        total_fee,
        paid_fee,
        due_fee,
        status
    FROM fees
    WHERE student_id = ?
    AND status IN ('Pending', 'Partial')
    LIMIT 1
";

$checkStmt = mysqli_prepare(
    $conn,
    $checkSql
);

mysqli_stmt_bind_param(
    $checkStmt,
    "i",
    $student_id
);

mysqli_stmt_execute($checkStmt);

$checkResult =
    mysqli_stmt_get_result($checkStmt);

$existingFee =
    mysqli_fetch_assoc($checkResult);

mysqli_stmt_close($checkStmt);

//  Prevent Duplicate Active Fee

if ($existingFee) {

    echo json_encode([
        "status" => false,
        "message" => "This student already has an active fee record",
        "existing_fee" => [
            "fee_id" => intval($existingFee["id"]),
            "total_fee" => floatval($existingFee["total_fee"]),
            "paid_fee" => floatval($existingFee["paid_fee"]),
            "due_fee" => floatval($existingFee["due_fee"]),
            "status" => $existingFee["status"]
        ]
    ]);

    exit;
}

// New Fee

$paid_fee = 0;
$due_fee = $total_fee;
$status = "Pending";

// Insert Fee

$sql = "
    INSERT INTO fees
    (
        student_id,
        total_fee,
        paid_fee,
        due_fee,
        payment_date,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Fee query preparation failed"
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "idddss",
    $student_id,
    $total_fee,
    $paid_fee,
    $due_fee,
    $payment_date,
    $status
);

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => false,
        "message" => "Failed to add fee"
    ]);

    exit;
}

$fee_id =
    mysqli_insert_id($conn);

mysqli_stmt_close($stmt);

// Success

echo json_encode([

    "status" => true,

    "message" =>
        "Fee added successfully",

    "fee" => [

        "id" =>
            $fee_id,

        "student_id" =>
            $student_id,

        "total_fee" =>
            $total_fee,

        "paid_fee" =>
            0,

        "due_fee" =>
            $due_fee,

        "payment_date" =>
            $payment_date,

        "status" =>
            "Pending"
    ]

]);

?>