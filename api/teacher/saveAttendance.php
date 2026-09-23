<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

ob_start();
ini_set("display_errors", 0);

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("Invalid JSON data");
    }

    /*
    |--------------------------------------------------------------------------
    | Frontend sends users.id
    |--------------------------------------------------------------------------
    */
    $teacher_user_id = isset($data["teacher_id"])
        ? (int)$data["teacher_id"]
        : 0;

    $attendance_date = trim($data["attendance_date"] ?? "");
    $attendance = $data["attendance"] ?? [];

    if ($teacher_user_id <= 0) {
        throw new Exception("Invalid teacher ID");
    }

    if (!$attendance_date) {
        throw new Exception("Attendance date is required");
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
        throw new Exception("Invalid attendance date format");
    }

    if (!is_array($attendance) || count($attendance) === 0) {
        throw new Exception("Attendance data is required");
    }

    /*
    |--------------------------------------------------------------------------
    | Convert users.id -> teachers.id
    |--------------------------------------------------------------------------
    */

    $teacherStmt = $conn->prepare("
        SELECT t.id AS teacher_id
        FROM teachers t
        INNER JOIN users u
            ON t.user_id = u.id
        WHERE u.id = ?
          AND u.role = 'teacher'
        LIMIT 1
    ");

    if (!$teacherStmt) {
        throw new Exception("Teacher query preparation failed");
    }

    $teacherStmt->bind_param("i", $teacher_user_id);
    $teacherStmt->execute();

    $teacherResult = $teacherStmt->get_result();

    if ($teacherResult->num_rows === 0) {
        throw new Exception("Teacher account not found");
    }

    $teacherRow = $teacherResult->fetch_assoc();

    $marked_by_teacher_id = (int)$teacherRow["teacher_id"];

    $teacherStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Attendance query
    |--------------------------------------------------------------------------
    */

    $attendanceStmt = $conn->prepare("
        INSERT INTO attendance
        (
            student_id,
            marked_by_teacher_id,
            attendance_date,
            status,
            attendance_type
        )
        VALUES (?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE
            marked_by_teacher_id = VALUES(marked_by_teacher_id),
            status = VALUES(status),
            attendance_type = VALUES(attendance_type)
    ");

    if (!$attendanceStmt) {
        throw new Exception("Attendance query preparation failed");
    }

    foreach ($attendance as $record) {

        $student_id = isset($record["student_id"])
            ? (int)$record["student_id"]
            : 0;

        $status = trim($record["status"] ?? "");
        $attendance_type = trim($record["attendance_type"] ?? "Manual");

        if ($student_id <= 0) {
            throw new Exception("Invalid student ID");
        }

        if (!in_array($status, ["Present", "Absent", "Leave"], true)) {
            throw new Exception(
                "Invalid attendance status for student ID: " . $student_id
            );
        }

        if (!in_array(
            $attendance_type,
            ["Manual", "Face", "Fingerprint"],
            true
        )) {
            $attendance_type = "Manual";
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure student is active
        |--------------------------------------------------------------------------
        */

        $studentCheck = $conn->prepare("
            SELECT id
            FROM students
            WHERE id = ?
              AND status = 'Active'
            LIMIT 1
        ");

        if (!$studentCheck) {
            throw new Exception("Student validation failed");
        }

        $studentCheck->bind_param("i", $student_id);
        $studentCheck->execute();

        $studentResult = $studentCheck->get_result();

        if ($studentResult->num_rows === 0) {
            $studentCheck->close();

            throw new Exception(
                "Active student not found: " . $student_id
            );
        }

        $studentCheck->close();

        /*
        |--------------------------------------------------------------------------
        | Save / Update attendance
        |--------------------------------------------------------------------------
        */

        $attendanceStmt->bind_param(
            "iisss",
            $student_id,
            $marked_by_teacher_id,
            $attendance_date,
            $status,
            $attendance_type
        );

        if (!$attendanceStmt->execute()) {
            throw new Exception(
                "Failed to save attendance: " .
                $attendanceStmt->error
            );
        }
    }

    $attendanceStmt->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Student attendance saved successfully",
        "teacher_user_id" => $teacher_user_id,
        "marked_by_teacher_id" => $marked_by_teacher_id,
        "attendance_date" => $attendance_date
    ]);

} catch (Exception $e) {

    if (isset($conn) && $conn->connect_errno === 0) {
        try {
            $conn->rollback();
        } catch (Exception $ignored) {
        }
    }

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

ob_end_flush();