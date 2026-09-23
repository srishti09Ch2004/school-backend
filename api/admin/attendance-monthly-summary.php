<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
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

$month = $_GET["month"] ?? date("Y-m");


// Validate YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid month format. Use YYYY-MM"
    ]);
    exit;
}

$monthStart = $month . "-01";
$monthEnd = date(
    "Y-m-t",
    strtotime($monthStart)
);


/*
|--------------------------------------------------------------------------
| STUDENT MONTHLY SUMMARY
|--------------------------------------------------------------------------
*/

$studentSql = "
    SELECT
        COALESCE(SUM(status = 'Present'), 0) AS present,
        COALESCE(SUM(status = 'Absent'), 0) AS absent,
        COALESCE(SUM(status = 'Leave'), 0) AS leave_count,
        COUNT(*) AS total
    FROM attendance
    WHERE attendance_date BETWEEN ? AND ?
      AND student_id IS NOT NULL
";

$stmt = mysqli_prepare($conn, $studentSql);

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $monthStart,
    $monthEnd
);

mysqli_stmt_execute($stmt);

$studentResult = mysqli_stmt_get_result($stmt);

$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| TEACHER MONTHLY SUMMARY
|--------------------------------------------------------------------------
*/

$teacherSql = "
    SELECT
        COALESCE(SUM(status = 'Present'), 0) AS present,
        COALESCE(SUM(status = 'Absent'), 0) AS absent,
        COALESCE(SUM(status = 'Leave'), 0) AS leave_count,
        COUNT(*) AS total
    FROM teacher_attendance
    WHERE attendance_date BETWEEN ? AND ?
      AND teacher_id IS NOT NULL
";

$stmt = mysqli_prepare($conn, $teacherSql);

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $monthStart,
    $monthEnd
);

mysqli_stmt_execute($stmt);

$teacherResult = mysqli_stmt_get_result($stmt);

$teacher = mysqli_fetch_assoc($teacherResult);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => true,

    "month" => $month,
    "month_start" => $monthStart,
    "month_end" => $monthEnd,

    "students" => [
        "present" => intval($student["present"] ?? 0),
        "absent" => intval($student["absent"] ?? 0),
        "leave" => intval($student["leave_count"] ?? 0),
        "total" => intval($student["total"] ?? 0)
    ],

    "teachers" => [
        "present" => intval($teacher["present"] ?? 0),
        "absent" => intval($teacher["absent"] ?? 0),
        "leave" => intval($teacher["leave_count"] ?? 0),
        "total" => intval($teacher["total"] ?? 0)
    ]
]);

?>