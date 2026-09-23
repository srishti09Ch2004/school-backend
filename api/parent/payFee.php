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
        "message" => "Invalid JSON data"
    ]);
    exit;
}

$user_id = intval($data["user_id"] ?? 0);
$fee_id = intval($data["fee_id"] ?? 0);
$amount = floatval($data["amount"] ?? 0);

$payment_method = trim(
    $data["payment_method"] ?? "UPI"
);

$transaction_id = !empty($data["transaction_id"])
    ? trim($data["transaction_id"])
    : null;

$remarks = !empty($data["remarks"])
    ? trim($data["remarks"])
    : null;

$payment_date = date("Y-m-d");

if ($user_id <= 0 || $fee_id <= 0 || $amount <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "user_id, fee_id and valid amount are required"
    ]);

    exit;
}

// Find Parent

$parentSql = "
    SELECT student_id
    FROM parents
    WHERE user_id = ?
    LIMIT 1
";

$parentStmt = mysqli_prepare(
    $conn,
    $parentSql
);

mysqli_stmt_bind_param(
    $parentStmt,
    "i",
    $user_id
);

mysqli_stmt_execute($parentStmt);

$parentResult =
    mysqli_stmt_get_result($parentStmt);

$parent =
    mysqli_fetch_assoc($parentResult);

mysqli_stmt_close($parentStmt);

if (!$parent) {

    echo json_encode([
        "status" => false,
        "message" => "Parent not found"
    ]);

    exit;
}

$student_id =
    intval($parent["student_id"]);

// Find Fee

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

mysqli_stmt_bind_param(
    $feeStmt,
    "ii",
    $fee_id,
    $student_id
);

mysqli_stmt_execute($feeStmt);

$feeResult =
    mysqli_stmt_get_result($feeStmt);

$fee =
    mysqli_fetch_assoc($feeResult);

mysqli_stmt_close($feeStmt);

if (!$fee) {

    echo json_encode([
        "status" => false,
        "message" => "Fee record not found"
    ]);

    exit;
}

// Check Due

$currentDue =
    floatval($fee["due_fee"]);

if ($currentDue <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "No due fee available"
    ]);

    exit;
}

if ($amount > $currentDue) {

    echo json_encode([
        "status" => false,
        "message" => "Payment amount cannot be greater than due fee",
        "due_fee" => $currentDue
    ]);

    exit;
}

// Calculate New Values

$currentPaid =
    floatval($fee["paid_fee"]);

$totalFee =
    floatval($fee["total_fee"]);

$newPaid =
    $currentPaid + $amount;

$newDue =
    $totalFee - $newPaid;

if ($newDue < 0) {
    $newDue = 0;
}

$newStatus =
    ($newDue <= 0)
        ? "Paid"
        : "Pending";


// Receipt Number

$receiptNo =
    "REC-" . date("YmdHis") . rand(100, 999);


// Transaction

mysqli_begin_transaction($conn);

try {

    /*
    | Insert Payment History
    */

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

    $paymentStmt =
        mysqli_prepare(
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
        $receiptNo,
        $remarks
    );

    if (!mysqli_stmt_execute($paymentStmt)) {

        throw new Exception(
            "Payment history insert failed"
        );
    }

    $paymentId =
        mysqli_insert_id($conn);

    mysqli_stmt_close($paymentStmt);


    /*
    | Update Fee
    */

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

    $updateStmt =
        mysqli_prepare(
            $conn,
            $updateSql
        );

    mysqli_stmt_bind_param(
        $updateStmt,
        "ddssii",
        $newPaid,
        $newDue,
        $payment_date,
        $newStatus,
        $fee_id,
        $student_id
    );

    if (!mysqli_stmt_execute($updateStmt)) {

        throw new Exception(
            "Fee update failed"
        );
    }

    mysqli_stmt_close($updateStmt);


    /*
    | Commit
    */

    mysqli_commit($conn);


    echo json_encode([

        "status" => true,

        "message" =>
            "Fee payment successful",

        "payment_id" =>
            $paymentId,

        "receipt_no" =>
            $receiptNo,

        "payment" => [

            "amount" =>
                $amount,

            "payment_date" =>
                $payment_date,

            "payment_method" =>
                $payment_method,

            "transaction_id" =>
                $transaction_id
        ],

        "fee" => [

            "fee_id" =>
                $fee_id,

            "student_id" =>
                $student_id,

            "total_fee" =>
                $totalFee,

            "paid_fee" =>
                $newPaid,

            "due_fee" =>
                $newDue,

            "status" =>
                $newStatus
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