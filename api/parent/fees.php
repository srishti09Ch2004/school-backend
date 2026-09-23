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
        "message" => "Parent user id is required"
    ]);

    exit;
}

// Parent + Student

$sql = "
    SELECT
        p.id AS parent_id,
        p.user_id AS parent_user_id,
        p.student_id,

        s.user_id AS student_user_id,
        s.admission_no,
        s.class,
        s.section,
        s.roll_no,

        u.full_name,
        u.email

    FROM parents p

    INNER JOIN students s
        ON p.student_id = s.id

    INNER JOIN users u
        ON s.user_id = u.id

    WHERE p.user_id = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Student query preparation failed"
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$student =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$student) {

    echo json_encode([
        "status" => false,
        "message" => "No student associated with this parent"
    ]);

    exit;
}

$student_id =
    intval($student["student_id"]);

//  Fee Records

$feeSql = "
    SELECT
        id,
        student_id,
        total_fee,
        paid_fee,
        due_fee,
        payment_date,
        status
    FROM fees
    WHERE student_id = ?
    ORDER BY id DESC
";

$feeStmt =
    mysqli_prepare(
        $conn,
        $feeSql
    );

mysqli_stmt_bind_param(
    $feeStmt,
    "i",
    $student_id
);

mysqli_stmt_execute($feeStmt);

$feeResult =
    mysqli_stmt_get_result($feeStmt);

$fees = [];

$totalFee = 0;
$totalPaid = 0;
$totalDue = 0;

while ($row = mysqli_fetch_assoc($feeResult)) {

    $total =
        floatval($row["total_fee"]);

    $paid =
        floatval($row["paid_fee"]);

    $due =
        floatval($row["due_fee"]);

    $totalFee += $total;
    $totalPaid += $paid;
    $totalDue += $due;

    $fees[] = [

        "id" =>
            intval($row["id"]),

        "student_id" =>
            intval($row["student_id"]),

        "total_fee" =>
            $total,

        "paid_fee" =>
            $paid,

        "due_fee" =>
            $due,

        "payment_date" =>
            $row["payment_date"],

        "status" =>
            $row["status"]
    ];
}

mysqli_stmt_close($feeStmt);

// Payment History

$paymentSql = "
    SELECT
        id,
        fee_id,
        student_id,
        amount,
        payment_date,
        payment_method,
        transaction_id,
        receipt_no,
        remarks,
        created_at
    FROM fee_payments
    WHERE student_id = ?
    ORDER BY payment_date DESC, id DESC
";

$paymentStmt =
    mysqli_prepare(
        $conn,
        $paymentSql
    );

$payments = [];

if ($paymentStmt) {

    mysqli_stmt_bind_param(
        $paymentStmt,
        "i",
        $student_id
    );

    mysqli_stmt_execute(
        $paymentStmt
    );

    $paymentResult =
        mysqli_stmt_get_result(
            $paymentStmt
        );

    while (
        $row =
        mysqli_fetch_assoc($paymentResult)
    ) {

        $payments[] = [

            "id" =>
                intval($row["id"]),

            "fee_id" =>
                intval($row["fee_id"]),

            "student_id" =>
                intval($row["student_id"]),

            "amount" =>
                floatval($row["amount"]),

            "payment_date" =>
                $row["payment_date"],

            "payment_method" =>
                $row["payment_method"],

            "transaction_id" =>
                $row["transaction_id"],

            "receipt_no" =>
                $row["receipt_no"],

            "remarks" =>
                $row["remarks"],

            "created_at" =>
                $row["created_at"]
        ];
    }

    mysqli_stmt_close($paymentStmt);
}

// Status

if ($totalFee <= 0) {

    $status = "No Fee Record";

} elseif ($totalDue <= 0) {

    $status = "Paid";

} else {

    $status = "Pending";
}

// Percentage

$percentage = 0;

if ($totalFee > 0) {

    $percentage =
        ($totalPaid / $totalFee) * 100;
}

// Final Response

echo json_encode([

    "status" => true,

    "message" =>
        "Parent fee details fetched successfully",

    "student" => [

        "id" =>
            intval($student["student_id"]),

        "user_id" =>
            intval($student["student_user_id"]),

        "name" =>
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
            $totalFee,

        "total_paid" =>
            $totalPaid,

        "total_due" =>
            $totalDue,

        "status" =>
            $status,

        "payment_percentage" =>
            round($percentage, 2)
    ],

    "fees" =>
        $fees,

    "payments" =>
        $payments

]);

?>