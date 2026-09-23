
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

$session_id = isset($data["session_id"])
    ? (int)$data["session_id"]
    : 0;

if ($session_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session ID is required"
    ]);
    exit();
}

$sessionSql = "SELECT id, status
               FROM exam_sessions
               WHERE id = ?";

$sessionStmt = $conn->prepare($sessionSql);
$sessionStmt->bind_param("i", $session_id);
$sessionStmt->execute();

$sessionResult = $sessionStmt->get_result();

if ($sessionResult->num_rows === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session not found"
    ]);
    exit();
}

$session = $sessionResult->fetch_assoc();

$sessionStmt->close();

if ($session["status"] === "Cancelled") {
    echo json_encode([
        "status" => false,
        "message" => "Cancelled examination cannot be published"
    ]);
    exit();
}

$countSql = "SELECT COUNT(*) AS total
             FROM exams
             WHERE exam_session_id = ?";

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $session_id);
$countStmt->execute();

$countResult = $countStmt->get_result();
$countData = $countResult->fetch_assoc();

$countStmt->close();

if ((int)$countData["total"] === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Add at least one subject before publishing"
    ]);
    exit();
}

$updateSql = "UPDATE exam_sessions
              SET status = 'Published'
              WHERE id = ?";

$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("i", $session_id);

if ($updateStmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Exam datesheet published successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to publish datesheet"
    ]);
}

$updateStmt->close();
$conn->close();
?>
