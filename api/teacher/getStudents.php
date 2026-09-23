<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}

$class = $_GET["class"] ?? "";
$section = $_GET["section"] ?? "";

if ($class === "" || $section === "") {

    echo json_encode([
        "status" => false,
        "message" => "Class and section are required"
    ]);

    exit;
}

$sql = "
    SELECT
        s.id,
        s.user_id,
        s.admission_no,
        s.class,
        s.section,
        s.roll_no,
        s.gender,
        s.dob,
        s.phone,
        s.address,
        s.status,
        u.full_name,
        u.email

    FROM students s

    LEFT JOIN users u
        ON s.user_id = u.id

    WHERE s.class = ?
    AND s.section = ?

    ORDER BY
        CAST(s.roll_no AS UNSIGNED) ASC,
        s.id ASC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $class,
    $section
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$students = [];

while ($row = mysqli_fetch_assoc($result)) {

    $students[] = [
        "id" => intval($row["id"]),
        "user_id" => intval($row["user_id"]),
        "name" => $row["full_name"],
        "email" => $row["email"],
        "admission_no" => $row["admission_no"],
        "class" => $row["class"],
        "section" => $row["section"],
        "roll_no" => $row["roll_no"],
        "gender" => $row["gender"],
        "dob" => $row["dob"],
        "phone" => $row["phone"],
        "address" => $row["address"],
        "status" => $row["status"]
    ];
}

echo json_encode([
    "status" => true,
    "message" => "Students fetched successfully",
    "total" => count($students),
    "students" => $students
]);

mysqli_stmt_close($stmt);

?>