<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");


/* Only POST allowed */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid Request"
    ]);

    exit;
}


/* Get JSON */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


if (!$data) {

    echo json_encode([
        "status" => false,
        "message" => "No data received"
    ]);

    exit;
}


/* Parent ID */

$id = intval($data["id"] ?? 0);


/* User ID */

$user_id = intval($data["user_id"] ?? 0);


/* Parent fields */

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");

$father_name = trim(
    $data["father_name"] ?? ""
);

$mother_name = trim(
    $data["mother_name"] ?? ""
);

$phone = trim(
    $data["phone"] ?? ""
);

$occupation = trim(
    $data["occupation"] ?? ""
);

$address = trim(
    $data["address"] ?? ""
);


/* Validation */

if ($id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid Parent ID"
    ]);

    exit;
}


if (
    empty($name) ||
    empty($email)
) {

    echo json_encode([
        "status" => false,
        "message" => "Parent name and email are required"
    ]);

    exit;
}


try {

    /*
     * Check parent exists
     */

    $checkParent = mysqli_prepare(
        $conn,
        "SELECT id, user_id FROM parents WHERE id = ? LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $checkParent,
        "i",
        $id
    );

    mysqli_stmt_execute(
        $checkParent
    );

    $parentResult =
        mysqli_stmt_get_result($checkParent);


    if (
        mysqli_num_rows($parentResult) === 0
    ) {

        echo json_encode([
            "status" => false,
            "message" => "Parent not found"
        ]);

        exit;
    }


    $parentRow =
        mysqli_fetch_assoc($parentResult);


    /*
     * Get actual user ID
     */

    $actualUserId =
        intval($parentRow["user_id"]);


    /*
     * Check duplicate email
     *
     * Don't allow another user
     * to use same email.
     */

    $emailCheck = mysqli_prepare(
        $conn,
        "SELECT id FROM users
         WHERE email = ?
         AND id != ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $emailCheck,
        "si",
        $email,
        $actualUserId
    );

    mysqli_stmt_execute(
        $emailCheck
    );

    $emailResult =
        mysqli_stmt_get_result($emailCheck);


    if (
        mysqli_num_rows($emailResult) > 0
    ) {

        echo json_encode([
            "status" => false,
            "message" => "Email already exists"
        ]);

        exit;
    }


    /*
     * Start transaction
     */

    mysqli_begin_transaction($conn);


    /*
     * Update users table
     */

    $userUpdate = mysqli_prepare(
        $conn,
        "UPDATE users
         SET full_name = ?, email = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $userUpdate,
        "ssi",
        $name,
        $email,
        $actualUserId
    );

    if (
        !mysqli_stmt_execute($userUpdate)
    ) {

        throw new Exception(
            mysqli_stmt_error($userUpdate)
        );
    }


    /*
     * Update parents table
     */

    $parentUpdate = mysqli_prepare(
        $conn,
        "UPDATE parents
         SET
            father_name = ?,
            mother_name = ?,
            phone = ?,
            occupation = ?,
            address = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $parentUpdate,
        "sssssi",
        $father_name,
        $mother_name,
        $phone,
        $occupation,
        $address,
        $id
    );


    if (
        !mysqli_stmt_execute($parentUpdate)
    ) {

        throw new Exception(
            mysqli_stmt_error($parentUpdate)
        );
    }


    /*
     * Commit
     */

    mysqli_commit($conn);


    echo json_encode([

        "status" => true,

        "message" =>
            "Parent updated successfully"

    ]);


} catch (Exception $e) {

    /*
     * Rollback
     */

    mysqli_rollback($conn);


    http_response_code(500);


    echo json_encode([

        "status" => false,

        "message" =>
            $e->getMessage()

    ]);

}

?>