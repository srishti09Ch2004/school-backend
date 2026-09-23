<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

ob_start();
ini_set("display_errors", 0);

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Only POST method is allowed");
    }

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($input)) {
        throw new Exception("Invalid request data");
    }

    $notice_id = $input["notice_id"] ?? null;

    // Support both names for backward compatibility
    $user_id =
        $input["user_id"]
        ?? $input["teacher_id"]
        ?? null;

    if (!$notice_id || !is_numeric($notice_id)) {
        throw new Exception("Notice ID is required");
    }

    if (!$user_id || !is_numeric($user_id)) {
        throw new Exception("User ID is required");
    }

    $notice_id = (int)$notice_id;
    $user_id = (int)$user_id;

    /*
     * Verify logged-in user is Teacher
     */
    $userQuery = "
        SELECT id, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ";

    $userStmt = $conn->prepare($userQuery);

    if (!$userStmt) {
        throw new Exception(
            "Unable to verify user: " .
            $conn->error
        );
    }

    $userStmt->bind_param(
        "i",
        $user_id
    );

    $userStmt->execute();

    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();

    $userStmt->close();

    if (!$user) {
        throw new Exception("User not found");
    }

    if (strtolower(trim($user["role"])) !== "teacher") {
        throw new Exception(
            "Only teachers can delete teacher notices"
        );
    }

    /*
     * Verify notice belongs to this Teacher
     */
    $checkQuery = "
        SELECT id
        FROM notices
        WHERE id = ?
          AND created_by = ?
          AND created_role = 'teacher'
        LIMIT 1
    ";

    $checkStmt = $conn->prepare($checkQuery);

    if (!$checkStmt) {
        throw new Exception(
            "Unable to verify notice: " .
            $conn->error
        );
    }

    $checkStmt->bind_param(
        "ii",
        $notice_id,
        $user_id
    );

    $checkStmt->execute();

    $result = $checkStmt->get_result();

    if (!$result->fetch_assoc()) {
        throw new Exception(
            "You are not allowed to delete this notice."
        );
    }

    $checkStmt->close();

    $conn->begin_transaction();

    /*
     * Delete notifications
     */
    $notificationQuery = "
        DELETE FROM notifications
        WHERE notice_id = ?
    ";

    $notificationStmt = $conn->prepare(
        $notificationQuery
    );

    if (!$notificationStmt) {
        throw new Exception(
            "Unable to delete notifications: " .
            $conn->error
        );
    }

    $notificationStmt->bind_param(
        "i",
        $notice_id
    );

    if (!$notificationStmt->execute()) {
        throw new Exception(
            "Failed to delete notifications"
        );
    }

    $notificationStmt->close();

    /*
     * Delete notice targets
     */
    $targetQuery = "
        DELETE FROM notice_targets
        WHERE notice_id = ?
    ";

    $targetStmt = $conn->prepare($targetQuery);

    if (!$targetStmt) {
        throw new Exception(
            "Unable to delete notice targets: " .
            $conn->error
        );
    }

    $targetStmt->bind_param(
        "i",
        $notice_id
    );

    if (!$targetStmt->execute()) {
        throw new Exception(
            "Failed to delete notice targets"
        );
    }

    $targetStmt->close();

    /*
     * Delete notice
     */
    $deleteQuery = "
        DELETE FROM notices
        WHERE id = ?
          AND created_by = ?
          AND created_role = 'teacher'
    ";

    $deleteStmt = $conn->prepare($deleteQuery);

    if (!$deleteStmt) {
        throw new Exception(
            "Unable to delete notice: " .
            $conn->error
        );
    }

    $deleteStmt->bind_param(
        "ii",
        $notice_id,
        $user_id
    );

    if (!$deleteStmt->execute()) {
        throw new Exception(
            "Failed to delete notice: " .
            $deleteStmt->error
        );
    }

    if ($deleteStmt->affected_rows === 0) {
        throw new Exception(
            "Notice could not be deleted."
        );
    }

    $deleteStmt->close();

    $conn->commit();

    ob_clean();

    echo json_encode([
        "status" => true,
        "message" => "Notice deleted successfully"
    ]);

} catch (Exception $e) {

    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    ob_clean();

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>