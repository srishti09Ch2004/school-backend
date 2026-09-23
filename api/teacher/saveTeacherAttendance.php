<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

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

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON data"
    ]);
    exit;
}

// Frontend sends USERS.ID

$teacher_id = (int)($data["teacher_id"] ?? 0);

$attendance_date = trim(
    $data["attendance_date"] ?? date("Y-m-d")
);

$status = trim(
    $data["status"] ?? "Present"
);

$attendance_type = trim(
    $data["attendance_type"] ?? "Face"
);

// Basic validation

if ($teacher_id <= 0) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid teacher ID"
    ]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid attendance date"
    ]);
    exit;
}

if (!in_array(
    $status,
    ["Present", "Absent", "Leave"],
    true
)) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid attendance status"
    ]);
    exit;
}


/*
| Teacher attendance type

| Teacher's own attendance can be:
| Face / Fingerprint
|
| Manual is also accepted because the frontend may currently
| send Manual. We keep it safe for now.
|
*/

if (!in_array(
    $attendance_type,
    ["Manual", "Face", "Fingerprint"],
    true
)) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid attendance type"
    ]);
    exit;
}


//  Verify teacher

$teacherCheck = mysqli_prepare(
    $conn,
    "
    SELECT id, full_name, role
    FROM users
    WHERE id = ?
      AND role = 'teacher'
    LIMIT 1
    "
);

if (!$teacherCheck) {
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Teacher validation query failed"
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $teacherCheck,
    "i",
    $teacher_id
);

mysqli_stmt_execute($teacherCheck);

$teacherResult = mysqli_stmt_get_result(
    $teacherCheck
);

if (!$teacherResult || mysqli_num_rows($teacherResult) === 0) {

    mysqli_stmt_close($teacherCheck);

    http_response_code(404);

    echo json_encode([
        "status" => false,
        "message" => "Teacher account not found"
    ]);
    exit;
}

$teacher = mysqli_fetch_assoc($teacherResult);

mysqli_stmt_close($teacherCheck);

/*
| Check whether teacher has already marked attendance for this date
*/

$existingStmt = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        status,
        attendance_type
    FROM teacher_attendance
    WHERE teacher_id = ?
      AND attendance_date = ?
    LIMIT 1
    "
);

if (!$existingStmt) {
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Unable to check existing attendance",
        "error" =>
            mysqli_error($conn)
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $existingStmt,
    "is",
    $teacher_id,
    $attendance_date
);

mysqli_stmt_execute($existingStmt);

$existingResult =
    mysqli_stmt_get_result($existingStmt);

if (
    $existingResult &&
    mysqli_num_rows($existingResult) > 0
) {

    $existing =
        mysqli_fetch_assoc($existingResult);

    mysqli_stmt_close($existingStmt);

    echo json_encode([
        "status" => true,
        "already_marked" => true,
        "message" =>
            "Your attendance is already marked for this date.",
        "teacher_id" =>
            $teacher_id,
        "teacher_name" =>
            $teacher["full_name"],
        "attendance_date" =>
            $attendance_date,
        "attendance_status" =>
            $existing["status"],
        "attendance_type" =>
            $existing["attendance_type"]
    ]);

    exit;
}

mysqli_stmt_close($existingStmt);


/*
| Insert new teacher attendance
*/

$sql = "
    INSERT INTO teacher_attendance
    (
        teacher_id,
        attendance_date,
        status,
        attendance_type
    )
    VALUES (?, ?, ?, ?)
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Attendance query preparation failed",
        "error" =>
            mysqli_error($conn)
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "isss",
    $teacher_id,
    $attendance_date,
    $status,
    $attendance_type
);

if (!mysqli_stmt_execute($stmt)) {

    $error =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Failed to save teacher attendance",
        "error" =>
            $error
    ]);

    exit;
}

mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Attendance query preparation failed",
        "error" => mysqli_error($conn)
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "isss",
    $teacher_id,
    $attendance_date,
    $status,
    $attendance_type
);

if (!mysqli_stmt_execute($stmt)) {

    $error = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to save teacher attendance",
        "error" => $error
    ]);
    exit;
}

mysqli_stmt_close($stmt);

//  Success

echo json_encode([
    "status" => true,
    "message" => "Teacher attendance saved successfully",
    "teacher_id" => $teacher_id,
    "teacher_name" => $teacher["full_name"],
    "attendance_date" => $attendance_date,
    "attendance_status" => $status,
    "attendance_type" => $attendance_type
]);

?>