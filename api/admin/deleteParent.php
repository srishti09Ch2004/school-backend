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


/* Get JSON data */

$data = json_decode(
    file_get_contents("php://input"),
    true
);


/* Parent ID */

$id = intval($data["id"] ?? 0);


if ($id <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid Parent ID"
    ]);

    exit;
}


try {

    /*
      Get Parent information
      We need user_id because parent
      has an account in users table.
     */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id
         FROM parents
         WHERE id = ?
         LIMIT 1"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    if (
        mysqli_num_rows($result) === 0
    ) {

        echo json_encode([
            "status" => false,
            "message" => "Parent not found"
        ]);

        exit;
    }


    $parent =
        mysqli_fetch_assoc($result);


    $userId =
        intval($parent["user_id"]);


    /*      Start Transaction     */

    mysqli_begin_transaction($conn);


    /*
      1. Delete Parent record
     */

    $deleteParent = mysqli_prepare(
        $conn,
        "DELETE FROM parents
         WHERE id = ?"
    );


    mysqli_stmt_bind_param(
        $deleteParent,
        "i",
        $id
    );


    if (
        !mysqli_stmt_execute($deleteParent)
    ) {

        throw new Exception(
            mysqli_stmt_error($deleteParent)
        );
    }


    /*
     2. Delete Parent user account
     
     */

    if ($userId > 0) {

        $deleteUser = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE id = ?
             AND role = 'parent'"
        );


        mysqli_stmt_bind_param(
            $deleteUser,
            "i",
            $userId
        );


        if (
            !mysqli_stmt_execute($deleteUser)
        ) {

            throw new Exception(
                mysqli_stmt_error($deleteUser)
            );
        }
    }


    /*
      Commit
     */

    mysqli_commit($conn);


    echo json_encode([

        "status" => true,

        "message" =>
            "Parent deleted successfully"

    ]);


} catch (Exception $e) {

    /*
     Rollback if anything fails
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