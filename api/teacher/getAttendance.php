<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            "status" => false,
            "message" => "Only GET method is allowed"
        ]);
        exit;
    }

    // GET PARAMETERS

    $class = isset($_GET['class']) ? trim($_GET['class']) : '';
    $section = isset($_GET['section']) ? trim($_GET['section']) : '';
    $attendance_date = isset($_GET['attendance_date'])
        ? trim($_GET['attendance_date'])
        : '';

    // VALIDATION
  

    if ($class === '') {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Class is required"
        ]);
        exit;
    }

    if ($section === '') {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Section is required"
        ]);
        exit;
    }

    if ($attendance_date === '') {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Attendance date is required"
        ]);
        exit;
    }

    // Validate date
    $dateObject = DateTime::createFromFormat('Y-m-d', $attendance_date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $attendance_date) {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid attendance date. Use YYYY-MM-DD"
        ]);
        exit;
    }

    $sql = "
        SELECT
            s.id AS student_id,
            s.user_id,
            u.full_name AS name,
            u.email,
            s.admission_no,
            s.class,
            s.section,
            s.roll_no,
            s.gender,
            s.dob,
            s.phone,
            s.address,
            s.status AS student_status,

            a.id AS attendance_id,
            a.marked_by_teacher_id,
            a.attendance_date,
            a.status AS attendance_status,
            a.attendance_type,
            a.created_at AS attendance_created_at

        FROM students s

        LEFT JOIN users u
            ON s.user_id = u.id

        LEFT JOIN attendance a
            ON a.student_id = s.id
            AND a.attendance_date = ?

        WHERE s.status = 'Active'
          AND s.class = ?
          AND s.section = ?

        ORDER BY
            CASE
                WHEN s.roll_no REGEXP '^[0-9]+$'
                THEN CAST(s.roll_no AS UNSIGNED)
                ELSE 999999
            END,
            s.id ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "SQL prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "sss",
        $attendance_date,
        $class,
        $section
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "SQL execute failed: " . $stmt->error
        );
    }

    $result = $stmt->get_result();

    $students = [];

    while ($row = $result->fetch_assoc()) {

        $attendanceStatus = $row['attendance_status'];

        if ($attendanceStatus === null || $attendanceStatus === '') {
            $attendanceStatus = "Not Marked";
        }

        $students[] = [
            "student_id" => (int)$row["student_id"],
            "user_id" => $row["user_id"] !== null
                ? (int)$row["user_id"]
                : null,

            "name" => $row["name"] ?? "",
            "email" => $row["email"] ?? "",

            "admission_no" => $row["admission_no"] ?? "",
            "class" => $row["class"] ?? "",
            "section" => $row["section"] ?? "",
            "roll_no" => $row["roll_no"] ?? "",

            "gender" => $row["gender"] ?? "",
            "dob" => $row["dob"] ?? "",
            "phone" => $row["phone"] ?? "",
            "address" => $row["address"] ?? "",

            "student_status" => $row["student_status"] ?? "",

            "attendance_id" => $row["attendance_id"] !== null
                ? (int)$row["attendance_id"]
                : null,

            "marked_by_teacher_id" =>
                $row["marked_by_teacher_id"] !== null
                    ? (int)$row["marked_by_teacher_id"]
                    : null,

            "attendance_date" => $row["attendance_date"] ?? null,

            "status" => $attendanceStatus,

            "attendance_type" => $row["attendance_type"] ?? null,

            "created_at" => $row["attendance_created_at"] ?? null
        ];
    }

    $stmt->close();

    // SUCCESS RESPONSE

    echo json_encode([
        "status" => true,
        "message" => "Attendance students fetched successfully",

        "class" => $class,
        "section" => $section,
        "attendance_date" => $attendance_date,

        "total" => count($students),

        "data" => $students
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch attendance students",
        "error" => $e->getMessage()
    ]);
}
?>