<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

ini_set("display_errors", 0);
error_reporting(E_ALL);

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Basic User ID
    |--------------------------------------------------------------------------
    */

    $userId = isset($_POST["user_id"])
        ? intval($_POST["user_id"])
        : 0;

    if ($userId <= 0) {
        throw new Exception("Invalid user ID");
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Principal
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
        throw new Exception("Access denied.");
    }

    /*
    |--------------------------------------------------------------------------
    | Read Form Data
    |--------------------------------------------------------------------------
    */

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $qualification = trim($_POST["qualification"] ?? "");
    $experience = trim($_POST["experience"] ?? "");
    $joiningDate = trim($_POST["joining_date"] ?? "");
    $dob = trim($_POST["dob"] ?? "");
    $emergencyContact = trim($_POST["emergency_contact"] ?? "");
    $bloodGroup = trim($_POST["blood_group"] ?? "");
    $address = trim($_POST["address"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Required Validation
    |--------------------------------------------------------------------------
    */

    if ($fullName === "") {
        throw new Exception("Full name is required.");
    }

    if ($email === "") {
        throw new Exception("Email is required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */

    $emailStmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        AND id != ?
        LIMIT 1
    ");

    $emailStmt->bind_param(
        "si",
        $email,
        $userId
    );

    $emailStmt->execute();

    $emailResult = $emailStmt->get_result();

    if ($emailResult->num_rows > 0) {
        throw new Exception(
            "This email address is already registered."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Update users table
    |--------------------------------------------------------------------------
    */

    $userUpdate = $conn->prepare("
        UPDATE users
        SET
            full_name = ?,
            email = ?
        WHERE id = ?
        AND role = 'principal'
    ");

    $userUpdate->bind_param(
        "ssi",
        $fullName,
        $email,
        $userId
    );

    if (!$userUpdate->execute()) {
        throw new Exception(
            "Failed to update user information."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update principals table
    |--------------------------------------------------------------------------
    */

    $profileUpdate = $conn->prepare("
        UPDATE principals
        SET
            qualification = ?,
            experience = ?,
            joining_date = NULLIF(?, ''),
            dob = NULLIF(?, ''),
            phone = ?,
            emergency_contact = ?,
            blood_group = ?,
            address = ?
        WHERE user_id = ?
    ");

    $profileUpdate->bind_param(
        "ssssssssi",
        $qualification,
        $experience,
        $joiningDate,
        $dob,
        $phone,
        $emergencyContact,
        $bloodGroup,
        $address,
        $userId
    );

    if (!$profileUpdate->execute()) {
        throw new Exception(
            "Failed to update principal profile."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Image Upload
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES["profile_photo"]) &&
        $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK
        ) {
            throw new Exception(
                "Profile photo upload failed."
            );
        }

        $file = $_FILES["profile_photo"];

        /*
        |--------------------------------------------------------------------------
        | File Size - Maximum 5 MB
        |--------------------------------------------------------------------------
        */

        if ($file["size"] > 5 * 1024 * 1024) {
            throw new Exception(
                "Profile photo must be less than 5 MB."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate MIME Type
        |--------------------------------------------------------------------------
        */

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file(
            $finfo,
            $file["tmp_name"]
        );

        finfo_close($finfo);

        if (!isset($allowedTypes[$mimeType])) {
            throw new Exception(
                "Only JPG, PNG and WEBP images are allowed."
            );
        }

        $extension = $allowedTypes[$mimeType];

        /*
        |--------------------------------------------------------------------------
        | Upload Folder
        |--------------------------------------------------------------------------
        */

        $uploadDir =
            __DIR__
            . "/../../uploads/principals/";

        if (!is_dir($uploadDir)) {

            if (!mkdir(
                $uploadDir,
                0777,
                true
            )) {
                throw new Exception(
                    "Unable to create upload directory."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Unique Filename
        |--------------------------------------------------------------------------
        */

        $newFileName =
            "principal_"
            . $userId
            . "_"
            . time()
            . "_"
            . bin2hex(random_bytes(4))
            . "."
            . $extension;

        $destination =
            $uploadDir . $newFileName;

        if (!move_uploaded_file(
            $file["tmp_name"],
            $destination
        )) {
            throw new Exception(
                "Unable to save profile photo."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get Existing Photo
        |--------------------------------------------------------------------------
        */

        $oldStmt = $conn->prepare("
            SELECT profile_photo
            FROM principals
            WHERE user_id = ?
            LIMIT 1
        ");

        $oldStmt->bind_param(
            "i",
            $userId
        );

        $oldStmt->execute();

        $oldResult = $oldStmt->get_result();
        $oldData = $oldResult->fetch_assoc();

        $oldPhoto =
            $oldData["profile_photo"] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Save New Photo Path
        |--------------------------------------------------------------------------
        */

        $photoPath =
            "uploads/principals/"
            . $newFileName;

        $photoStmt = $conn->prepare("
            UPDATE principals
            SET profile_photo = ?
            WHERE user_id = ?
        ");

        $photoStmt->bind_param(
            "si",
            $photoPath,
            $userId
        );

        if (!$photoStmt->execute()) {

            if (file_exists($destination)) {
                unlink($destination);
            }

            throw new Exception(
                "Failed to save profile photo information."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Old Photo
        |--------------------------------------------------------------------------
        */

        if (!empty($oldPhoto)) {

            $oldFile =
                __DIR__
                . "/../../"
                . ltrim($oldPhoto, "/");

            if (
                file_exists($oldFile) &&
                is_file($oldFile)
            ) {
                unlink($oldFile);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Return Updated Profile
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

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $updatedProfile = $result->fetch_assoc();

    $profilePhotoUrl = null;

    if (!empty($updatedProfile["profile_photo"])) {

        $profilePhotoUrl =
            "http://localhost/SCHOOL_MANAGEMENT_SYSTEM/backend/"
            . ltrim(
                $updatedProfile["profile_photo"],
                "/"
            );
    }

    $updatedProfile["profile_photo_url"] =
        $profilePhotoUrl;

    echo json_encode([
        "status" => true,
        "message" => "Principal profile updated successfully.",
        "data" => $updatedProfile
    ]);

} catch (Exception $e) {

    if ($conn->connect_errno === 0) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
        }
    }

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}