<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");


/* Check Parent ID */

if (
    !isset($_GET["id"]) ||
    empty($_GET["id"])
) {

    echo json_encode([

        "status" => false,

        "message" =>
            "Parent ID is required"

    ]);

    exit;
}


$parentId = intval($_GET["id"]);


try {


    /* Parent + Student + User Information  */

    $sql = "

        SELECT

            /* Parent table */

            p.id,
            p.user_id,
            p.student_id,
            p.father_name,
            p.mother_name,
            p.phone,
            p.occupation,
            p.address,


            /* Parent user */

            pu.full_name AS parent_name,
            pu.email AS parent_email,


            /* Student */

            s.id AS child_id,
            s.user_id AS child_user_id,
            s.admission_no AS child_admission,
            s.class AS child_class,
            s.section AS child_section,
            s.roll_no AS child_roll_no,
            s.gender AS child_gender,
            s.dob AS child_dob,
            s.phone AS child_phone,
            s.address AS child_address,
            s.status AS child_status,


            /* Student user */

            su.full_name AS child_name,
            su.email AS child_email


        FROM parents p


        /* Parent → User */

        LEFT JOIN users pu

            ON p.user_id = pu.id


        /* Parent → Student */

        LEFT JOIN students s

            ON p.student_id = s.id


        /* Student → User */

        LEFT JOIN users su

            ON s.user_id = su.id


        WHERE p.id = ?

        LIMIT 1

    ";


    /* Prepare Query */

    $stmt = mysqli_prepare(
        $conn,
        $sql
    );


    if (!$stmt) {

        throw new Exception(
            mysqli_error($conn)
        );

    }


    /* Bind Parent ID  */

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $parentId
    );


    /* Execute  */

    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    if (!$result) {

        throw new Exception(
            mysqli_error($conn)
        );

    }


    /* Parent Not Found  */

    if (
        mysqli_num_rows($result) === 0
    ) {

        echo json_encode([

            "status" => false,

            "message" =>
                "Parent not found"

        ]);

        exit;

    }


    /* Get Data  */

    $row =
        mysqli_fetch_assoc($result);


    /* Determine Relation  */

    if (
        !empty($row["father_name"]) &&
        !empty($row["mother_name"])
    ) {

        $relation =
            "Father & Mother";

    } elseif (
        !empty($row["father_name"])
    ) {

        $relation =
            "Father";

    } elseif (
        !empty($row["mother_name"])
    ) {

        $relation =
            "Mother";

    } else {

        $relation =
            "Guardian";

    }


    /* Response  */

    echo json_encode([

        "status" => true,

        "message" =>
            "Parent details fetched successfully",


        /* Parent  */

        "parent" => [

            "id" =>
                (int)$row["id"],

            "user_id" =>
                $row["user_id"] !== null
                    ? (int)$row["user_id"]
                    : null,

            "student_id" =>
                $row["student_id"] !== null
                    ? (int)$row["student_id"]
                    : null,


            "name" =>
                $row["parent_name"]
                ?? "Parent",

            "email" =>
                $row["parent_email"]
                ?? "",


            "father_name" =>
                $row["father_name"]
                ?? "",

            "mother_name" =>
                $row["mother_name"]
                ?? "",


            "relation" =>
                $relation,


            "phone" =>
                $row["phone"]
                ?? "",

            "occupation" =>
                $row["occupation"]
                ?? "",

            "address" =>
                $row["address"]
                ?? ""

        ],


        /* Child   */

        "child" => [

            "id" =>
                $row["child_id"] !== null
                    ? (int)$row["child_id"]
                    : null,

            "user_id" =>
                $row["child_user_id"] !== null
                    ? (int)$row["child_user_id"]
                    : null,


            "name" =>
                $row["child_name"]
                ?? "",

            "email" =>
                $row["child_email"]
                ?? "",


            "admission" =>
                $row["child_admission"]
                ?? "",

            "class" =>
                $row["child_class"]
                ?? "",

            "section" =>
                $row["child_section"]
                ?? "",

            "roll_no" =>
                $row["child_roll_no"]
                ?? "",


            "gender" =>
                $row["child_gender"]
                ?? "",

            "dob" =>
                $row["child_dob"]
                ?? "",


            "phone" =>
                $row["child_phone"]
                ?? "",

            "address" =>
                $row["child_address"]
                ?? "",

            "status" =>
                $row["child_status"]
                ?? ""

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