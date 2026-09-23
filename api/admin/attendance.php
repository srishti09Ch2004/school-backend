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

$date = $_GET["date"] ?? date("Y-m-d");
$type = $_GET["type"] ?? "students";

/* MONTH RANGE| */

$monthStart = date("Y-m-01", strtotime($date));
$monthEnd = date("Y-m-t", strtotime($date));


/* STUDENT ATTENDANCE */

if ($type === "students") {

    $sql = "
        SELECT
            s.id,
            s.id AS student_id,
            u.full_name AS name,
            s.admission_no,
            s.roll_no,
            s.class,
            s.section,

            a.attendance_date,
            a.status,
            a.attendance_type,
            a.created_at,

            (
                SELECT COUNT(*)
                FROM attendance ma
                WHERE ma.student_id = s.id
                AND ma.status = 'Present'
                AND ma.attendance_date BETWEEN ? AND ?
            ) AS total_present

        FROM students s

        INNER JOIN users u
            ON s.user_id = u.id

        LEFT JOIN attendance a
            ON a.student_id = s.id
            AND a.attendance_date = ?

        ORDER BY
            CAST(s.roll_no AS UNSIGNED) ASC,
            u.full_name ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $monthStart,
        $monthEnd,
        $date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $attendance = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $attendance[] = [
            "id" => intval($row["id"]),
            "student_id" => intval($row["student_id"]),
            "name" => $row["name"],
            "admission_no" => $row["admission_no"],
            "roll_no" => $row["roll_no"],
            "class" => $row["class"],
            "section" => $row["section"],

            "attendance_date" => $row["attendance_date"],
            "status" => $row["status"] ?? "",
            "attendance_type" => $row["attendance_type"] ?? "",
            "created_at" => $row["created_at"] ?? "",

            "total_present" => intval(
                $row["total_present"] ?? 0
            )
        ];
    }

    echo json_encode([
        "status" => true,
        "type" => "students",
        "date" => $date,
        "month" => date("F Y", strtotime($date)),
        "month_start" => $monthStart,
        "month_end" => $monthEnd,
        "data" => $attendance
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


/*
|--------------------------------------------------------------------------
| TEACHER ATTENDANCE
|--------------------------------------------------------------------------
*/

if ($type === "teachers") {

    $sql = "
        SELECT
            u.id,
            u.id AS teacher_id,
            u.full_name AS name,
            u.email,

            ta.attendance_date,
            ta.status,
            ta.attendance_type,
            ta.created_at,

            (
                SELECT COUNT(*)
                FROM teacher_attendance mta
                WHERE mta.teacher_id = u.id
                AND mta.status = 'Present'
                AND mta.attendance_date BETWEEN ? AND ?
            ) AS total_present

        FROM users u

        LEFT JOIN teacher_attendance ta
            ON ta.teacher_id = u.id
            AND ta.attendance_date = ?

        WHERE u.role = 'teacher'

        ORDER BY u.full_name ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $monthStart,
        $monthEnd,
        $date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $attendance = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $attendance[] = [
            "id" => intval($row["id"]),
            "teacher_id" => intval($row["teacher_id"]),
            "name" => $row["name"],
            "email" => $row["email"],

            "attendance_date" => $row["attendance_date"],
            "status" => $row["status"] ?? "",
            "attendance_type" => $row["attendance_type"] ?? "",
            "created_at" => $row["created_at"] ?? "",

            "total_present" => intval(
                $row["total_present"] ?? 0
            )
        ];
    }

    echo json_encode([
        "status" => true,
        "type" => "teachers",
        "date" => $date,
        "month" => date("F Y", strtotime($date)),
        "month_start" => $monthStart,
        "month_end" => $monthEnd,
        "data" => $attendance
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


echo json_encode([
    "status" => false,
    "message" => "Invalid attendance type"
]);

?>