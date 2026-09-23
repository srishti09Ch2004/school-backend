<?php

header("Content-Type: application/octet-stream");

$backupFolder = __DIR__ . "/../../backups/";

if (!isset($_GET["file"])) {
    http_response_code(400);
    echo "Backup file is required.";
    exit;
}

$fileName = basename($_GET["file"]);

if ($fileName === "") {
    http_response_code(400);
    echo "Invalid backup file.";
    exit;
}

$filePath = $backupFolder . $fileName;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo "Backup file not found.";
    exit;
}

/*
|--------------------------------------------------------------------------
| Security Check
|--------------------------------------------------------------------------
| Only allow SQL backup files created by our backup system.
*/

if (
    !preg_match(
        '/^future_academy_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/',
        $fileName
    )
) {
    http_response_code(403);
    echo "Invalid backup file.";
    exit;
}

header(
    'Content-Disposition: attachment; filename="' .
    $fileName .
    '"'
);

header("Content-Length: " . filesize($filePath));

readfile($filePath);

exit;

?>