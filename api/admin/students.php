<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$sql = "SELECT
    students.id AS id,
    users.id AS user_id,
    users.full_name,
    users.email,
    students.admission_no,
    students.admission_date,
    students.class,
    students.section,
    students.roll_no,
    students.gender,
    students.dob,
    students.phone,
    students.address,
    students.status

FROM students

INNER JOIN users
    ON students.user_id = users.id

ORDER BY students.id DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {

    echo json_encode([
        "status" => false,
        "message" => "Database query failed",
        "error" => mysqli_error($conn)
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