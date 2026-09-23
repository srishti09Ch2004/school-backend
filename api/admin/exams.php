<?php

// header("Content-Type: application/json");
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//     http_response_code(200);
//     exit();
// }

// include("../../config/db.php");

// $sql = "SELECT
//             e.id,
//             e.exam_session_id,
//             e.exam_name,
//             e.class,
//             e.section,
//             e.subject,
//             e.exam_date,
//             e.start_time,
//             e.end_time,
//             e.total_marks,
//             e.passing_marks,
//             e.status,
//             e.created_at,
//             s.exam_name AS session_exam_name,
//             s.academic_year,
//             s.exam_type,
//             s.start_date AS session_start_date,
//             s.end_date AS session_end_date,
//             s.status AS session_status
//         FROM exams e
//         INNER JOIN exam_sessions s
//             ON s.id = e.exam_session_id
//         ORDER BY
//             e.exam_date ASC,
//             e.start_time ASC,
//             e.id ASC";

// $result = mysqli_query($conn, $sql);

// if (!$result) {
//     echo json_encode([
//         "status" => false,
//         "message" => mysqli_error($conn),
//         "data" => []
//     ]);
//     exit();
// }

// $exams = [];

// while ($row = mysqli_fetch_assoc($result)) {
//     $row["total_marks"] = (int)$row["total_marks"];
//     $row["passing_marks"] = (int)$row["passing_marks"];
//     $row["exam_session_id"] = (int)$row["exam_session_id"];

//     $exams[] = $row;
// }

// echo json_encode([
//     "status" => true,
//     "data" => $exams
// ]);

// mysqli_close($conn);
// ?>



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
            e.id,
            e.exam_session_id,
            e.exam_name,
            e.class,
            e.section,
            e.subject,
            e.exam_date,
            e.start_time,
            e.end_time,
            e.total_marks,
            e.passing_marks,
            e.status AS paper_status,
            e.created_at,

            s.exam_name AS session_exam_name,
            s.academic_year,
            s.exam_type,
            s.start_date AS session_start_date,
            s.end_date AS session_end_date,
            s.description AS session_description,
            s.status AS session_status,
            s.created_by,
            s.created_at AS session_created_at,
            s.updated_at AS session_updated_at

        FROM exams e

        INNER JOIN exam_sessions s
            ON s.id = e.exam_session_id

        ORDER BY
            e.exam_date ASC,
            e.start_time ASC,
            e.id ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . mysqli_error($conn),
        "data" => []
    ]);
    exit();
}

$exams = [];

while ($row = mysqli_fetch_assoc($result)) {

    $row["id"] = (int)$row["id"];
    $row["exam_session_id"] = (int)$row["exam_session_id"];
    $row["total_marks"] = (int)$row["total_marks"];
    $row["passing_marks"] = (int)$row["passing_marks"];

    $exams[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $exams
]);

mysqli_close($conn);
?>