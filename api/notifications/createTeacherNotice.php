<?php

// header("Access-Control-Allow-Origin: http://localhost:5173");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Content-Type: application/json");

// ob_start();
// ini_set("display_errors", 0);

// include("../../config/db.php");

// if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//     http_response_code(200);
//     exit;
// }

// try {

//     if ($_SERVER["REQUEST_METHOD"] !== "POST") {
//         throw new Exception("Only POST method is allowed");
//     }

//     $input = json_decode(
//         file_get_contents("php://input"),
//         true
//     );

//     if (!is_array($input)) {
//         throw new Exception("Invalid request data");
//     }

//     // Logged-in user ID
//     $user_id = $input["user_id"] ?? $input["teacher_id"] ?? null;

//     $title = trim($input["title"] ?? "");
//     $class_name = trim($input["class_name"] ?? "");
//     $section = trim($input["section"] ?? "");
//     $notice_type = trim($input["notice_type"] ?? "General");
//     $priority = trim($input["priority"] ?? "Normal");
//     $description = trim(
//         $input["description"]
//         ?? $input["content"]
//         ?? ""
//     );

//     $expiry_date = !empty($input["expiry_date"])
//         ? trim($input["expiry_date"])
//         : null;

//     // Validate user ID
//     if (!$user_id || !is_numeric($user_id)) {
//         throw new Exception("Invalid user ID");
//     }

//     $user_id = (int)$user_id;

//     // Verify Teacher from users table
//     $userQuery = "
//         SELECT
//             id,
//             full_name,
//             role
//         FROM users
//         WHERE id = ?
//         LIMIT 1
//     ";

//     $userStmt = $conn->prepare($userQuery);

//     if (!$userStmt) {
//         throw new Exception(
//             "Unable to verify user: " . $conn->error
//         );
//     }

//     $userStmt->bind_param("i", $user_id);
//     $userStmt->execute();

//     $userResult = $userStmt->get_result();
//     $user = $userResult->fetch_assoc();

//     $userStmt->close();

//     if (!$user) {
//         throw new Exception("User not found");
//     }

//     // Any logged-in Teacher can create notice
//     if (strtolower(trim($user["role"])) !== "teacher") {
//         throw new Exception(
//             "Only teachers can create notices"
//         );
//     }

//     // Validate notice
//     if ($title === "") {
//         throw new Exception("Notice title is required");
//     }

//     if ($class_name === "") {
//         throw new Exception("Class is required");
//     }

//     if ($section === "") {
//         throw new Exception("Section is required");
//     }

//     if ($description === "") {
//         throw new Exception("Notice content is required");
//     }

//     /*
//      * Convert frontend priority
//      *
//      * Normal     -> Medium
//      * Important  -> High
//      * Urgent     -> High
//      */
//     switch (strtolower($priority)) {

//         case "low":
//             $dbPriority = "Low";
//             break;

//         case "important":
//         case "urgent":
//         case "high":
//             $dbPriority = "High";
//             break;

//         case "medium":
//         case "normal":
//         default:
//             $dbPriority = "Medium";
//             break;
//     }

//     /*
//      * notices.notice_for is an ENUM:
//      *
//      * Student
//      * Teacher
//      * Parent
//      * All
//      *
//      * Teacher notices are sent only to Students.
//      */
//     $noticeFor = "Student";

//     $conn->begin_transaction();

//     /*
//      * Create main notice
//      */
//     $noticeQuery = "
//         INSERT INTO notices
//         (
//             title,
//             description,
//             notice_type,
//             priority,
//             notice_for,
//             created_by,
//             created_role,
//             publish_date,
//             expiry_date,
//             status
//         )
//         VALUES
//         (
//             ?,
//             ?,
//             ?,
//             ?,
//             ?,
//             ?,
//             'teacher',
//             NOW(),
//             ?,
//             'Published'
//         )
//     ";

//     $noticeStmt = $conn->prepare($noticeQuery);

//     if (!$noticeStmt) {
//         throw new Exception(
//             "Unable to prepare notice query: " .
//             $conn->error
//         );
//     }

//     $noticeStmt->bind_param(
//         "sssssis",
//         $title,
//         $description,
//         $notice_type,
//         $dbPriority,
//         $noticeFor,
//         $user_id,
//         $expiry_date
//     );

//     if (!$noticeStmt->execute()) {
//         throw new Exception(
//             "Failed to save notice: " .
//             $noticeStmt->error
//         );
//     }

//     $noticeId = (int)$noticeStmt->insert_id;

//     $noticeStmt->close();

//     /*
//      * Find target students
//      */
//     if (strtoupper($class_name) === "ALL") {

//         $studentQuery = "
//             SELECT
//                 id,
//                 user_id
//             FROM students
//             WHERE status = 'Active'
//               AND user_id IS NOT NULL
//         ";

//         $studentStmt = $conn->prepare($studentQuery);

//         $targetType = "class";

//     } elseif (strtoupper($section) === "ALL") {

