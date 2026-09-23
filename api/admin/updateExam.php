<?php

// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Content-Type: application/json");

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

// $id = isset($data["id"]) ? (int)$data["id"] : 0;

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

// if ($id <= 0) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Invalid Exam ID"
//     ]);
//     exit();
// }

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

// $sql = "UPDATE exams SET
//     exam_name = ?,
//     class = ?,
//     section = ?,
//     subject = ?,
//     exam_date = ?,
//     start_time = ?,
//     end_time = ?,
//     total_marks = ?,
//     passing_marks = ?,
//     status = ?
//     WHERE id = ?";

// $stmt = $conn->prepare($sql);

// if (!$stmt) {
//     echo json_encode([
//         "status" => false,
//         "message" => "Database statement preparation failed"
//     ]);
//     exit();
// }

// $stmt->bind_param(
//     "sssssssissi",
//     $exam_name,
//     $class,
//     $section,
//     $subject,
//     $exam_date,
//     $start_time,
//     $end_time,
//     $total_marks,
//     $passing_marks,
//     $status,
//     $id
// );

// if ($stmt->execute()) {

//     if ($stmt->affected_rows > 0) {

//         echo json_encode([
//             "status" => true,
//             "message" => "Exam Updated Successfully"
//         ]);

//     } else {

//         $checkSql = "SELECT id FROM exams WHERE id = ?";
//         $checkStmt = $conn->prepare($checkSql);
//         $checkStmt->bind_param("i", $id);
//         $checkStmt->execute();
//         $checkResult = $checkStmt->get_result();

//         if ($checkResult->num_rows > 0) {

//             echo json_encode([
//                 "status" => true,
//                 "message" => "Exam already has the latest data"
//             ]);

//         } else {

//             echo json_encode([
//                 "status" => false,
//                 "message" => "Exam not found"
//             ]);
//         }

//         $checkStmt->close();
//     }

// } else {

//     echo json_encode([
//         "status" => false,
//         "message" => "Update Failed: " . $stmt->error
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

$id = isset($data["id"])
    ? (int)$data["id"]
    : 0;

$exam_session_id = isset($data["exam_session_id"])
    ? (int)$data["exam_session_id"]
    : 0;

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

if ($id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid subject schedule ID"
    ]);
    exit();
}

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

$existingSql = "SELECT
                    id,
                    exam_session_id
                FROM exams
                WHERE id = ?";

$existingStmt = $conn->prepare($existingSql);
$existingStmt->bind_param("i", $id);
$existingStmt->execute();

$existingResult = $existingStmt->get_result();

if ($existingResult->num_rows === 0) {
    $existingStmt->close();

    echo json_encode([
        "status" => false,
        "message" => "Subject schedule not found"
    ]);
    exit();
}

$existingExam = $existingResult->fetch_assoc();

$existingStmt->close();

if ((int)$existingExam["exam_session_id"] !== $exam_session_id) {
    echo json_encode([
        "status" => false,
        "message" => "Subject does not belong to this examination session"
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
                 AND exam_date = ?
                 AND id != ?";

$duplicateStmt = $conn->prepare($duplicateSql);

$duplicateStmt->bind_param(
    "issssi",
    $exam_session_id,
    $class,
    $section,
    $subject,
    $exam_date,
    $id
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

$conflictSql = "SELECT
                    id,
                    subject,
                    start_time,
                    end_time
                FROM exams
                WHERE exam_session_id = ?
                AND class = ?
                AND section = ?
                AND exam_date = ?
                AND start_time < ?
                AND end_time > ?
                AND id != ?";

$conflictStmt = $conn->prepare($conflictSql);

$conflictStmt->bind_param(
    "isssssi",
    $exam_session_id,
    $class,
    $section,
    $exam_date,
    $end_time,
    $start_time,
    $id
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

$sql = "UPDATE exams SET
            exam_session_id = ?,
            exam_name = ?,
            class = ?,
            section = ?,
            subject = ?,
            exam_date = ?,
            start_time = ?,
            end_time = ?,
            total_marks = ?,
            passing_marks = ?,
            status = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database statement preparation failed"
    ]);
    exit();
}

$stmt->bind_param(
    "isssssssissi",
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
    $status,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Subject schedule updated successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to update subject schedule: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>