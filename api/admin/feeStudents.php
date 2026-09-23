<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$sql = "
SELECT
    students.id,
    users.full_name,
    students.class,
    students.section
FROM students
INNER JOIN users
    ON students.user_id = users.id
ORDER BY students.id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database query failed"
    ]);
    exit;
}

$students = [];

while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $students
]);
?>