<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        http_response_code(405);

        echo json_encode([
            "status" => false,
            "message" => "Only GET method is allowed"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Frontend sends users.id
    |--------------------------------------------------------------------------
    */

    $user_id = isset($_GET["user_id"])
        ? intval($_GET["user_id"])
        : 0;

    $attendance_date = isset($_GET["attendance_date"])
        ? trim($_GET["attendance_date"])
        : "";

    if ($user_id <= 0) {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid user ID"
        ]);

        exit;
    }

    if ($attendance_date === "") {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Attendance date is required"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate date
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        "Y-m-d",
        $attendance_date
    );

    if (
        !$dateObject ||
        $dateObject->format("Y-m-d") !== $attendance_date
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid attendance date. Use YYYY-MM-DD"
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify teacher account
    |--------------------------------------------------------------------------
    */

    $teacherStmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.full_name,
            t.id AS teacher_id

        FROM users u

        INNER JOIN teachers t
            ON t.user_id = u.id

        WHERE u.id = ?
          AND u.role = 'teacher'

        LIMIT 1
    ");

    if (!$teacherStmt) {
        throw new Exception(
            "Teacher validation query failed: " .
            $conn->error
        );
    }

    $teacherStmt->bind_param(
        "i",
        $user_id
    );

    $teacherStmt->execute();

    $teacherResult =
        $teacherStmt->get_result();

    if ($teacherResult->num_rows === 0) {

        $teacherStmt->close();

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "Teacher account not found"
        ]);

        exit;
    }

    $teacher =
        $teacherResult->fetch_assoc();

    $teacherStmt->close();


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | teacher_attendance.teacher_id currently stores USERS.ID
    | because saveTeacherAttendance.php receives users.id.
    |
    */

    $attendanceStmt = $conn->prepare("
        SELECT
            teacher_id,
            attendance_date,
            status,
            attendance_type

        FROM teacher_attendance

        WHERE teacher_id = ?
          AND attendance_date = ?

        LIMIT 1
    ");

    if (!$attendanceStmt) {
        throw new Exception(
            "Attendance query preparation failed: " .
            $conn->error
        );
    }

    $attendanceStmt->bind_param(
        "is",
        $user_id,
        $attendance_date
    );

    $attendanceStmt->execute();

    $attendanceResult =
        $attendanceStmt->get_result();

    if ($attendanceResult->num_rows > 0) {

        $attendance =
            $attendanceResult->fetch_assoc();

        $attendanceStmt->close();

        echo json_encode([
            "status" => true,
            "marked" => true,

            "teacher_id" =>
                (int)$user_id,

            "teacher_name" =>
                $teacher["full_name"],

            "attendance_date" =>
                $attendance["attendance_date"],

            "attendance_status" =>
                $attendance["status"],

            "attendance_type" =>
                $attendance["attendance_type"]
        ]);

        exit;
    }

    $attendanceStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Attendance not marked for this date
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "status" => true,
        "marked" => false,

        "teacher_id" =>
            (int)$user_id,

        "teacher_name" =>
            $teacher["full_name"],

        "attendance_date" =>
            $attendance_date,

        "attendance_status" => null,

        "attendance_type" => null
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Failed to check teacher attendance",
        "error" =>
            $e->getMessage()
    ]);
}
?>