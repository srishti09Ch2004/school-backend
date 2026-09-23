<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

try {

    $sql = "
        SELECT

            p.id,
            p.user_id,
            p.student_id,
            p.father_name,
            p.mother_name,
            p.phone,
            p.occupation,
            p.address,

            /* Parent account information */
            pu.full_name AS parent_name,
            pu.email AS parent_email,

            /* Student information */
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

            /* Student name/email */
            su.full_name AS child_name,
            su.email AS child_email

        FROM parents p

        /* Parent → Users */
        LEFT JOIN users pu
            ON p.user_id = pu.id

        /* Parent → Student */
        LEFT JOIN students s
            ON p.student_id = s.id

        /* Student → Users */
        LEFT JOIN users su
            ON s.user_id = su.id

        ORDER BY p.id DESC
    ";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }

    $parents = [];

    while ($row = mysqli_fetch_assoc($result)) {

        /*
         * Relation is calculated from father/mother names
         */

        if (
            !empty($row["father_name"]) &&
            !empty($row["mother_name"])
        ) {

            $relation = "Father & Mother";

        } elseif (!empty($row["father_name"])) {

            $relation = "Father";

        } elseif (!empty($row["mother_name"])) {

            $relation = "Mother";

        } else {

            $relation = "Guardian";

        }


        /*
         * Parent is considered active
         * when a student is linked.
         */

        $status = !empty($row["student_id"])
            ? "Active"
            : "Pending";


        $parents[] = [

            "id" => (int)$row["id"],

            "user_id" => $row["user_id"] !== null
                ? (int)$row["user_id"]
                : null,

            "student_id" => $row["student_id"] !== null
                ? (int)$row["student_id"]
                : null,


            /* Parent */

            "name" => $row["parent_name"] ?? "Parent",

            "email" => $row["parent_email"] ?? "",

            "father_name" => $row["father_name"] ?? "",

            "mother_name" => $row["mother_name"] ?? "",

            "relation" => $relation,

            "phone" => $row["phone"] ?? "",

            "occupation" => $row["occupation"] ?? "",

            "address" => $row["address"] ?? "",


            /* Student */

            "student" => $row["child_name"] ?? "Not Linked",

            "student_email" => $row["child_email"] ?? "",

            "student_admission" =>
                $row["child_admission"] ?? "",

            "student_class" =>
                $row["child_class"] ?? "",

            "student_section" =>
                $row["child_section"] ?? "",

            "student_roll_no" =>
                $row["child_roll_no"] ?? "",

            "student_gender" =>
                $row["child_gender"] ?? "",

            "student_dob" =>
                $row["child_dob"] ?? "",

            "student_phone" =>
                $row["child_phone"] ?? "",

            "student_status" =>
                $row["child_status"] ?? "",


            /* Parent status */

            "status" => $status
        ];
    }


    echo json_encode([

        "status" => true,

        "message" =>
            "Parents fetched successfully",

        "total" =>
            count($parents),

        "parents" =>
            $parents

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