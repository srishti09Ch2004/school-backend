<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    $classes = [];
    $sections = [];

    /*
    |--------------------------------------------------------------------------
    | GET ALL ACTIVE CLASSES
    |--------------------------------------------------------------------------
    */

    $classSql = "
        SELECT DISTINCT TRIM(class) AS class_name
        FROM students
        WHERE status = 'Active'
          AND class IS NOT NULL
          AND TRIM(class) != ''
    ";

    $classResult = mysqli_query($conn, $classSql);

    if (!$classResult) {
        throw new Exception(
            "Unable to fetch classes: " .
            mysqli_error($conn)
        );
    }

    while ($row = mysqli_fetch_assoc($classResult)) {
        $classes[] = $row["class_name"];
    }

    /*
    |--------------------------------------------------------------------------
    | SORT CLASSES
    |--------------------------------------------------------------------------
    | Numeric classes first:
    | 1,2,3,4,10,11,12
    |--------------------------------------------------------------------------
    */

    usort($classes, function ($a, $b) {

        $aNumeric = is_numeric($a);
        $bNumeric = is_numeric($b);

        if ($aNumeric && $bNumeric) {
            return (int)$a <=> (int)$b;
        }

        if ($aNumeric) {
            return -1;
        }

        if ($bNumeric) {
            return 1;
        }

        return strcasecmp($a, $b);
    });

    /*
    |--------------------------------------------------------------------------
    | GET SECTIONS FOR EACH CLASS
    |--------------------------------------------------------------------------
    */

    $sectionSql = "
        SELECT DISTINCT
            TRIM(class) AS class_name,
            TRIM(section) AS section_name
        FROM students
        WHERE status = 'Active'
          AND class IS NOT NULL
          AND TRIM(class) != ''
          AND section IS NOT NULL
          AND TRIM(section) != ''
    ";

    $sectionResult = mysqli_query($conn, $sectionSql);

    if (!$sectionResult) {
        throw new Exception(
            "Unable to fetch sections: " .
            mysqli_error($conn)
        );
    }

    while ($row = mysqli_fetch_assoc($sectionResult)) {

        $className = $row["class_name"];
        $sectionName = $row["section_name"];

        if (!isset($sections[$className])) {
            $sections[$className] = [];
        }

        if (!in_array(
            $sectionName,
            $sections[$className],
            true
        )) {
            $sections[$className][] = $sectionName;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SORT SECTIONS
    |--------------------------------------------------------------------------
    */

    foreach ($sections as $className => $classSections) {

        usort(
            $classSections,
            function ($a, $b) {
                return strcasecmp($a, $b);
            }
        );

        $sections[$className] = $classSections;
    }

    echo json_encode([
        "status" => true,
        "message" => "Classes and sections fetched successfully.",
        "classes" => $classes,
        "sections" => $sections
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage(),
        "classes" => [],
        "sections" => []
    ]);
}
?>