
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

$session_id = isset($data["id"])
    ? (int)$data["id"]
    : 0;

if ($session_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session ID is required"
    ]);
    exit();
}

$checkSql = "SELECT id, status
             FROM exam_sessions
             WHERE id = ?";

$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $session_id);
$checkStmt->execute();

$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session not found"
    ]);
    exit();
}

$session = $result->fetch_assoc();

$checkStmt->close();

if ($session["status"] === "Published") {
    echo json_encode([
        "status" => false,
        "message" => "Published examination cannot be deleted. Cancel it first."
    ]);
    exit();
}

if ($session["status"] === "Completed") {
    echo json_encode([
        "status" => false,
        "message" => "Completed examination cannot be deleted"
    ]);
    exit();
}

if ($session["status"] === "Cancelled") {
    echo json_encode([
        "status" => false,
        "message" => "This examination is already cancelled"
    ]);
    exit();
}

$stmt = $conn->prepare(
    "DELETE FROM exam_sessions WHERE id = ?"
);

$stmt->bind_param("i", $session_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Exam session and its subjects deleted successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to delete exam session: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