//         $studentQuery = "
//             SELECT
//                 id,
//                 user_id
//             FROM students
//             WHERE class = ?
//               AND status = 'Active'
//               AND user_id IS NOT NULL
//         ";

//         $studentStmt = $conn->prepare($studentQuery);

//         $targetType = "class";

//     } else {

//         $studentQuery = "
//             SELECT
//                 id,
//                 user_id
//             FROM students
//             WHERE class = ?
//               AND section = ?
//               AND status = 'Active'
//               AND user_id IS NOT NULL
//         ";

//         $studentStmt = $conn->prepare($studentQuery);

//         $targetType = "student";
//     }

//     if (!$studentStmt) {
//         throw new Exception(
//             "Unable to find target students: " .
//             $conn->error
//         );
//     }

//     /*
//      * Execute target student query
//      */
//     if (strtoupper($class_name) === "ALL") {

//         $studentStmt->execute();

//     } elseif (strtoupper($section) === "ALL") {

//         $studentStmt->bind_param(
//             "s",
//             $class_name
//         );

//         $studentStmt->execute();

//     } else {

//         $studentStmt->bind_param(
//             "ss",
//             $class_name,
//             $section
//         );

//         $studentStmt->execute();
//     }

//     $studentResult = $studentStmt->get_result();

//     /*
//      * Save notice target
//      */
//     $targetQuery = "
//         INSERT INTO notice_targets
//         (
//             notice_id,
//             target_type,
//             target_role,
//             target_id
//         )
//         VALUES
//         (?, ?, 'student', ?)
//     ";

//     $targetStmt = $conn->prepare($targetQuery);

//     if (!$targetStmt) {
//         throw new Exception(
//             "Unable to prepare notice target query: " .
//             $conn->error
//         );
//     }

//     /*
//      * Create student notification
//      */
//     $notificationQuery = "
//         INSERT INTO notifications
//         (
//             notice_id,
//             user_id,
//             is_read,
//             read_at
//         )
//         VALUES
//         (?, ?, 0, NULL)
//     ";

//     $notificationStmt = $conn->prepare(
//         $notificationQuery
//     );

//     if (!$notificationStmt) {
//         throw new Exception(
//             "Unable to prepare notification query: " .
//             $conn->error
//         );
//     }

//     $recipientCount = 0;

//     /*
//      * Send notice to every target student
//      */
//     while ($student = $studentResult->fetch_assoc()) {

//         $studentId = (int)$student["id"];
//         $studentUserId = (int)$student["user_id"];

//         /*
//          * Save target
//          */
//         $targetStmt->bind_param(
//             "isi",
//             $noticeId,
//             $targetType,
//             $studentId
//         );

//         if (!$targetStmt->execute()) {
//             throw new Exception(
//                 "Failed to save notice target: " .
//                 $targetStmt->error
//             );
//         }

//         /*
//          * Create notification
//          */
//         $notificationStmt->bind_param(
//             "ii",
//             $noticeId,
//             $studentUserId
//         );

//         if (!$notificationStmt->execute()) {
//             throw new Exception(
//                 "Failed to create notification: " .
//                 $notificationStmt->error
//             );
//         }

//         $recipientCount++;
//     }

//     $targetStmt->close();
//     $notificationStmt->close();
//     $studentStmt->close();

//     /*
//      * If no students found, don't create an empty notice
//      */
//     if ($recipientCount === 0) {

//         throw new Exception(
//             "No active students found for the selected class/section"
//         );
//     }

//     $conn->commit();

//     ob_clean();

//     echo json_encode([
//         "status" => true,
//         "message" => "Notice sent successfully",
//         "notice_id" => $noticeId,
//         "recipient_count" => $recipientCount
//     ]);

// } catch (Exception $e) {

//     if (isset($conn)) {
//         try {
//             $conn->rollback();
//         } catch (Throwable $ignored) {
//         }
//     }

//     ob_clean();

//     http_response_code(400);

//     echo json_encode([
//         "status" => false,
//         "message" => $e->getMessage()
//     ]);
// }
// ?>











<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

ob_start();

ini_set("display_errors", "0");
error_reporting(E_ALL);

