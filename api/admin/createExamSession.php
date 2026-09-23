
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

$exam_name = trim($data["exam_name"] ?? "");
$academic_year = trim($data["academic_year"] ?? "");
$exam_type = trim($data["exam_type"] ?? "");
$start_date = trim($data["start_date"] ?? "");
$end_date = trim($data["end_date"] ?? "");
$description = trim($data["description"] ?? "");

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

if ($start_date !== "" && $end_date !== "" && $end_date < $start_date) {
    echo json_encode([
        "status" => false,
        "message" => "End date cannot be before start date"
    ]);
    exit();
}

$sql = "INSERT INTO exam_sessions
        (
            exam_name,
            academic_year,
            exam_type,
            start_date,
            end_date,
            description,
            status
        )
        VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, 'Draft')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database statement preparation failed"
    ]);
    exit();
}

$stmt->bind_param(
    "ssssss",
    $exam_name,
    $academic_year,
    $exam_type,
    $start_date,
    $end_date,
    $description
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Exam session created successfully",
        "id" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to create exam session: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

