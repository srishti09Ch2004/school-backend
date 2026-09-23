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

$response = [
    "status" => false,
    "data" => [
        "students" => 0,
        "revenue" => 0,
        "attendance" => 0,
        "results" => 0
    ]
];

/*
|--------------------------------------------------------------------------
| Total Students
|--------------------------------------------------------------------------
*/

$studentQuery = "
    SELECT COUNT(*) AS total_students
    FROM students
";

$studentResult = $conn->query($studentQuery);

if ($studentResult) {
    $studentRow = $studentResult->fetch_assoc();
    $response["data"]["students"] = (int)$studentRow["total_students"];
}


/*
|--------------------------------------------------------------------------
| Total Revenue
|--------------------------------------------------------------------------
| paid_fee = actual collected amount
|--------------------------------------------------------------------------
*/

$feeQuery = "
    SELECT COALESCE(SUM(paid_fee), 0) AS total_revenue
    FROM fees
";

$feeResult = $conn->query($feeQuery);

if ($feeResult) {
    $feeRow = $feeResult->fetch_assoc();
    $response["data"]["revenue"] = (float)$feeRow["total_revenue"];
}


/*
|--------------------------------------------------------------------------
| Attendance Percentage
|--------------------------------------------------------------------------
| Present / Total Attendance Records * 100
|--------------------------------------------------------------------------
*/

$attendanceQuery = "
    SELECT
        COUNT(*) AS total_records,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_records
    FROM attendance
";

$attendanceResult = $conn->query($attendanceQuery);

if ($attendanceResult) {

    $attendanceRow = $attendanceResult->fetch_assoc();

    $totalRecords = (int)$attendanceRow["total_records"];
    $presentRecords = (int)$attendanceRow["present_records"];

    if ($totalRecords > 0) {
        $response["data"]["attendance"] =
            round(($presentRecords / $totalRecords) * 100, 2);
    }
}


/*
|--------------------------------------------------------------------------
| Result Percentage
|--------------------------------------------------------------------------
| marks are stored as marks out of 100
|--------------------------------------------------------------------------
*/

$resultQuery = "
    SELECT COALESCE(AVG(marks), 0) AS average_marks
    FROM results
";

$resultResult = $conn->query($resultQuery);

if ($resultResult) {
    $resultRow = $resultResult->fetch_assoc();

    $response["data"]["results"] =
        round((float)$resultRow["average_marks"], 2);
}


$response["status"] = true;

echo json_encode($response);

$conn->close();

?>