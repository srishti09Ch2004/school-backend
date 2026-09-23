<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "status" => false,
        "message" => "No data received"
    ]);
    exit;
}

$full_name = $data["full_name"];
$email = $data["email"];
$password = $data["password"];
$employee_id = $data["employee_id"];
$department = $data["department"];
$qualification = $data["qualification"];
$phone = $data["phone"];
$address = $data["address"];

// Check Email
$check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

if (mysqli_num_rows($check) > 0) {
    echo json_encode([
        "status" => false,
        "message" => "Email already exists"
    ]);
    exit;
}

// Insert into users
mysqli_query($conn, "
INSERT INTO users(full_name,email,password,role)
VALUES('$full_name','$email','$password','teacher')
");

$user_id = mysqli_insert_id($conn);

// Insert into teachers
mysqli_query($conn, "
INSERT INTO teachers
(user_id,employee_id,department,qualification,phone,address)
VALUES
('$user_id','$employee_id','$department','$qualification','$phone','$address')
");

echo json_encode([
    "status" => true,
    "message" => "Teacher Added Successfully"
]);

?>