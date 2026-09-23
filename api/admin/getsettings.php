<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    /*
    |--------------------------------------------------------------------------
    | School Settings
    |--------------------------------------------------------------------------
    */

    $school = [];

    $schoolSql = "SELECT
                    id,
                    school_name,
                    school_address,
                    school_phone,
                    school_email,
                    school_website,
                    principal_name,
                    logo
                  FROM school_settings
                  ORDER BY id DESC
                  LIMIT 1";

    $schoolResult = $conn->query($schoolSql);

    if ($schoolResult && $schoolResult->num_rows > 0) {
        $school = $schoolResult->fetch_assoc();
    }


    /*
    |--------------------------------------------------------------------------
    | System Settings
    |--------------------------------------------------------------------------
    */

    $system = [];

    $systemSql = "SELECT
                    setting_key,
                    setting_value,
                    setting_type,
                    category
                  FROM system_settings
                  ORDER BY category, setting_key";

    $systemResult = $conn->query($systemSql);

    if ($systemResult) {

        while ($row = $systemResult->fetch_assoc()) {

            $key = $row['setting_key'];
            $value = $row['setting_value'];
            $type = $row['setting_type'];

            /*
            Convert database values into proper JS values
            */

            if ($type === 'number') {
                $value = is_numeric($value)
                    ? (float)$value
                    : 0;
            }

            elseif ($type === 'boolean') {
                $value = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            elseif ($type === 'json') {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $system[$key] = $value;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "status" => true,
        "data" => [
            "school" => $school,
            "system" => $system
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Unable to fetch settings.",
        "error" => $e->getMessage()
    ]);
}

$conn->close();

?>