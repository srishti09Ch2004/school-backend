<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

include("../../config/db.php");

if($_SERVER["REQUEST_METHOD"] != "POST"){

    echo json_encode([
        "status" => false,
        "message" => "Invalid Request"
    ]);

    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$full_name   = $data["full_name"] ?? "";
$email       = $data["email"] ?? "";
$password    = $data["password"] ?? "";
$admission_no= $data["admission_no"] ?? "";
$class       = $data["class"] ?? "";
$section     = $data["section"] ?? "";
$roll_no     = $data["roll_no"] ?? "";
$gender      = $data["gender"] ?? "";
$dob         = $data["dob"] ?? "";
$phone       = $data["phone"] ?? "";
$address     = $data["address"] ?? "";

if(
    empty($full_name) || empty($email) || empty($password) ||
    empty($admission_no) || empty($class) || empty($section)
){

    echo json_encode([
        "status" => false,
        "message" => "Please fill all required fields"
    ]);

    exit;
}

// Email check
$check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

if(mysqli_num_rows($check) > 0){

    echo json_encode([
        "status" => false,
        "message" => "Email already exists"
    ]);

    exit;
}

// Insert into users
$user_sql = "INSERT INTO users(full_name,email,password,role)
VALUES('$full_name','$email','$password','student')";

mysqli_query($conn, $user_sql);

$user_id = mysqli_insert_id($conn);

// Insert into students
$student_sql = "INSERT INTO students
(user_id,admission_no,class,section,roll_no,gender,dob,phone,address)
VALUES
('$user_id','$admission_no','$class','$section','$roll_no','$gender','$dob','$phone','$address')";

if(mysqli_query($conn, $student_sql)){

    echo json_encode([
        "status" => true,
        "message" => "Student added successfully"
    ]);

}else{

    echo json_encode([
        "status" => false,
        "message" => "Failed to add student"
    ]);
}

?>