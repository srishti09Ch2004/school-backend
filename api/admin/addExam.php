<?php

// header("Content-Type: application/json");
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//     http_response_code(200);
//     exit();
// }

// include("../../config/db.php");

// $data = json_decode(file_get_contents("php://input"), true);

// if (!$data) {
//     echo json_encode([
//         "status" => false,
//         "message" => "No Data Received"
//     ]);
//     exit();
// }

// $exam_name = trim($data["exam_name"] ?? "");
// $class = trim($data["class"] ?? "");
// $section = trim($data["section"] ?? "");
// $subject = trim($data["subject"] ?? "");
// $exam_date = $data["exam_date"] ?? "";
// $start_time = $data["start_time"] ?? "";
// $end_time = $data["end_time"] ?? "";
// $total_marks = isset($data["total_marks"]) ? (int)$data["total_marks"] : 0;
// $passing_marks = isset($data["passing_marks"]) ? (int)$data["passing_marks"] : 0;
// $status = trim($data["status"] ?? "");

// if (
//     $exam_name === "" ||
//     $class === "" ||
//     $section === "" ||
//     $subject === "" ||
//     $exam_date === "" ||
//     $start_time === "" ||
//     $end_time === ""
// ) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Please fill all required fields"
//     ]);
//     exit();
// }

// if ($total_marks < 0 || $passing_marks < 0) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Marks cannot be negative"
//     ]);
//     exit();
// }

// if ($passing_marks > $total_marks) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Passing marks cannot be greater than total marks"
//     ]);
//     exit();
// }

// $sql = "INSERT INTO exams
//     (
//         exam_name,
//         class,
//         section,
//         subject,
//         exam_date,
//         start_time,
//         end_time,
//         total_marks,
//         passing_marks,
//         status
//     )
//     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

// $stmt = $conn->prepare($sql);

// if (!$stmt) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Database statement preparation failed"
//     ]);
//     exit();
// }

// $stmt->bind_param(
//     "sssssssiss",
//     $exam_name,
//     $class,
//     $section,
//     $subject,
//     $exam_date,
//     $start_time,
//     $end_time,
//     $total_marks,
//     $passing_marks,
//     $status
// );

// if ($stmt->execute()) {

//     echo json_encode([
//         "status" => true,
//         "message" => "Exam Added Successfully",
//         "id" => $stmt->insert_id
//     ]);

// } else {

//     echo json_encode([
//         "status" => false,
//         "message" => "Exam Add Failed: " . $stmt->error
//     ]);
// }

// $stmt->close();
// $conn->close();

// ?>




<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

include("../../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "status" => false,
        "message" => "No data received"
    ]);
    exit();
}

$exam_session_id = isset($data["exam_session_id"])
    ? (int)$data["exam_session_id"]
    : 0;

$exam_name = trim($data["exam_name"] ?? "");
$class = trim($data["class"] ?? "");
$section = trim($data["section"] ?? "");
$subject = trim($data["subject"] ?? "");
$exam_date = trim($data["exam_date"] ?? "");
$start_time = trim($data["start_time"] ?? "");
$end_time = trim($data["end_time"] ?? "");
$total_marks = isset($data["total_marks"])
    ? (int)$data["total_marks"]
    : 0;
$passing_marks = isset($data["passing_marks"])
    ? (int)$data["passing_marks"]
    : 0;
$status = trim($data["status"] ?? "Scheduled");

if ($exam_session_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session ID is required"
    ]);
    exit();
}

if (
    $class === "" ||
    $section === "" ||
    $subject === "" ||
    $exam_date === "" ||
    $start_time === "" ||
    $end_time === ""
) {
    echo json_encode([
        "status" => false,
        "message" => "Please fill all required subject fields"
    ]);
    exit();
}

if ($start_time >= $end_time) {
    echo json_encode([
        "status" => false,
        "message" => "End time must be after start time"
    ]);
    exit();
}

if ($total_marks < 0 || $passing_marks < 0) {
    echo json_encode([
        "status" => false,
        "message" => "Marks cannot be negative"
    ]);
    exit();
}

