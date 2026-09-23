<?php

// header("Content-Type: application/json");
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     http_response_code(200);
//     exit;
// }

// include("../../config/db.php");

// $data = json_decode(file_get_contents("php://input"), true);

// if (!$data || !isset($data["id"])) {

//     echo json_encode([
//         "status" => false,
//         "message" => "Exam ID is required"
//     ]);

//     exit;
// }

// $id = intval($data["id"]);

// $sql = "DELETE FROM exams WHERE id = ?";

// $stmt = $conn->prepare($sql);

// $stmt->bind_param("i", $id);

// if ($stmt->execute()) {

//     if ($stmt->affected_rows > 0) {

//         echo json_encode([
//             "status" => true,
//             "message" => "Exam Deleted Successfully"
//         ]);

//     } else {

//         echo json_encode([
//             "status" => false,
//             "message" => "Exam not found"
//         ]);

//     }

// } else {

//     echo json_encode([
//         "status" => false,
//         "message" => "Delete Failed"
//     ]);

// }

// $stmt->close();
// $conn->close();







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

$id = isset($data["id"])
    ? (int)$data["id"]
    : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Subject schedule ID is required"
    ]);
    exit();
}

$sql = "SELECT
            e.id,
            e.exam_session_id,
            e.subject,
            s.status AS session_status
        FROM exams e
        INNER JOIN exam_sessions s
            ON s.id = e.exam_session_id
        WHERE e.id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database statement preparation failed"
    ]);
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    echo json_encode([
        "status" => false,
        "message" => "Subject schedule not found"
    ]);
    exit();
}

$exam = $result->fetch_assoc();

$stmt->close();

if ($exam["session_status"] === "Published") {
    echo json_encode([
        "status" => false,
        "message" => "Published examination subject cannot be deleted"
    ]);
    exit();
}

if ($exam["session_status"] === "Completed") {
    echo json_encode([
        "status" => false,
        "message" => "Completed examination subject cannot be deleted"
    ]);
    exit();
}

if ($exam["session_status"] === "Cancelled") {
    echo json_encode([
        "status" => false,
        "message" => "Cancelled examination subject cannot be deleted"
    ]);
    exit();
}

$deleteSql = "DELETE FROM exams WHERE id = ?";

$deleteStmt = $conn->prepare($deleteSql);
$deleteStmt->bind_param("i", $id);

if ($deleteStmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Subject schedule deleted successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to delete subject schedule"
    ]);
}

$deleteStmt->close();
$conn->close();
?>