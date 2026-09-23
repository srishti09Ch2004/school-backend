
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

$exam_name = trim($data["exam_name"] ?? "");
$academic_year = trim($data["academic_year"] ?? "");
$exam_type = trim($data["exam_type"] ?? "");
$start_date = trim($data["start_date"] ?? "");
$end_date = trim($data["end_date"] ?? "");
$description = trim($data["description"] ?? "");

if ($id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid exam session ID"
    ]);
    exit();
}

if (
    $exam_name === "" ||
    $academic_year === "" ||
    $exam_type === ""
) {
    echo json_encode([
        "status" => false,
        "message" => "Exam name, academic year and exam type are required"
    ]);
    exit();
}

if (
    $start_date !== "" &&
    $end_date !== "" &&
    $end_date < $start_date
) {
    echo json_encode([
        "status" => false,
        "message" => "End date cannot be before start date"
    ]);
    exit();
}

$checkSql = "SELECT id, status
             FROM exam_sessions
             WHERE id = ?";

$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $id);
$checkStmt->execute();

$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session not found"
    ]);
    exit();
}

$existing = $checkResult->fetch_assoc();

$checkStmt->close();

if ($existing["status"] === "Completed") {
    echo json_encode([
        "status" => false,
        "message" => "Completed examination cannot be modified"
    ]);
    exit();
}

if ($existing["status"] === "Cancelled") {
    echo json_encode([
        "status" => false,
        "message" => "Cancelled examination cannot be modified"
    ]);
    exit();
}

$sql = "UPDATE exam_sessions SET
            exam_name = ?,
            academic_year = ?,
            exam_type = ?,
            start_date = NULLIF(?, ''),
            end_date = NULLIF(?, ''),
            description = ?
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
    "ssssssi",
    $exam_name,
    $academic_year,
    $exam_type,
    $start_date,
    $end_date,
    $description,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Exam session updated successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to update exam session: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
