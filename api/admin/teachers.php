<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$sql = "
SELECT
u.id,
u.full_name,
u.email,
t.user_id,
t.employee_id,
t.department,
t.qualification,
t.phone,
t.address
FROM users u
JOIN teachers t
ON u.id=t.user_id
WHERE u.role='teacher'
ORDER BY u.id DESC
";

$result=mysqli_query($conn,$sql);

$data=[];

while($row=mysqli_fetch_assoc($result)){
    $data[]=$row;
}

echo json_encode([
    "status"=>true,
    "data"=>$data
]);