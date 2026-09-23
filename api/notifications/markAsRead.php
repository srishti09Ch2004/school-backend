<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

ob_start();
ini_set("display_errors", "0");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

function sendJson($data, $code = 200)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($code);
    echo json_encode($data);
    exit;
}

include("../../config/db.php");

try {

    // Only POST is allowed
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        sendJson([
            "status" => false,
            "message" => "Only POST method is allowed"
        ], 405);
    }

    // Read JSON body
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        sendJson([
            "status" => false,
            "message" => "Invalid JSON data"
        ], 400);
    }

    $notification_id = intval($input["notification_id"] ?? 0);
    $user_id = intval($input["user_id"] ?? 0);

    // Validate notification ID
    if ($notification_id <= 0) {
        sendJson([
            "status" => false,
            "message" => "Valid notification_id is required"
        ], 400);
    }

    // Validate user ID
    if ($user_id <= 0) {
        sendJson([
            "status" => false,
            "message" => "Valid user_id is required"
        ], 400);
    }

    // Check notification belongs to user
    $checkStmt = $conn->prepare("
        SELECT id, is_read, read_at
        FROM notifications
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    if (!$checkStmt) {
        throw new Exception($conn->error);
    }

    $checkStmt->bind_param("ii", $notification_id, $user_id);
    $checkStmt->execute();

    $result = $checkStmt->get_result();
    $notification = $result->fetch_assoc();

    $checkStmt->close();

    if (!$notification) {
        sendJson([
            "status" => false,
            "message" => "Notification not found"
        ], 404);
    }

    // Mark notification as read
    if ((int)$notification["is_read"] === 0) {

        $updateStmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = NOW()
            WHERE id = ?
            AND user_id = ?
            AND is_read = 0
        ");

        if (!$updateStmt) {
            throw new Exception($conn->error);
        }

        $updateStmt->bind_param("ii", $notification_id, $user_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Get latest unread count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE user_id = ?
        AND is_read = 0
    ");

    if (!$countStmt) {
        throw new Exception($conn->error);
    }

    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();

    $countResult = $countStmt->get_result();
    $countData = $countResult->fetch_assoc();

    $countStmt->close();

    $unreadCount = intval($countData["unread_count"] ?? 0);

    // Return success response
    sendJson([
        "status" => true,
        "message" => "Notification marked as read",
        "notification_id" => $notification_id,
        "user_id" => $user_id,
        "is_read" => 1,
        "unread_count" => $unreadCount
    ]);

} catch (Throwable $e) {

    sendJson([
        "status" => false,
        "message" => $e->getMessage()
    ], 500);
}

$conn->close();
?>