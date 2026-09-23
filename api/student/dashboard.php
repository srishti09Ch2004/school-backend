<!-- <?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../../config/db.php");

$user_id = $_GET["user_id"] ?? 0;

$sql = "SELECT
users.id,
users.full_name,
users.email,
users.role,
students.class,
students.section,
students.roll_no,
students.admission_no,
students.gender,
students.dob,
students.phone,
students.address
FROM students
JOIN users ON students.user_id = users.id
WHERE users.id = '$user_id'";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){

    $student = mysqli_fetch_assoc($result);

    echo json_encode([
        "status" => true,
        "data" => $student
    ]);

}else{

    echo json_encode([
        "status" => false,
        "message" => "Student not found"
    ]);

}

?> -->


<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

/*
|--------------------------------------------------------------------------
| STUDENT ID
|--------------------------------------------------------------------------
|
| Frontend URL:
| dashboard.php?student_id=5
|
*/

$student_id = isset($_GET["student_id"])
    ? intval($_GET["student_id"])
    : 0;

if ($student_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Valid student_id is required"
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| STUDENT PROFILE
|--------------------------------------------------------------------------
*/

$student_sql = "
SELECT
    students.id,
    students.user_id,
    students.class,
    students.section,
    students.roll_no,
    students.admission_no,
    users.full_name,
    users.email
FROM students
INNER JOIN users
    ON students.user_id = users.id
WHERE students.id = ?
LIMIT 1
";

$stmt = mysqli_prepare($conn, $student_sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$student_result = mysqli_stmt_get_result($stmt);

$student = mysqli_fetch_assoc($student_result);

if (!$student) {

    echo json_encode([
        "status" => false,
        "message" => "Student not found"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$attendance_sql = "
SELECT
    COUNT(*) AS total_days,
    SUM(
        CASE
            WHEN status = 'Present' THEN 1
            ELSE 0
        END
    ) AS present_days
FROM attendance
WHERE student_id = ?
";

$stmt = mysqli_prepare($conn, $attendance_sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$attendance_result = mysqli_stmt_get_result($stmt);

$attendance_data = mysqli_fetch_assoc($attendance_result);

$total_days = intval($attendance_data["total_days"] ?? 0);

$present_days = intval($attendance_data["present_days"] ?? 0);

$attendance_percentage = 0;

if ($total_days > 0) {
    $attendance_percentage =
        round(($present_days / $total_days) * 100);
}


/*
|--------------------------------------------------------------------------
| FEES
|--------------------------------------------------------------------------
*/

$fee_sql = "
SELECT
    COALESCE(SUM(total_fee), 0) AS total_fee,
    COALESCE(SUM(paid_fee), 0) AS paid_fee,
    COALESCE(SUM(due_fee), 0) AS due_fee
FROM fees
WHERE student_id = ?
";

$stmt = mysqli_prepare($conn, $fee_sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$fee_result = mysqli_stmt_get_result($stmt);

$fees = mysqli_fetch_assoc($fee_result);


/*
|--------------------------------------------------------------------------
| ASSIGNMENTS
|--------------------------------------------------------------------------
|
| Ye section tab data dega jab assignments table available ho.
|
*/

$assignments = [];

$assignment_table_check = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'assignments'"
);

if ($assignment_table_check && mysqli_num_rows($assignment_table_check) > 0) {

    $assignment_sql = "
    SELECT *
    FROM assignments
    WHERE student_id = ?
    ORDER BY id DESC
    LIMIT 10
    ";

    $stmt = mysqli_prepare($conn, $assignment_sql);

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $student_id
        );

        
        mysqli_stmt_execute($stmt);

        $assignment_result =
            mysqli_stmt_get_result($stmt);

        while (
            $row = mysqli_fetch_assoc($assignment_result)
        ) {
            $assignments[] = $row;
        }
    }
}


/*
|--------------------------------------------------------------------------
| RESULT / CURRENT GRADE
|--------------------------------------------------------------------------
|
| Result table available hone par latest grade nikalega.
|
*/

$current_grade = null;

$result_table_check = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'results'"
);

if ($result_table_check && mysqli_num_rows($result_table_check) > 0) {

    $grade_sql = "
    SELECT *
    FROM results
    WHERE student_id = ?
    ORDER BY id DESC
    LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $grade_sql);

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($stmt);

        $grade_result =
            mysqli_stmt_get_result($stmt);

        $grade_data =
            mysqli_fetch_assoc($grade_result);

        if ($grade_data) {

            if (isset($grade_data["grade"])) {
                $current_grade =
                    $grade_data["grade"];
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => true,

    "message" => "Student dashboard data loaded successfully",

    "data" => [

        "student" => [

            "id" => $student["id"],

            "user_id" => $student["user_id"],

            "full_name" => $student["full_name"],

            "email" => $student["email"],

            "class" => $student["class"],

            "section" => $student["section"],

            "roll_no" => $student["roll_no"],

            "admission_no" => $student["admission_no"]

        ],

        "attendance" => [

            "total_days" => $total_days,

            "present_days" => $present_days,

            "percentage" => $attendance_percentage

        ],

        "fees" => [

            "total_fee" => $fees["total_fee"] ?? 0,

            "paid_fee" => $fees["paid_fee"] ?? 0,

            "due_fee" => $fees["due_fee"] ?? 0

        ],

        "assignments" => $assignments,

        "current_grade" => $current_grade

    ]

]);

?>