<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

include("../../config/db.php");

// ONLY POST REQUEST

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}

// GET JSON DATA

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {

    echo json_encode([
        "status" => false,
        "message" => "No data received"
    ]);

    exit;
}

// STUDENT DATA

$id = $data["id"] ?? "";

$full_name = trim($data["full_name"] ?? "");
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");

$class = trim($data["class"] ?? "");
$section = trim($data["section"] ?? "");
$roll_no = trim($data["roll_no"] ?? "");
$gender = trim($data["gender"] ?? "");

$dob = trim($data["dob"] ?? "");
$admission_date = trim($data["admission_date"] ?? "");

$phone = trim($data["phone"] ?? "");
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    echo json_encode([
        "status" => false,
        "message" => "Phone number must be exactly 10 digits"
    ]);
    exit;
}
$address = trim($data["address"] ?? "");

$status = $data["status"] ?? "Active";


// VALIDATION
if (
    empty($id) ||
    empty($full_name) ||
    empty($email) ||
    empty($class) ||
    empty($section) ||
    empty($roll_no) ||
    empty($gender) ||
    empty($dob) ||
    empty($admission_date) ||
    empty($phone) ||
    empty($address)
) {

    echo json_encode([
        "status" => false,
        "message" => "Please fill all required student fields"
    ]);

    exit;
}

// UPDATE USERS TABLE

if (!empty($password)) {

    $sql = "
        UPDATE users
        SET
            full_name = ?,
            email = ?,
            password = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $full_name,
        $email,
        $password,
        $id
    );

} else {

    $sql = "
        UPDATE users
        SET
            full_name = ?,
            email = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $full_name,
        $email,
        $id
    );
}

if (!mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to update user",
        "error" => mysqli_stmt_error($stmt)
    ]);

    exit;
}

mysqli_stmt_close($stmt);

// UPDATE STUDENTS TABLE

$sql = "
    UPDATE students
    SET
        admission_date = ?,
        class = ?,
        section = ?,
        roll_no = ?,
        gender = ?,
        dob = ?,
        phone = ?,
        address = ?,
        status = ?
    WHERE user_id = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to prepare student update",
        "error" => mysqli_error($conn)
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "sssssssssi",
    $admission_date,
    $class,
    $section,
    $roll_no,
    $gender,
    $dob,
    $phone,
    $address,
    $status,
    $id
);

if (!mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to update student",
        "error" => mysqli_stmt_error($stmt)
    ]);

    exit;
}

mysqli_stmt_close($stmt);

// RESPONSE

echo json_encode([
    "status" => true,
    "message" => "Student Updated Successfully",
    "data" => [
        "user_id" => $id,
        "full_name" => $full_name,
        "email" => $email,
        "admission_date" => $admission_date,
        "class" => $class,
        "section" => $section,
        "roll_no" => $roll_no,
        "gender" => $gender,
        "dob" => $dob,
        "phone" => $phone,
        "address" => $address,
        "status" => $status
    ]
]);

?>