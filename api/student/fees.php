<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);
    exit;
}

$user_id = intval($_GET["user_id"] ?? 0);

if ($user_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "user_id is required"
    ]);
    exit;
}

// Find Student

$studentSql = "
    SELECT
        s.id,
        u.id AS user_id,
        u.full_name,
        u.email,
        s.admission_no,
        s.class,
        s.section,
        s.roll_no
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
";

$studentStmt = mysqli_prepare($conn, $studentSql);

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
    $user_id
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

$student_id = intval($student["id"]);

// Fetch Fee Records

$feeSql = "
    SELECT
        f.id,
        f.student_id,
        f.total_fee,
        f.paid_fee,
        f.due_fee,
        f.payment_date,
        f.status
    FROM fees f
    WHERE f.student_id = ?
    ORDER BY f.id DESC
";

$feeStmt = mysqli_prepare($conn, $feeSql);

if (!$feeStmt) {
    echo json_encode([
        "status" => false,
        "message" => "Fee query preparation failed"
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $feeStmt,
    "i",
    $student_id
);

mysqli_stmt_execute($feeStmt);

$feeResult =
    mysqli_stmt_get_result($feeStmt);

$fees = [];

$total_fee = 0;
$total_paid = 0;
$total_due = 0;

while ($row = mysqli_fetch_assoc($feeResult)) {

    $row["id"] = intval($row["id"]);
    $row["student_id"] = intval($row["student_id"]);

    $row["total_fee"] = floatval($row["total_fee"]);
    $row["paid_fee"] = floatval($row["paid_fee"]);
    $row["due_fee"] = floatval($row["due_fee"]);

    $fees[] = $row;

    $total_fee += $row["total_fee"];
    $total_paid += $row["paid_fee"];
    $total_due += $row["due_fee"];
}

mysqli_stmt_close($feeStmt);

// Payment History

$paymentSql = "
    SELECT
        fp.id,
        fp.fee_id,
        fp.amount,
        fp.payment_date,
        fp.payment_method,
        fp.transaction_id,
        fp.receipt_no,
        fp.remarks
    FROM fee_payments fp
    WHERE fp.student_id = ?
    ORDER BY fp.payment_date DESC, fp.id DESC
";

$paymentStmt = mysqli_prepare(
    $conn,
    $paymentSql
);

if (!$paymentStmt) {
    echo json_encode([
        "status" => false,
        "message" => "Payment history query preparation failed"
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $paymentStmt,
    "i",
    $student_id
);

mysqli_stmt_execute($paymentStmt);

$paymentResult =
    mysqli_stmt_get_result($paymentStmt);

$payments = [];

while ($row = mysqli_fetch_assoc($paymentResult)) {

    $row["id"] = intval($row["id"]);
    $row["fee_id"] = intval($row["fee_id"]);
    $row["amount"] = floatval($row["amount"]);

    $payments[] = $row;
}

mysqli_stmt_close($paymentStmt);

//  Status

$status = "No Fee Record";

if ($total_fee > 0) {

    $status =
        $total_due <= 0
            ? "Paid"
            : "Pending";
}

// Payment Percentage

$payment_percentage = 0;

if ($total_fee > 0) {

    $payment_percentage =
        round(
            ($total_paid / $total_fee) * 100,
            2
        );
}

if ($payment_percentage > 100) {
    $payment_percentage = 100;
}

// Response

echo json_encode([

    "status" => true,

    "message" =>
        "Student fee details fetched successfully",

    "student" => [

        "id" =>
            $student_id,

        "user_id" =>
            intval($student["user_id"]),

        "name" =>
            $student["full_name"],

        "full_name" =>
            $student["full_name"],

        "email" =>
            $student["email"],

        "admission_no" =>
            $student["admission_no"],

        "class" =>
            $student["class"],

        "section" =>
            $student["section"],

        "roll_no" =>
            $student["roll_no"]
    ],

    "summary" => [

        "total_fee" =>
            $total_fee,

        "total_paid" =>
            $total_paid,

        "total_due" =>
            $total_due,

        "status" =>
            $status,

        "payment_percentage" =>
            $payment_percentage
    ],

    "fees" =>
        $fees,

    "payments" =>
        $payments

]);

?>