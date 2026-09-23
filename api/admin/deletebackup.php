<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle CORS preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Only POST allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);
    exit;
}

// Backup folder
$backupFolder = __DIR__ . "/../../backups/";

// Get file name
$fileName = $_POST["file_name"] ?? "";

// If not found in POST, try JSON also
if ($fileName === "") {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $fileName = $input["file_name"] ?? "";
}

if ($fileName === "") {
    echo json_encode([
        "status" => false,
        "message" => "Backup file name is required."
    ]);
    exit;
}

// Security: remove any path
$fileName = basename($fileName);

// Only allow generated backup files
$pattern =
    '/^future_academy_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/';

if (!preg_match($pattern, $fileName)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid backup file."
    ]);
    exit;
}

$filePath = $backupFolder . $fileName;

// Check file exists
if (!file_exists($filePath)) {
    echo json_encode([
        "status" => false,
        "message" => "Backup file not found."
    ]);
    exit;
}

// Delete file
if (!unlink($filePath)) {
    echo json_encode([
        "status" => false,
        "message" => "Unable to delete backup file."
    ]);
    exit;
}

// Success
echo json_encode([
    "status" => true,
    "message" => "Backup deleted successfully.",
    "data" => [
        "file_name" => $fileName
    ]
]);

exit;
?>