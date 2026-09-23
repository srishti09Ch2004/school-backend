<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

include("../../config/db.php");

// OPTIONS Request

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

// Only GET Request Allowed

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}
/*

| Fetch All Fee Records
| Admin ko:
| Pending + Partial + Paid
| sabhi fee records dikhne chahiye.
|
*/

$sql = "
    SELECT
        f.id,
        f.student_id,

        u.full_name,
        u.email,

        s.admission_no,
        s.class,
        s.section,
        s.roll_no,

        f.total_fee,
        f.paid_fee,
        f.due_fee,
        f.payment_date,
        f.status

    FROM fees f

    INNER JOIN students s
        ON f.student_id = s.id

    INNER JOIN users u
        ON s.user_id = u.id

    ORDER BY f.id DESC
";

$result = mysqli_query($conn, $sql);


if (!$result) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch fee records",
        "error" => mysqli_error($conn)
    ]);

    exit;
}
// Prepare Fee Data

$fees = [];

$totalFee = 0;
$totalPaid = 0;
$totalDue = 0;


while ($row = mysqli_fetch_assoc($result)) {

    $total = floatval($row["total_fee"]);
    $paid  = floatval($row["paid_fee"]);
    $due   = floatval($row["due_fee"]);

// Fee Record

    $fees[] = [

        "id" => intval($row["id"]),

        "student_id" => intval($row["student_id"]),


        /*
        | Student Information
        */

        "full_name" => $row["full_name"],

        "student_name" => $row["full_name"],

        "email" => $row["email"],

        "admission_no" => $row["admission_no"],

        "class" => $row["class"],

        "section" => $row["section"],

        "roll_no" => $row["roll_no"],

        /*
        | Fee Information
        */

        "total_fee" => $total,

        "paid_fee" => $paid,

        "due_fee" => $due,

        "payment_date" => $row["payment_date"],

        "status" => $row["status"]
    ];

// Admin Summary

    $totalFee += $total;

    $totalPaid += $paid;

    $totalDue += $due;
}


/*
| Today's Collection
|
| Today's Collection fees table se calculate nahi karna hai.
|
| Actual payments fee_payments table mein stored hain.
|
*/

$today = date("Y-m-d");


$todaySql = "
    SELECT
        COALESCE(SUM(amount), 0) AS today_collection

    FROM fee_payments

    WHERE payment_date = ?
";


$todayStmt = mysqli_prepare($conn, $todaySql);


$todayCollection = 0;


if ($todayStmt) {

    mysqli_stmt_bind_param(
        $todayStmt,
        "s",
        $today
    );


    mysqli_stmt_execute($todayStmt);


    $todayResult = mysqli_stmt_get_result(
        $todayStmt
    );


    if ($todayResult) {

        $todayRow = mysqli_fetch_assoc(
            $todayResult
        );


        $todayCollection = floatval(
            $todayRow["today_collection"] ?? 0
        );
    }


    mysqli_stmt_close($todayStmt);
}

// Final Response

echo json_encode([

    "status" => true,

    "message" => "Fee records fetched successfully",

// Summary

    "summary" => [

        "total_fee" => $totalFee,

        "total_paid" => $totalPaid,

        "total_due" => $totalDue,

        "today_collection" => $todayCollection,

        "total_records" => count($fees)
    ],

// Fee Records

    "data" => $fees
]);

?>