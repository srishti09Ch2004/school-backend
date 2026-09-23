<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "status" => false,
        "message" => "Only POST request is allowed."
    ]);

    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$student_id = $input["student_id"] ?? null;
$attendance_date = $input["attendance_date"] ?? date("Y-m-d");
$status = $input["status"] ?? null;
$attendance_type = $input["attendance_type"] ?? "Face";


// Validate student

if (!$student_id) {

    echo json_encode([
        "status" => false,
        "message" => "Student ID is required."
    ]);

    exit;
}


// Validate status

$allowed_status = [
    "Present",
    "Absent",
    "Leave"
];

if (!in_array($status, $allowed_status)) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid attendance status."
    ]);

    exit;
}


// Validate attendance type

$allowed_types = [
    "Face",
    "Fingerprint"
];

if (!in_array($attendance_type, $allowed_types)) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid attendance type."
    ]);

    exit;
}


// Check whether student exists

$studentCheck = mysqli_prepare(
    $conn,
    "SELECT id FROM students WHERE id = ? LIMIT 1"
);

mysqli_stmt_bind_param(
    $studentCheck,
    "i",
    $student_id
);

mysqli_stmt_execute($studentCheck);

$studentResult = mysqli_stmt_get_result($studentCheck);

if (mysqli_num_rows($studentResult) === 0) {

    echo json_encode([
        "status" => false,
        "message" => "Student not found."
    ]);

    exit;
}


// Check today's attendance

$check = mysqli_prepare(
    $conn,
    "
    SELECT id
    FROM attendance
    WHERE student_id = ?
    AND attendance_date = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $check,
    "is",
    $student_id,
    $attendance_date
);

mysqli_stmt_execute($check);

$checkResult = mysqli_stmt_get_result($check);


// If already exists → UPDATE

if (mysqli_num_rows($checkResult) > 0) {

    $existing = mysqli_fetch_assoc($checkResult);

    $attendance_id = $existing["id"];

    $update = mysqli_prepare(
        $conn,
        "
        UPDATE attendance
        SET
            status = ?,
            attendance_type = ?
        WHERE id = ?
        "
    );

    mysqli_stmt_bind_param(
        $update,
        "ssi",
        $status,
        $attendance_type,
        $attendance_id
    );

    if (!mysqli_stmt_execute($update)) {

        echo json_encode([
            "status" => false,
            "message" => "Failed to update attendance.",
            "error" => mysqli_error($conn)
        ]);

        exit;
    }

    echo json_encode([
        "status" => true,
        "message" => "Attendance updated successfully.",
        "action" => "updated",
        "attendance_id" => $attendance_id
    ]);

    exit;
}


// Otherwise → INSERT

$insert = mysqli_prepare(
    $conn,
    "
    INSERT INTO attendance
    (
        student_id,
        attendance_date,
        status,
        attendance_type
    )
    VALUES (?, ?, ?, ?)
    "
);

mysqli_stmt_bind_param(
    $insert,
    "isss",
    $student_id,
    $attendance_date,
    $status,
    $attendance_type
);

if (!mysqli_stmt_execute($insert)) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to save attendance.",
        "error" => mysqli_error($conn)
    ]);

    exit;
}

echo json_encode([
    "status" => true,
    "message" => "Attendance marked successfully.",
    "action" => "created",
    "attendance_id" => mysqli_insert_id($conn)
]);

?>