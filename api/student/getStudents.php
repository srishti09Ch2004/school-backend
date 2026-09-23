<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$sql = "SELECT * FROM students ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$students = [];

while($row = mysqli_fetch_assoc($result)){
    $students[] = $row;
}

echo json_encode([
    "status" => true,
    "students" => $students
]);

?>