if ($passing_marks > $total_marks) {
    echo json_encode([
        "status" => false,
        "message" => "Passing marks cannot be greater than total marks"
    ]);
    exit();
}

$sessionSql = "SELECT
                    id,
                    exam_name,
                    start_date,
                    end_date,
                    status
               FROM exam_sessions
               WHERE id = ?";

$sessionStmt = $conn->prepare($sessionSql);

if (!$sessionStmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database statement preparation failed"
    ]);
    exit();
}

$sessionStmt->bind_param("i", $exam_session_id);
$sessionStmt->execute();

$sessionResult = $sessionStmt->get_result();

if ($sessionResult->num_rows === 0) {
    $sessionStmt->close();

    echo json_encode([
        "status" => false,
        "message" => "Exam session not found"
    ]);
    exit();
}

$session = $sessionResult->fetch_assoc();

$sessionStmt->close();

if ($session["status"] === "Completed") {
    echo json_encode([
        "status" => false,
        "message" => "Completed examination cannot be modified"
    ]);
    exit();
}

if ($session["status"] === "Cancelled") {
    echo json_encode([
        "status" => false,
        "message" => "Cancelled examination cannot be modified"
    ]);
    exit();
}

if (
    !empty($session["start_date"]) &&
    $exam_date < $session["start_date"]
) {
    echo json_encode([
        "status" => false,
        "message" => "Exam date cannot be before examination start date"
    ]);
    exit();
}

if (
    !empty($session["end_date"]) &&
    $exam_date > $session["end_date"]
) {
    echo json_encode([
        "status" => false,
        "message" => "Exam date cannot be after examination end date"
    ]);
    exit();
}

$duplicateSql = "SELECT id
                 FROM exams
                 WHERE exam_session_id = ?
                 AND class = ?
                 AND section = ?
                 AND subject = ?
                 AND exam_date = ?";

$duplicateStmt = $conn->prepare($duplicateSql);

$duplicateStmt->bind_param(
    "issss",
    $exam_session_id,
    $class,
    $section,
    $subject,
    $exam_date
);

$duplicateStmt->execute();

$duplicateResult = $duplicateStmt->get_result();

if ($duplicateResult->num_rows > 0) {
    $duplicateStmt->close();

    echo json_encode([
        "status" => false,
        "message" => "This subject is already scheduled for this class and date"
    ]);
    exit();
}

$duplicateStmt->close();

$conflictSql = "SELECT id, subject, start_time, end_time
                FROM exams
                WHERE exam_session_id = ?
                AND class = ?
                AND section = ?
                AND exam_date = ?
                AND start_time < ?
                AND end_time > ?";

$conflictStmt = $conn->prepare($conflictSql);

$conflictStmt->bind_param(
    "isssss",
    $exam_session_id,
    $class,
    $section,
    $exam_date,
    $end_time,
    $start_time
);

$conflictStmt->execute();

$conflictResult = $conflictStmt->get_result();

if ($conflictResult->num_rows > 0) {
    $conflict = $conflictResult->fetch_assoc();

    $conflictStmt->close();

    echo json_encode([
        "status" => false,
        "message" => "Time conflict with " . $conflict["subject"] . " scheduled from " .
            $conflict["start_time"] . " to " . $conflict["end_time"]
    ]);
    exit();
}

$conflictStmt->close();

$sql = "INSERT INTO exams
        (
            exam_session_id,
            exam_name,
            class,
            section,
            subject,
            exam_date,
            start_time,
            end_time,
            total_marks,
            passing_marks,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database statement preparation failed"
    ]);
    exit();
}

$stmt->bind_param(
    "isssssssiss",
    $exam_session_id,
    $session["exam_name"],
    $class,
    $section,
    $subject,
    $exam_date,
    $start_time,
    $end_time,
    $total_marks,
    $passing_marks,
    $status
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Subject schedule added successfully",
        "id" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to add subject schedule: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>