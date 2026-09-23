<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $schoolSql = "
            SELECT
                id,
                school_name,
                school_address,
                school_phone,
                school_email,
                school_website,
                principal_name,
                logo
            FROM school_settings
            ORDER BY id ASC
            LIMIT 1
        ";

        $schoolResult = $conn->query($schoolSql);

        if (!$schoolResult) {
            throw new Exception($conn->error);
        }

        $schoolSettings = $schoolResult->fetch_assoc();

        $systemSql = "
            SELECT
                setting_key,
                setting_value,
                setting_type,
                category
            FROM system_settings
            ORDER BY id ASC
        ";

        $systemResult = $conn->query($systemSql);

        if (!$systemResult) {
            throw new Exception($conn->error);
        }

        $systemSettings = [];

        while ($row = $systemResult->fetch_assoc()) {

            $key = $row['setting_key'];
            $value = $row['setting_value'];
            $type = $row['setting_type'];

            if ($type === 'number') {
                $value = is_numeric($value) ? (int)$value : 0;
            }

            elseif ($type === 'boolean') {

                $value = (
                    $value === '1' ||
                    $value === 'true' ||
                    $value === 'TRUE'
                );
            }

            elseif ($type === 'json') {

                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $systemSettings[$key] = $value;
        }

        echo json_encode([
            "status" => true,
            "data" => [
                "school" => $schoolSettings ?: [],
                "system" => $systemSettings
            ]
        ]);

        exit;
    }


    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' ||
        $_SERVER['REQUEST_METHOD'] === 'PUT'
    ) {

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            throw new Exception("Invalid request data.");
        }

        $conn->begin_transaction();


        if (isset($input['school'])) {

            $school = $input['school'];

            $checkSql = "
                SELECT id
                FROM school_settings
                ORDER BY id ASC
                LIMIT 1
            ";

            $checkResult = $conn->query($checkSql);

            if (!$checkResult) {
                throw new Exception($conn->error);
            }

            $existingSchool = $checkResult->fetch_assoc();

            if ($existingSchool) {

                $schoolId = (int)$existingSchool['id'];

                $stmt = $conn->prepare("
                    UPDATE school_settings
                    SET
                        school_name = ?,
                        school_address = ?,
                        school_phone = ?,
                        school_email = ?,
                        school_website = ?,
                        principal_name = ?,
                        logo = ?
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                $schoolName = $school['school_name'] ?? '';
                $schoolAddress = $school['school_address'] ?? '';
                $schoolPhone = $school['school_phone'] ?? '';
                $schoolEmail = $school['school_email'] ?? '';
                $schoolWebsite = $school['school_website'] ?? '';
                $principalName = $school['principal_name'] ?? '';
                $logo = $school['logo'] ?? '';

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
                    throw new Exception($stmt->error);
                }

                $stmt->close();
            }

           else {

                $stmt = $conn->prepare("
                    INSERT INTO school_settings
                    (
                        school_name,
                        school_address,
                        school_phone,
                        school_email,
                        school_website,
                        principal_name,
                        logo
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                $schoolName = $school['school_name'] ?? '';
                $schoolAddress = $school['school_address'] ?? '';
                $schoolPhone = $school['school_phone'] ?? '';
                $schoolEmail = $school['school_email'] ?? '';
                $schoolWebsite = $school['school_website'] ?? '';
                $principalName = $school['principal_name'] ?? '';
                $logo = $school['logo'] ?? '';

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
                    throw new Exception($stmt->error);
                }

                $stmt->close();
            }
        }


        if (isset($input['system']) && is_array($input['system'])) {

            foreach ($input['system'] as $settingKey => $settingValue) {

                if (is_bool($settingValue)) {
                    $settingValue = $settingValue ? '1' : '0';
                }

                elseif (is_array($settingValue)) {
                    $settingValue = json_encode($settingValue);
                }

                else {
                    $settingValue = (string)$settingValue;
                }

                $stmt = $conn->prepare("
                    UPDATE system_settings
                    SET setting_value = ?
                    WHERE setting_key = ?
                ");

                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ss",
                    $settingValue,
                    $settingKey
                );

                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }

                $stmt->close();
            }
        }

        $conn->commit();

        echo json_encode([
            "status" => true,
            "message" => "Settings saved successfully."
        ]);

        exit;
    }

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed."
    ]);

} catch (Exception $e) {

    if ($conn->in_transaction) {
        $conn->rollback();
    }

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}


$conn->close();

?>