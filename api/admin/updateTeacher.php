<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if(!$data){
    echo json_encode([
        "status"=>false,
        "message"=>"No data received"
    ]);
    exit;
}

$id = $data["id"];
$full_name = $data["full_name"];
$email = $data["email"];
$password = $data["password"];
$employee_id = $data["employee_id"];
$department = $data["department"];
$qualification = $data["qualification"];
$phone = $data["phone"];
$address = $data["address"];

// Update users table

if($password!=""){

    mysqli_query($conn,"
    UPDATE users
    SET
    full_name='$full_name',
    email='$email',
    password='$password'
    WHERE id='$id'
    ");

}else{

    mysqli_query($conn,"
    UPDATE users
    SET
    full_name='$full_name',
    email='$email'
    WHERE id='$id'
    ");

}

// Update teachers table

mysqli_query($conn,"
UPDATE teachers
SET
employee_id='$employee_id',
department='$department',
qualification='$qualification',
phone='$phone',
address='$address'
WHERE user_id='$id'
");

echo json_encode([
    "status"=>true,
    "message"=>"Teacher Updated Successfully"
]);

?>