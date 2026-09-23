<!-- <?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

$sql = "SELECT
            a.id,
            a.student_id,
            a.attendance_date,
            a.status,
            a.attendance_type,
            s.admission_no,
            s.class,
            s.section,
            s.roll_no,
            u.full_name
        FROM attendance a
        LEFT JOIN students s
            ON a.student_id = s.id
        LEFT JOIN users u
            ON s.user_id = u.id
        ORDER BY a.attendance_date DESC, a.id DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$attendance = [];

while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $attendance
]);

$conn->close();

?> -->

<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

$date_from = $_GET["date_from"] ?? "";
$date_to = $_GET["date_to"] ?? "";
$student_id = $_GET["student_id"] ?? "";

$sql = "
    SELECT
        attendance.id,
        attendance.student_id,
        users.full_name,
        students.class,
        students.section,
        attendance.attendance_date,
        attendance.status,
        attendance.attendance_type,
        attendance.created_at
    FROM attendance

    INNER JOIN students
        ON attendance.student_id = students.id

    INNER JOIN users
        ON students.user_id = users.id

    WHERE 1=1
";

$params = [];
$types = "";


// Student filter

if ($student_id !== "") {

    $sql .= " AND attendance.student_id = ?";

    $params[] = $student_id;
    $types .= "i";
}


// Date from

if ($date_from !== "") {

    $sql .= " AND attendance.attendance_date >= ?";

    $params[] = $date_from;
    $types .= "s";
}


// Date to

if ($date_to !== "") {

    $sql .= " AND attendance.attendance_date <= ?";

    $params[] = $date_to;
    $types .= "s";
}


$sql .= "
    ORDER BY attendance.attendance_date DESC,
             attendance.id DESC
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Database query preparation failed.",
        "error" => mysqli_error($conn)
    ]);

    exit;
}


if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );
}


mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    $data[] = $row;
}


echo json_encode([
    "status" => true,
    "data" => $data
]);

?>