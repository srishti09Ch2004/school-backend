<?php

session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../config/db.php");

if(!isset($_SESSION["user_id"])){

    echo json_encode([
        "status"=>false,
        "message"=>"Please Login First"
    ]);

    exit;
}

$id=$_SESSION["user_id"];

$sql="SELECT id,full_name,email,role FROM users WHERE id='$id'";

$result=mysqli_query($conn,$sql);

$user=mysqli_fetch_assoc($result);

echo json_encode([
    "status"=>true,
    "user"=>$user
]);

?>