<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$id = $_GET["id"];

$result = mysqli_query($conn,"
SELECT
users.id,
users.full_name,
users.email,
teachers.employee_id,
teachers.department,
teachers.qualification,
teachers.phone,
teachers.address
FROM users
INNER JOIN teachers
ON users.id=teachers.user_id
WHERE users.id='$id'
");

if(mysqli_num_rows($result)>0){

    echo json_encode([
        "status"=>true,
        "data"=>mysqli_fetch_assoc($result)
    ]);

}else{

    echo json_encode([
        "status"=>false,
        "message"=>"Teacher not found"
    ]);

}