include("../../config/db.php");

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Only POST request is allowed");
    }

    $rawInput = file_get_contents("php://input");

    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        throw new Exception("Invalid JSON data");
    }

    /*
    |--------------------------------------------------------------------------
    | Input
    |--------------------------------------------------------------------------
    */

    $userId = isset($input["user_id"])
        ? (int)$input["user_id"]
        : (isset($input["teacher_id"]) ? (int)$input["teacher_id"] : 0);

    $title = trim($input["title"] ?? "");
    $className = trim($input["class_name"] ?? "");
    $section = trim($input["section"] ?? "");

    $noticeType = trim($input["notice_type"] ?? "General");
    $priorityInput = strtolower(trim($input["priority"] ?? "normal"));

    $description = trim($input["description"] ?? "");
    $expiryDate = trim($input["expiry_date"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($userId <= 0) {
        throw new Exception("Invalid teacher ID");
    }

    if ($title === "") {
        throw new Exception("Notice title is required");
    }

    if ($description === "") {
        throw new Exception("Notice content is required");
    }

    if ($className === "") {
        throw new Exception("Class is required");
    }

    if ($section === "") {
        throw new Exception("Section is required");
    }

    /*
    |--------------------------------------------------------------------------
    | Verify teacher
    |--------------------------------------------------------------------------
    */

    $teacherStmt = $conn->prepare("
        SELECT id, full_name, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $teacherStmt->bind_param("i", $userId);
    $teacherStmt->execute();

    $teacherResult = $teacherStmt->get_result();

    if ($teacherResult->num_rows === 0) {
        throw new Exception("Teacher account not found");
    }

    $teacher = $teacherResult->fetch_assoc();

    if (strtolower($teacher["role"]) !== "teacher") {
        throw new Exception("Only teachers can create notices");
    }

    /*
    |--------------------------------------------------------------------------
    | Convert frontend priority to DB priority
    |--------------------------------------------------------------------------
    */

    switch ($priorityInput) {

        case "low":
            $priority = "Low";
            break;

        case "important":
        case "urgent":
        case "high":
            $priority = "High";
            break;

        case "medium":
        case "normal":
        default:
            $priority = "Medium";
            break;
    }

    /*
    |--------------------------------------------------------------------------
    | Teacher notices are for students
    |--------------------------------------------------------------------------
    */

    $noticeFor = "Student";

    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Create notice
    |--------------------------------------------------------------------------
    */

    $noticeStmt = $conn->prepare("
        INSERT INTO notices
        (
            title,
            description,
            notice_type,
            priority,
            notice_for,
            created_by,
            created_role,
            publish_date,
            expiry_date,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, 'teacher', NOW(), ?, 'Published'
        )
    ");

    $noticeStmt->bind_param(
        "sssssds",
        $title,
        $description,
        $noticeType,
        $priority,
        $noticeFor,
        $userId,
        $expiryDate
    );

    if (!$noticeStmt->execute()) {
        throw new Exception("Failed to create notice");
    }

    $noticeId = (int)$conn->insert_id;

    /*
    |--------------------------------------------------------------------------
    | Find students
    |--------------------------------------------------------------------------
    */

    $students = [];

    if (strtoupper($className) === "ALL") {

        $studentStmt = $conn->prepare("
            SELECT id, user_id
            FROM students
            WHERE status = 'Active'
              AND user_id IS NOT NULL
            ORDER BY id ASC
        ");

    } elseif (strtoupper($section) === "ALL") {

        $studentStmt = $conn->prepare("
            SELECT id, user_id
            FROM students
            WHERE status = 'Active'
              AND class = ?
              AND user_id IS NOT NULL
            ORDER BY id ASC
        ");

        $studentStmt->bind_param("s", $className);

    } else {

        $studentStmt = $conn->prepare("
            SELECT id, user_id
            FROM students
            WHERE status = 'Active'
              AND class = ?
              AND section = ?
              AND user_id IS NOT NULL
            ORDER BY id ASC
        ");

        $studentStmt->bind_param(
            "ss",
            $className,
            $section
        );
    }

    $studentStmt->execute();

    $studentResult = $studentStmt->get_result();

    while ($student = $studentResult->fetch_assoc()) {
        $students[] = $student;
    }

    if (count($students) === 0) {
        throw new Exception(
            "No active students found for the selected class/section"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare target + notification statements
    |--------------------------------------------------------------------------
    */

    $targetType = (
        strtoupper($className) === "ALL" ||
        strtoupper($section) === "ALL"
    )
        ? "class"
        : "student";

    $targetStmt = $conn->prepare("
        INSERT INTO notice_targets
        (
            notice_id,
            target_type,
            target_role,
            target_id
        )
        VALUES
        (?, ?, 'student', ?)
    ");

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications
        (
            notice_id,
            user_id,
            is_read,
            read_at
        )
        VALUES
        (?, ?, 0, NULL)
    ");

    /*
    |--------------------------------------------------------------------------
    | Create target + notification for every student
    |--------------------------------------------------------------------------
    */

    $recipientCount = 0;

    foreach ($students as $student) {

        $studentId = (int)$student["id"];
        $studentUserId = (int)$student["user_id"];

        // Notice target
        $targetStmt->bind_param(
            "isi",
            $noticeId,
            $targetType,
            $studentId
        );

        if (!$targetStmt->execute()) {
            throw new Exception("Failed to create notice target");
        }

        // Notification
        $notificationStmt->bind_param(
            "ii",
            $noticeId,
            $studentUserId
        );

        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to create student notification");
        }

        $recipientCount++;
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Notice published successfully",
        "notice_id" => $noticeId,
        "recipient_count" => $recipientCount,
        "sent_at" => date("Y-m-d H:i:s"),
        "created_by" => [
            "id" => (int)$teacher["id"],
            "name" => $teacher["full_name"],
            "role" => $teacher["role"]
        ]
    ]);

} catch (Throwable $e) {

    if ($conn->errno === 0) {
        // no-op
    }

    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // Ignore rollback error
    }

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>