
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

$sql = "SELECT
            s.id,
            s.exam_name,
            s.academic_year,
            s.exam_type,
            s.start_date,
            s.end_date,
            s.description,
            s.status,
            s.created_by,
            s.created_at,
            s.updated_at,
            COUNT(e.id) AS subject_count
        FROM exam_sessions s
        LEFT JOIN exams e
            ON e.exam_session_id = s.id
        GROUP BY
            s.id,
            s.exam_name,
            s.academic_year,
            s.exam_type,
            s.start_date,
            s.end_date,
            s.description,
            s.status,
            s.created_by,
            s.created_at,
            s.updated_at
        ORDER BY
            s.start_date DESC,
            s.created_at DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . mysqli_error($conn),
        "data" => []
    ]);
    exit();
}

$sessions = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row["subject_count"] = (int)$row["subject_count"];
    $sessions[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $sessions
]);

mysqli_close($conn);
?>
