<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST request allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}

// Database connection
include("../../config/db.php");

try {

    /*
    |--------------------------------------------------------------------------
    | Backup Directory
    |--------------------------------------------------------------------------
    */

    $backupDirectory = realpath(__DIR__ . "/../../backups");

    /*
    | If backup directory does not exist,
    | create it.
    */

    if ($backupDirectory === false) {

        $backupDirectory = __DIR__ . "/../../backups";

        if (!is_dir($backupDirectory)) {

            if (!mkdir($backupDirectory, 0755, true)) {

                throw new Exception(
                    "Unable to create backup directory."
                );
            }
        }

        $backupDirectory = realpath($backupDirectory);
    }

    if ($backupDirectory === false) {

        throw new Exception(
            "Backup directory is not accessible."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check mysqldump
    |--------------------------------------------------------------------------
    */

    $mysqldumpPath = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

    if (!file_exists($mysqldumpPath)) {

        throw new Exception(
            "mysqldump.exe was not found. Please check your XAMPP MySQL installation."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Backup Filename
    |--------------------------------------------------------------------------
    */

    $dateTime = date("Y-m-d_H-i-s");

    $fileName =
        "future_academy_backup_" .
        $dateTime .
        ".sql";


    /*
    |--------------------------------------------------------------------------
    | Full Backup File Path
    |--------------------------------------------------------------------------
    */

    $backupFile = $backupDirectory .
        DIRECTORY_SEPARATOR .
        $fileName;


    /*
    |--------------------------------------------------------------------------
    | Database Credentials
    |--------------------------------------------------------------------------
    */

    $dbHost = $host;
    $dbUser = $user;
    $dbPassword = $password;
    $dbName = $database;


    /*
    |--------------------------------------------------------------------------
    | Build mysqldump Command
    |--------------------------------------------------------------------------
    */

    $command =
        '"' . $mysqldumpPath . '"' .
        ' --host=' . escapeshellarg($dbHost) .
        ' --user=' . escapeshellarg($dbUser) .
        ' --single-transaction' .
        ' --quick' .
        ' --routines' .
        ' --triggers' .
        ' --events' .
        ' --default-character-set=utf8mb4';


    /*
    | Password
    |
    | Your current database password is empty.
    | Therefore we don't add a password argument.
    */

    if ($dbPassword !== '') {

        $command .=
            ' --password=' . escapeshellarg($dbPassword);
    }


    /*
    | Database name
    */

    $command .=
        ' ' . escapeshellarg($dbName);


    /*
    |--------------------------------------------------------------------------
    | Output SQL File
    |--------------------------------------------------------------------------
    */

    $command .=
        ' > "' . $backupFile . '"';


    /*
    |--------------------------------------------------------------------------
    | Execute mysqldump
    |--------------------------------------------------------------------------
    */

    $output = [];

    $exitCode = 0;

    exec(
        $command . ' 2>&1',
        $output,
        $exitCode
    );


    /*
    |--------------------------------------------------------------------------
    | Check Backup Result
    |--------------------------------------------------------------------------
    */

    if ($exitCode !== 0) {

        /*
        | Delete incomplete backup file
        */

        if (file_exists($backupFile)) {
            unlink($backupFile);
        }

        $errorMessage = !empty($output)
            ? implode("\n", $output)
            : "Unknown mysqldump error.";

        throw new Exception(
            "Database backup failed. " . $errorMessage
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check Backup File
    |--------------------------------------------------------------------------
    */

    if (!file_exists($backupFile)) {

        throw new Exception(
            "Backup file was not created."
        );
    }


    /*
    | Check file size
    */

    $fileSize = filesize($backupFile);

    if ($fileSize === false || $fileSize <= 0) {

        unlink($backupFile);

        throw new Exception(
            "Backup file is empty."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Convert File Size
    |--------------------------------------------------------------------------
    */

    function formatFileSize($bytes)
    {

        if ($bytes < 1024) {
            return $bytes . " B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . " KB";
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round(
                $bytes / (1024 * 1024),
                2
            ) . " MB";
        }

        return round(
            $bytes / (1024 * 1024 * 1024),
            2
        ) . " GB";
    }


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "status" => true,
        "message" => "Database backup created successfully.",
        "data" => [
            "file_name" => $fileName,
            "file_size" => formatFileSize($fileSize),
            "created_at" => date("Y-m-d H:i:s")
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Unable to create database backup.",
        "error" => $e->getMessage()
    ]);

}

$conn->close();

?>