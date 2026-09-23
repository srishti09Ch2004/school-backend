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

$fee_id = intval($data["fee_id"] ?? 0);
$student_id = intval($data["student_id"] ?? 0);
$amount = floatval($data["amount"] ?? 0);

$payment_date = !empty($data["payment_date"])
    ? $data["payment_date"]
    : date("Y-m-d");

$payment_method = trim(
    $data["payment_method"] ?? ""
);

$transaction_id = trim(
    $data["transaction_id"] ?? ""
);

$receipt_no = trim(
    $data["receipt_no"] ?? ""
);

$remarks = trim(
    $data["remarks"] ?? ""
);

// Validation

if ($fee_id <= 0 || $student_id <= 0 || $amount <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Fee, student and valid payment amount are required"
    ]);

    exit;
}

// Get Fee

$feeSql = "
    SELECT
        id,
        student_id,
        total_fee,
        paid_fee,
        due_fee,
        status
    FROM fees
    WHERE id = ?
    AND student_id = ?
    LIMIT 1
";

$feeStmt = mysqli_prepare(
    $conn,
    $feeSql
);

if (!$feeStmt) {

    echo json_encode([
        "status" => false,
        "message" => "Fee query preparation failed"
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $feeStmt,
    "ii",
    $fee_id,
    $student_id
);

mysqli_stmt_execute($feeStmt);

$feeResult = mysqli_stmt_get_result($feeStmt);

$fee = mysqli_fetch_assoc($feeResult);

mysqli_stmt_close($feeStmt);

if (!$fee) {

    echo json_encode([
        "status" => false,
        "message" => "Fee record not found"
    ]);

    exit;
}

$total_fee = floatval($fee["total_fee"]);
$paid_fee = floatval($fee["paid_fee"]);
$due_fee = floatval($fee["due_fee"]);
if ($due_fee <= 0 || $fee["status"] === "Paid") {
    echo json_encode([
        "status" => false,
        "message" => "This fee is already fully paid"
    ]);
    exit;
}

// Prevent overpayment

if ($amount > $due_fee) {

    echo json_encode([
        "status" => false,
        "message" => "Payment amount cannot be greater than due fee",
        "due_fee" => $due_fee
    ]);

    exit;
}

// New amounts

$new_paid_fee = $paid_fee + $amount;
$new_due_fee = $total_fee - $new_paid_fee;

if ($new_due_fee < 0) {
    $new_due_fee = 0;
}

if ($new_due_fee <= 0) {
    $new_status = "Paid";
} elseif ($new_paid_fee > 0) {
    $new_status = "Partial";
} else {
    $new_status = "Pending";
}

// Generate receipt if empty

if ($receipt_no === "") {

    $receipt_no =
        "REC-" . str_pad(
            rand(1, 999999),
            6,
            "0",
            STR_PAD_LEFT
        );
}

// Start Transaction

mysqli_begin_transaction($conn);

try {

    // Insert Payment History

    $paymentSql = "
        INSERT INTO fee_payments
        (
            fee_id,
            student_id,
            amount,
            payment_date,
            payment_method,
            transaction_id,
            receipt_no,
            remarks
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $paymentStmt = mysqli_prepare(
        $conn,
        $paymentSql
    );

    if (!$paymentStmt) {
        throw new Exception(
            "Payment query preparation failed"
        );
    }

    mysqli_stmt_bind_param(
        $paymentStmt,
        "iidsssss",
        $fee_id,
        $student_id,
        $amount,
        $payment_date,
        $payment_method,
        $transaction_id,
        $receipt_no,
        $remarks
    );

    if (!mysqli_stmt_execute($paymentStmt)) {

        throw new Exception(
            "Failed to save payment"
        );
    }

    $payment_id =
        mysqli_insert_id($conn);

    mysqli_stmt_close($paymentStmt);

    // Update Fee

    $updateSql = "
        UPDATE fees
        SET
            paid_fee = ?,
            due_fee = ?,
            payment_date = ?,
            status = ?
        WHERE id = ?
        AND student_id = ?
    ";

    $updateStmt = mysqli_prepare(
        $conn,
        $updateSql
    );

    if (!$updateStmt) {
        throw new Exception(
            "Fee update preparation failed"
        );
    }

    mysqli_stmt_bind_param(
        $updateStmt,
        "ddssii",
        $new_paid_fee,
        $new_due_fee,
        $payment_date,
        $new_status,
        $fee_id,
        $student_id
    );

    if (!mysqli_stmt_execute($updateStmt)) {

        throw new Exception(
            "Failed to update fee"
        );
    }

    mysqli_stmt_close($updateStmt);

    // Commit

    mysqli_commit($conn);


    echo json_encode([

        "status" => true,

        "message" =>
            "Fee payment added successfully",

        "payment_id" =>
            $payment_id,

        "receipt_no" =>
            $receipt_no,

        "fee" => [

            "fee_id" =>
                $fee_id,

            "student_id" =>
                $student_id,

            "total_fee" =>
                $total_fee,

            "paid_fee" =>
                $new_paid_fee,

            "due_fee" =>
                $new_due_fee,

            "status" =>
                $new_status,

            "payment_date" =>
                $payment_date
        ]

    ]);

} catch (Exception $e) {

    mysqli_rollback($conn);

    echo json_encode([

        "status" => false,

        "message" =>
            $e->getMessage()

    ]);
}

?>