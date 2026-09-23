```php
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
        "message" => "Invalid Request"
    ]);

    exit;
}

// GET JSON DATA

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

$studentId = intval(
    $data["id"] ?? 0
);

$deleteParent = isset($data["delete_parent"])
    ? (bool)$data["delete_parent"]
    : false;

$forceDeleteStudent = isset($data["force_delete_student"])
    ? (bool)$data["force_delete_student"]
    : false;

// VALIDATE STUDENT ID

if ($studentId <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid Student ID"
    ]);

    exit;
}

try {
    // GET STUDENT

    $studentStmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id
         FROM students
         WHERE id = ?
         LIMIT 1"
    );

    if (!$studentStmt) {

        throw new Exception(
            "Student query preparation failed: " .
            mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $studentStmt,
        "i",
        $studentId
    );

    if (!mysqli_stmt_execute($studentStmt)) {

        throw new Exception(
            mysqli_stmt_error($studentStmt)
        );
    }

    // GET RESULT WITHOUT mysqli_stmt_get_result()

    mysqli_stmt_bind_result(
        $studentStmt,
        $foundStudentId,
        $studentUserId
    );


    if (!mysqli_stmt_fetch($studentStmt)) {

        mysqli_stmt_close($studentStmt);

        echo json_encode([
            "status" => false,
            "message" => "Student not found"
        ]);

        exit;
    }

    $studentUserId = intval($studentUserId);

    mysqli_stmt_close($studentStmt);

    // FIND LINKED PARENT

    $parentStmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id
         FROM parents
         WHERE student_id = ?
         LIMIT 1"
    );

    if (!$parentStmt) {

        throw new Exception(
            "Parent query preparation failed: " .
            mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $parentStmt,
        "i",
        $studentId
    );


    if (!mysqli_stmt_execute($parentStmt)) {

        throw new Exception(
            mysqli_stmt_error($parentStmt)
        );
    }

    // GET PARENT WITHOUT mysqli_stmt_get_result()

    mysqli_stmt_bind_result(
        $parentStmt,
        $parentId,
        $parentUserId
    );

    $parentExists = mysqli_stmt_fetch($parentStmt);

    if ($parentExists) {

        $parentId = intval($parentId);
        $parentUserId = intval($parentUserId);

    } else {

        $parentId = 0;
        $parentUserId = 0;
    }

    mysqli_stmt_close($parentStmt);

    // PARENT CONFIRMATION

    if (
        $parentExists &&
        !$deleteParent &&
        !$forceDeleteStudent
    ) {

        echo json_encode([

            "status" => false,

            "requires_parent_confirmation" => true,

            "message" =>
                "This student has a linked parent. Do you also want to delete the parent?"

        ]);

        exit;
    }

    // START TRANSACTION

    mysqli_begin_transaction($conn);

    // OPTION 1
    // DELETE PARENT + PARENT USER

    if (
        $parentExists &&
        $deleteParent
    ) {

        // Delete Parent Record

        $deleteParentStmt = mysqli_prepare(
            $conn,
            "DELETE FROM parents
             WHERE id = ?"
        );

        if (!$deleteParentStmt) {

            throw new Exception(
                "Parent delete preparation failed"
            );
        }

        mysqli_stmt_bind_param(
            $deleteParentStmt,
            "i",
            $parentId
        );

        if (!mysqli_stmt_execute($deleteParentStmt)) {

            throw new Exception(
                mysqli_stmt_error($deleteParentStmt)
            );
        }

        mysqli_stmt_close($deleteParentStmt);

        // Delete Parent Login

        if ($parentUserId > 0) {

            $deleteParentUserStmt = mysqli_prepare(
                $conn,
                "DELETE FROM users
                 WHERE id = ?
                 AND role = 'parent'"
            );

            if (!$deleteParentUserStmt) {

                throw new Exception(
                    "Parent user delete preparation failed"
                );
            }

            mysqli_stmt_bind_param(
                $deleteParentUserStmt,
                "i",
                $parentUserId
            );

            if (!mysqli_stmt_execute($deleteParentUserStmt)) {

                throw new Exception(
                    mysqli_stmt_error($deleteParentUserStmt)
                );
            }

            mysqli_stmt_close($deleteParentUserStmt);
        }
    }

    // OPTION 2
    // KEEP PARENT BUT UNLINK STUDENT

    if (
        $parentExists &&
        !$deleteParent &&
        $forceDeleteStudent
    ) {

        $unlinkParentStmt = mysqli_prepare(
            $conn,
            "UPDATE parents
             SET student_id = NULL
             WHERE id = ?"
        );

        if (!$unlinkParentStmt) {

            throw new Exception(
                "Parent unlink preparation failed"
            );
        }

        mysqli_stmt_bind_param(
            $unlinkParentStmt,
            "i",
            $parentId
        );


        if (!mysqli_stmt_execute($unlinkParentStmt)) {

            throw new Exception(
                mysqli_stmt_error($unlinkParentStmt)
            );
        }


        mysqli_stmt_close($unlinkParentStmt);
    }

    // DELETE STUDENT RECORD

    $deleteStudentStmt = mysqli_prepare(
        $conn,
        "DELETE FROM students
         WHERE id = ?"
    );

    if (!$deleteStudentStmt) {

        throw new Exception(
            "Student delete preparation failed"
        );
    }

    mysqli_stmt_bind_param(
        $deleteStudentStmt,
        "i",
        $studentId
    );

    if (!mysqli_stmt_execute($deleteStudentStmt)) {

        throw new Exception(
            mysqli_stmt_error($deleteStudentStmt)
        );
    }

    mysqli_stmt_close($deleteStudentStmt);

    // DELETE STUDENT LOGIN

    if ($studentUserId > 0) {

        $deleteStudentUserStmt = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE id = ?
             AND role = 'student'"
        );

        if (!$deleteStudentUserStmt) {

            throw new Exception(
                "Student user delete preparation failed"
            );
        }

        mysqli_stmt_bind_param(
            $deleteStudentUserStmt,
            "i",
            $studentUserId
        );

        if (!mysqli_stmt_execute($deleteStudentUserStmt)) {

            throw new Exception(
                mysqli_stmt_error($deleteStudentUserStmt)
            );
        }

        mysqli_stmt_close($deleteStudentUserStmt);
    }

    // COMMIT

    mysqli_commit($conn);

    // RESPONSE

    if (
        $parentExists &&
        $deleteParent
    ) {

        echo json_encode([

            "status" => true,

            "message" =>
                "Student and linked parent deleted successfully"

        ]);

    } else {

        echo json_encode([

            "status" => true,

            "message" =>
                "Student deleted successfully. Linked parent was kept."

        ]);
    }


} catch (Exception $e) {

    // ROLLBACK

    mysqli_rollback($conn);

    echo json_encode([

        "status" => false,

        "message" => $e->getMessage()

    ]);
}
?>
