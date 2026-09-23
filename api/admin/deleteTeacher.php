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

// pehle teacher table se delete
mysqli_query($conn,"DELETE FROM teachers WHERE user_id='$id'");

// fir users table se delete
mysqli_query($conn,"DELETE FROM users WHERE id='$id'");

echo json_encode([
    "status"=>true,
    "message"=>"Teacher Deleted Successfully"
]);

?>