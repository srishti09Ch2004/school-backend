<?php

header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: POST, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type");


/*
|--------------------------------------------------------------------------
| Handle CORS preflight
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


/*
|--------------------------------------------------------------------------
| Only POST request allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

include("../../config/db.php");


try {

    /*
    |--------------------------------------------------------------------------
    | Check uploaded file
    |--------------------------------------------------------------------------
    */

    if (!isset($_FILES['backup_file'])) {

        throw new Exception(
            "No backup file was uploaded."
        );
    }


    $file = $_FILES['backup_file'];


    /*
    |--------------------------------------------------------------------------
    | Check upload error
    |--------------------------------------------------------------------------
    */

    if ($file['error'] !== UPLOAD_ERR_OK) {

        throw new Exception(
            "File upload failed. Error code: " . $file['error']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | File information
    |--------------------------------------------------------------------------
    */

    $originalName = $file['name'];

    $tmpName = $file['tmp_name'];

    $fileSize = $file['size'];


    /*
    |--------------------------------------------------------------------------
    | Validate file extension
    |--------------------------------------------------------------------------
    */

    $extension = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );


    if ($extension !== "sql") {

        throw new Exception(
            "Invalid backup file. Only .sql files are allowed."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum file size
    |--------------------------------------------------------------------------
    |
    | 50 MB
    |
    */

    $maxFileSize = 50 * 1024 * 1024;


    if ($fileSize > $maxFileSize) {

        throw new Exception(
            "Backup file is too large. Maximum allowed size is 50 MB."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check temporary file
    |--------------------------------------------------------------------------
    */

    if (!is_uploaded_file($tmpName)) {

        throw new Exception(
            "Invalid uploaded file."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Read SQL file
    |--------------------------------------------------------------------------
    */

    $sql = file_get_contents($tmpName);


    if ($sql === false) {

        throw new Exception(
            "Unable to read backup file."
        );
    }


    if (trim($sql) === "") {

        throw new Exception(
            "Backup file is empty."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Basic SQL validation
    |--------------------------------------------------------------------------
    */

    $sqlLower = strtolower($sql);


    if (
        strpos($sqlLower, "create table") === false &&
        strpos($sqlLower, "insert into") === false &&
        strpos($sqlLower, "alter table") === false
    ) {

        throw new Exception(
            "The uploaded file does not appear to be a valid database backup."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Disable foreign key checks
    |--------------------------------------------------------------------------
    */

    if (!$conn->query("SET FOREIGN_KEY_CHECKS = 0")) {

        throw new Exception(
            "Unable to disable foreign key checks."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Execute SQL backup
    |--------------------------------------------------------------------------
    */

    if (!$conn->multi_query($sql)) {

        throw new Exception(
            "Database restore failed: " . $conn->error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Process all SQL results
    |--------------------------------------------------------------------------
    */

    do {

        if ($result = $conn->store_result()) {

            $result->free();
        }

    } while ($conn->more_results() && $conn->next_result());


    /*
    |--------------------------------------------------------------------------
    | Check SQL execution error
    |--------------------------------------------------------------------------
    */

    if ($conn->errno) {

        throw new Exception(
            "Database restore failed: " . $conn->error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Enable foreign key checks
    |--------------------------------------------------------------------------
    */

    if (!$conn->query("SET FOREIGN_KEY_CHECKS = 1")) {

        throw new Exception(
            "Unable to enable foreign key checks."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "status" => true,

        "message" => "Database restored successfully.",

        "data" => [

            "file_name" => $originalName,

            "file_size" => round(
                $fileSize / 1024,
                2
            ) . " KB"

        ]

    ]);


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | Make sure foreign keys are enabled again
    |--------------------------------------------------------------------------
    */

    $conn->query(
        "SET FOREIGN_KEY_CHECKS = 1"
    );


    /*
    |--------------------------------------------------------------------------
    | Error response
    |--------------------------------------------------------------------------
    */

    http_response_code(500);


    echo json_encode([

        "status" => false,

        "message" => "Database restore failed.",

        "error" => $e->getMessage()

    ]);

}


/*
|--------------------------------------------------------------------------
| Close database connection
|--------------------------------------------------------------------------
*/

$conn->close();

?>