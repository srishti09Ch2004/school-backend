<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON data."
    ]);

    exit;
}

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | SCHOOL SETTINGS
    |--------------------------------------------------------------------------
    */

    if (isset($data['school'])) {

        $school = $data['school'];

        $schoolName = trim($school['school_name'] ?? '');
        $schoolAddress = trim($school['school_address'] ?? '');
        $schoolPhone = trim($school['school_phone'] ?? '');
        $schoolEmail = trim($school['school_email'] ?? '');
        $schoolWebsite = trim($school['school_website'] ?? '');
        $principalName = trim($school['principal_name'] ?? '');
        $logo = trim($school['logo'] ?? '');

        /*
        | Get existing school settings row
        */

        $checkSchool = $conn->query(
            "SELECT id FROM school_settings ORDER BY id DESC LIMIT 1"
        );

        if (!$checkSchool) {
            throw new Exception(
                "Unable to check school settings."
            );
        }

        if ($checkSchool->num_rows > 0) {

            $schoolRow = $checkSchool->fetch_assoc();
            $schoolId = (int)$schoolRow['id'];

            $stmt = $conn->prepare(
                "UPDATE school_settings
                 SET
                    school_name = ?,
                    school_address = ?,
                    school_phone = ?,
                    school_email = ?,
                    school_website = ?,
                    principal_name = ?,
                    logo = ?
                 WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare school update."
                );
            }

            $stmt->bind_param(
                "sssssssi",
                $schoolName,
                $schoolAddress,
                $schoolPhone,
                $schoolEmail,
                $schoolWebsite,
                $principalName,
                $logo,
                $schoolId
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Failed to update school settings."
                );
            }

            $stmt->close();

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO school_settings
                (
                    school_name,
                    school_address,
                    school_phone,
                    school_email,
                    school_website,
                    principal_name,
                    logo
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare school insert."
                );
            }

            $stmt->bind_param(
                "sssssss",
                $schoolName,
                $schoolAddress,
                $schoolPhone,
                $schoolEmail,
                $schoolWebsite,
                $principalName,
                $logo
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Failed to create school settings."
                );
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SYSTEM SETTINGS
    |--------------------------------------------------------------------------
    */

    if (isset($data['system']) && is_array($data['system'])) {

        foreach ($data['system'] as $key => $value) {

            /*
            | Do not save school information inside system_settings.
            | School information belongs to school_settings table.
            */

            $schoolKeys = [
                "school_name",
                "school_code",
                "school_email",
                "school_phone",
                "school_address"
            ];

            if (in_array($key, $schoolKeys, true)) {
                continue;
            }


            /*
            | Convert JS values to database strings
            */

            if (is_bool($value)) {
                $value = $value ? "1" : "0";
                $type = "boolean";

            } elseif (is_int($value) || is_float($value)) {
                $value = (string)$value;
                $type = "number";

            } elseif (is_array($value)) {
                $value = json_encode($value);
                $type = "json";

            } else {
                $value = (string)$value;
                $type = "string";
            }


            /*
            | Check whether setting already exists
            */

            $checkStmt = $conn->prepare(
                "SELECT id
                 FROM system_settings
                 WHERE setting_key = ?
                 LIMIT 1"
            );

            if (!$checkStmt) {
                throw new Exception(
                    "Failed to check system setting."
                );
            }

            $checkStmt->bind_param("s", $key);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            $checkStmt->close();


            /*
            | UPDATE existing setting
            */

            if ($checkResult->num_rows > 0) {

                $existing = $checkResult->fetch_assoc();
                $settingId = (int)$existing['id'];

                $stmt = $conn->prepare(
                    "UPDATE system_settings
                     SET
                        setting_value = ?,
                        setting_type = ?,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Failed to prepare system update."
                    );
                }

                $stmt->bind_param(
                    "ssi",
                    $value,
                    $type,
                    $settingId
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Failed to update setting: " . $key
                    );
                }

                $stmt->close();

            }

            /*
            | INSERT new setting
            */

            else {

                $category = "general";

                $stmt = $conn->prepare(
                    "INSERT INTO system_settings
                    (
                        setting_key,
                        setting_value,
                        setting_type,
                        category
                    )
                    VALUES (?, ?, ?, ?)"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Failed to prepare system insert."
                    );
                }

                $stmt->bind_param(
                    "ssss",
                    $key,
                    $value,
                    $type,
                    $category
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Failed to create setting: " . $key
                    );
                }

                $stmt->close();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "status" => true,
        "message" => "Settings updated successfully."
    ]);

} catch (Exception $e) {

    /*
    | Rollback if anything fails
    */

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to update settings.",
        "error" => $e->getMessage()
    ]);
}

$conn->close();

?>