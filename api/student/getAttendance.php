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


// Get logged-in user ID

$user_id = intval($_GET["user_id"] ?? 0);


// Get selected month

$month = $_GET["month"] ?? date("Y-m");


//  Validate user ID

if ($user_id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid user ID"
    ]);

    exit;
}


// Validate month format

if (!preg_match("/^\d{4}-\d{2}$/", $month)) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid month format"
    ]);

    exit;
}


// Create month range


$monthStart = $month . "-01";

$monthEnd = date(
    "Y-m-t",
    strtotime($monthStart)
);


// | Find student using users.id -> students.user_id

$studentSql = "
    SELECT
        s.id AS student_id,
        s.user_id,
        s.admission_no,
        s.class,
        s.section,
        s.roll_no,
        u.full_name,
        u.email

    FROM students s

    INNER JOIN users u
        ON s.user_id = u.id

    WHERE s.user_id = ?

    LIMIT 1
";


$studentStmt = mysqli_prepare(
    $conn,
    $studentSql
);


if (!$studentStmt) {

    echo json_encode([
        "status" => false,
        "message" => mysqli_error($conn)
    ]);

    exit;
}


mysqli_stmt_bind_param(
    $studentStmt,
    "i",
    $user_id
);


mysqli_stmt_execute(
    $studentStmt
);


$studentResult =
    mysqli_stmt_get_result(
        $studentStmt
    );


if (
    !$studentResult ||
    mysqli_num_rows($studentResult) === 0
) {

    echo json_encode([
        "status" => false,
        "message" => "Student record not found"
    ]);

    exit;
}


$student =
    mysqli_fetch_assoc(
        $studentResult
    );


$student_id =
    intval(
        $student["student_id"]
    );

// Get attendance for selected month only

$attendanceSql = "
    SELECT
        id,
        student_id,
        teacher_id,
        attendance_date,
        status,
        attendance_type,
        created_at

    FROM attendance

    WHERE student_id = ?

    AND attendance_date BETWEEN ? AND ?

    ORDER BY
        attendance_date DESC,
        id DESC
";


$attendanceStmt = mysqli_prepare(
    $conn,
    $attendanceSql
);


if (!$attendanceStmt) {

    echo json_encode([
        "status" => false,
        "message" => mysqli_error($conn)
    ]);

    exit;
}


mysqli_stmt_bind_param(
    $attendanceStmt,
    "iss",
    $student_id,
    $monthStart,
    $monthEnd
);


mysqli_stmt_execute(
    $attendanceStmt
);


$attendanceResult =
    mysqli_stmt_get_result(
        $attendanceStmt
    );


$attendance = [];

$present = 0;
$absent = 0;
$leave = 0;


while (
    $row =
        mysqli_fetch_assoc(
            $attendanceResult
        )
) {

    $status =
        $row["status"];


    // count monthly attendance

    if ($status === "Present") {

        $present++;
    }


    if ($status === "Absent") {

        $absent++;
    }


    if ($status === "Leave") {

        $leave++;
    }


    // attendance record

    $attendance[] = [

        "id" =>
            intval(
                $row["id"]
            ),

        "student_id" =>
            intval(
                $row["student_id"]
            ),

        "teacher_id" =>
            $row["teacher_id"]
                ? intval(
                    $row["teacher_id"]
                )
                : null,

        "attendance_date" =>
            $row["attendance_date"],

        "status" =>
            $status,

        "attendance_type" =>
            $row["attendance_type"]
                ?? "",

        "created_at" =>
            $row["created_at"]
    ];
}


// total classes for selected month

$total =
    count(
        $attendance
    );


// monthly attendance record percentage

$percentage =
    $total > 0
        ? round(
            ($present / $total) * 100
        )
        : 0;


// final response

echo json_encode([

    "status" => true,

    "message" =>
        "Student monthly attendance fetched successfully",

    "month" =>
        $month,

    "month_start" =>
        $monthStart,

    "month_end" =>
        $monthEnd,


    /* Student information    */

    "student" => [

        "student_id" =>
            $student_id,

        "user_id" =>
            intval(
                $student["user_id"]
            ),

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


    /* Monthly summary */

    "summary" => [

        "total" =>
            $total,

        "present" =>
            $present,

        "absent" =>
            $absent,

        "leave" =>
            $leave,

        "percentage" =>
            $percentage
    ],


    /* Monthly attendance history    */

    "attendance" =>
        $attendance
]);



mysqli_stmt_close(
    $studentStmt
);

mysqli_stmt_close(
    $attendanceStmt
);

?>