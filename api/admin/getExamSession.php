
<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

include("../../config/db.php");

$session_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($session_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Exam session ID is required"
    ]);
    exit();
}

$sessionSql = "SELECT
                    id,
                    exam_name,
                    academic_year,
                    exam_type,
                    start_date,
                    end_date,
                    description,
                    status,
                    created_by,
                    created_at,
                    updated_at
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

$examSql = "SELECT
                id,
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
                status,
                created_at
            FROM exams
            WHERE exam_session_id = ?
            ORDER BY exam_date ASC, start_time ASC, id ASC";

$examStmt = $conn->prepare($examSql);
$examStmt->bind_param("i", $session_id);
$examStmt->execute();

$examResult = $examStmt->get_result();

$subjects = [];

while ($row = $examResult->fetch_assoc()) {
    $row["total_marks"] = (int)$row["total_marks"];
    $row["passing_marks"] = (int)$row["passing_marks"];
    $subjects[] = $row;
}

$examStmt->close();

echo json_encode([
    "status" => true,
    "data" => [
        "session" => $session,
        "subjects" => $subjects
    ]
]);

mysqli_close($conn);
?>
