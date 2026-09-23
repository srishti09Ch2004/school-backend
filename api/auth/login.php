<!-- <?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "../../config/db.php";

$data = json_decode(file_get_contents("php://input"));

$email = $data->email ?? "";
$password = $data->password ?? "";

if($email=="" || $password==""){
    echo json_encode([
        "success"=>false,
        "message"=>"All fields are required."
    ]);
    exit;
}

$sql="SELECT * FROM users WHERE email=?";

$stmt=$conn->prepare($sql);

$stmt->bind_param("s",$email);

$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows==0){

    echo json_encode([
        "success"=>false,
        "message"=>"Email not found."
    ]);

    exit;
}

$user=$result->fetch_assoc();

if($password!=$user["password"]){

    echo json_encode([
        "success"=>false,
        "message"=>"Wrong Password."
    ]);

    exit;
}

echo json_encode([
    "success"=>true,
    "message"=>"Login Successful",
    "role"=>$user["role"],
    "user"=>$user
]);

?> -->