<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

ini_set("display_errors", 0);
error_reporting(E_ALL);

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    $userId = isset($_GET["user_id"])
        ? intval($_GET["user_id"])
        : 0;

    if ($userId <= 0) {
        throw new Exception("Invalid user ID");
    }

    /*
    |--------------------------------------------------------------------------
    | Verify that this user is actually a principal
    |--------------------------------------------------------------------------
    */

    $userStmt = $conn->prepare("
        SELECT id, full_name, email, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->bind_param("i", $userId);
    $userStmt->execute();

    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    if ($user["role"] !== "principal") {
        throw new Exception("Access denied. User is not a principal.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get Principal Profile
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.user_id,
            p.employee_id,
            p.designation,
            p.qualification,
            p.experience,
            p.joining_date,
            p.dob,
            p.phone,
            p.emergency_contact,
            p.blood_group,
            p.address,
            p.profile_photo,
            p.updated_at,

            u.full_name,
            u.email,
            u.role,
            u.created_at

        FROM principals p

        INNER JOIN users u
            ON u.id = p.user_id

        WHERE p.user_id = ?

        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();

    if (!$profile) {
        throw new Exception(
            "Principal profile record not found."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Photo URL
    |--------------------------------------------------------------------------
    */

    $profilePhoto = null;

    if (!empty($profile["profile_photo"])) {

        $profilePhoto =
            "http://localhost/SCHOOL_MANAGEMENT_SYSTEM/backend/"
            . ltrim($profile["profile_photo"], "/");
    }

    $profile["profile_photo_url"] = $profilePhoto;

    echo json_encode([
        "status" => true,
        "message" => "Principal profile fetched successfully",
        "data" => $profile
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}