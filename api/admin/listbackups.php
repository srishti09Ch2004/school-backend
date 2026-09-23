<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode([
        "status" => false,
        "message" => "Only GET method is allowed."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| BACKUP DIRECTORY
|--------------------------------------------------------------------------
*/

$backupDirectory = __DIR__ . "/../../backups/";

/*
|--------------------------------------------------------------------------
| CHECK BACKUP DIRECTORY
|--------------------------------------------------------------------------
*/

if (!is_dir($backupDirectory)) {

    echo json_encode([
        "status" => false,
        "message" => "Backup directory does not exist."
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GET BACKUP FILES
|--------------------------------------------------------------------------
*/

$files = scandir($backupDirectory);

$backups = [];

foreach ($files as $file) {

    /*
    | Skip current and parent directory
    */

    if ($file === "." || $file === "..") {
        continue;
    }

    /*
    | Only allow SQL files
    */

    $extension = strtolower(
        pathinfo($file, PATHINFO_EXTENSION)
    );

    if ($extension !== "sql") {
        continue;
    }

    $fullPath = $backupDirectory . $file;

    /*
    | Make sure it is actually a file
    */

    if (!is_file($fullPath)) {
        continue;
    }

    $fileSize = filesize($fullPath);

    $modifiedTime = filemtime($fullPath);

    $backups[] = [

        "file_name" => $file,

        "file_size" => $fileSize,

        "file_size_kb" =>
            round($fileSize / 1024, 2),

        "file_size_mb" =>
            round($fileSize / 1024 / 1024, 2),

        "created_at" =>
            date("Y-m-d H:i:s", $modifiedTime),

        "timestamp" =>
            $modifiedTime
    ];
}

/*
|--------------------------------------------------------------------------
| SORT NEWEST FIRST
|--------------------------------------------------------------------------
*/

usort(
    $backups,
    function ($a, $b) {

        return $b["timestamp"]
            <=> $a["timestamp"];
    }
);

/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => true,

    "message" =>
        "Backup list fetched successfully.",

    "data" => [

        "total" =>
            count($backups),

        "backups" =>
            $backups
    ]

]);

exit;