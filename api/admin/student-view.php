<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

include("../../config/db.php");

// GET STUDENT ID

$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid student ID"
    ]);

    exit;
}

// GET STUDENT + PARENT INFORMATION

$sql = "

SELECT

    students.id,
    students.user_id,

    users.full_name,
    users.email,

    students.admission_no,
    students.admission_date,

    students.class,
    students.section,
    students.roll_no,

    students.gender,
    students.dob,

    students.phone,
    students.address,

    students.status,

    parents.id AS parent_id,
    parents.user_id AS parent_user_id,
    parents.student_id,

    parents.father_name,
    parents.mother_name,

    parents.phone AS parent_phone,
    parents.occupation,
    parents.address AS parent_address,

    parent_users.full_name AS parent_name,
    parent_users.email AS parent_email,

    CASE

        WHEN parents.father_name IS NOT NULL
             AND parents.mother_name IS NOT NULL
             AND parents.father_name != ''
             AND parents.mother_name != ''

        THEN 'Father & Mother'

        WHEN parents.father_name IS NOT NULL
             AND parents.father_name != ''

        THEN 'Father'

        WHEN parents.mother_name IS NOT NULL
             AND parents.mother_name != ''

        THEN 'Mother'

        ELSE 'Guardian'

    END AS parent_relation

FROM students

INNER JOIN users
    ON students.user_id = users.id

LEFT JOIN parents
    ON students.id = parents.student_id

LEFT JOIN users AS parent_users
    ON parents.user_id = parent_users.id

WHERE students.id = ?

LIMIT 1

";

// PREPARE

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Database statement failed",
        "error" => mysqli_error($conn)
    ]);

    exit;
}

// BIND ID

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

// EXECUTE

if (!mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => false,
        "message" => "Failed to execute query",
        "error" => mysqli_stmt_error($stmt)
    ]);

    exit;
}

// GET RESULT WITHOUT mysqli_stmt_get_result()

mysqli_stmt_bind_result(
    $stmt,

    $student_id,
    $user_id,

    $full_name,
    $email,

    $admission_no,
    $admission_date,

    $class,
    $section,
    $roll_no,

    $gender,
    $dob,

    $phone,
    $address,

    $status,

    $parent_id,
    $parent_user_id,
    $parent_student_id,

    $father_name,
    $mother_name,

    $parent_phone,
    $occupation,
    $parent_address,

    $parent_name,
    $parent_email,

    $parent_relation
);

// FETCH

if (mysqli_stmt_fetch($stmt)) {

    $student = [

        "id" => $student_id,
        "user_id" => $user_id,

        "full_name" => $full_name,
        "email" => $email,

        "admission_no" => $admission_no,
        "admission_date" => $admission_date,

        "class" => $class,
        "section" => $section,
        "roll_no" => $roll_no,

        "gender" => $gender,
        "dob" => $dob,

        "phone" => $phone,
        "address" => $address,

        "status" => $status,

        "parent_id" => $parent_id,
        "parent_user_id" => $parent_user_id,
        "student_id" => $parent_student_id,

        "father_name" => $father_name,
        "mother_name" => $mother_name,

        "parent_phone" => $parent_phone,
        "occupation" => $occupation,
        "parent_address" => $parent_address,

        "parent_name" => $parent_name,
        "parent_email" => $parent_email,

        "parent_relation" => $parent_relation
    ];


    echo json_encode([

        "status" => true,

        "message" => "Student details fetched successfully",

        "data" => $student

    ]);

} else {

    echo json_encode([

        "status" => false,

        "message" => "Student not found"

    ]);
}

// CLOSE

mysqli_stmt_close($stmt);

?>