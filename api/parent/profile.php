<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

// GET USER ID

$user_id = intval($_GET["user_id"] ?? 0);

if ($user_id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid parent user ID"
    ]);

    exit;
}

try {

//    Parent + Child Information

    $sql = "
        SELECT

            p.id AS parent_id,
            p.user_id AS parent_user_id,
            p.student_id,

            p.father_name,
            p.mother_name,
            p.phone AS parent_phone,
            p.occupation,
            p.address AS parent_address,

            /* Parent User */

            pu.full_name AS parent_name,
            pu.email AS parent_email,

            /* Student */

            s.id AS child_id,
            s.user_id AS child_user_id,
            s.admission_no,
            s.class AS child_class,
            s.section AS child_section,
            s.roll_no,
            s.gender,
            s.dob,
            s.phone AS child_phone,
            s.address AS child_address,
            s.status AS child_status,

            /* Student User */

            su.full_name AS child_name,
            su.email AS child_email

        FROM parents p

        LEFT JOIN users pu
            ON p.user_id = pu.id

        LEFT JOIN students s
            ON p.student_id = s.id

        LEFT JOIN users su
            ON s.user_id = su.id

        WHERE p.user_id = ?

        LIMIT 1
    ";


    $stmt = mysqli_prepare(
        $conn,
        $sql
    );


    if (!$stmt) {

        throw new Exception(
            mysqli_error($conn)
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    if (
        !$result ||
        mysqli_num_rows($result) === 0
    ) {

        echo json_encode([
            "status" => false,
            "message" => "Parent profile not found"
        ]);

        exit;
    }


    $row =
        mysqli_fetch_assoc($result);

// RESPONSE

    echo json_encode([

        "status" => true,

        "message" =>
            "Parent profile fetched successfully",

        "parent" => [

            "id" =>
                (int)$row["parent_id"],

            "user_id" =>
                (int)$row["parent_user_id"],

            "name" =>
                $row["parent_name"] ?? "Parent",

            "email" =>
                $row["parent_email"] ?? "",

            "father_name" =>
                $row["father_name"] ?? "",

            "mother_name" =>
                $row["mother_name"] ?? "",

            "phone" =>
                $row["parent_phone"] ?? "",

            "occupation" =>
                $row["occupation"] ?? "",

            "address" =>
                $row["parent_address"] ?? "",

        ],

        "student" => [

            "id" =>
                $row["child_id"] !== null
                    ? (int)$row["child_id"]
                    : null,

            "user_id" =>
                $row["child_user_id"] !== null
                    ? (int)$row["child_user_id"]
                    : null,

            "full_name" =>
                $row["child_name"] ?? "Not Linked",

            "email" =>
                $row["child_email"] ?? "",

            "admission_no" =>
                $row["admission_no"] ?? "",

            "class" =>
                $row["child_class"] ?? "",

            "section" =>
                $row["child_section"] ?? "",

            "roll_no" =>
                $row["roll_no"] ?? "",

            "gender" =>
                $row["gender"] ?? "",

            "dob" =>
                $row["dob"] ?? "",

            "phone" =>
                $row["child_phone"] ?? "",

            "address" =>
                $row["child_address"] ?? "",

            "status" =>
                $row["child_status"] ?? ""

        ]

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            $e->getMessage()

    ]);
}
?>