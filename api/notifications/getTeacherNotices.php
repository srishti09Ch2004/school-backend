<?php

// header("Access-Control-Allow-Origin: http://localhost:5173");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Access-Control-Allow-Methods: GET, OPTIONS");
// header("Content-Type: application/json");

// ob_start();
// ini_set("display_errors", 0);

// include("../../config/db.php");

// if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//     http_response_code(200);
//     exit;
// }

// try {

//     if ($_SERVER["REQUEST_METHOD"] !== "GET") {
//         throw new Exception("Only GET method is allowed");
//     }

//     $user_id = $_GET["user_id"] ?? $_GET["teacher_id"] ?? null;

//     if (!$user_id || !is_numeric($user_id)) {
//         throw new Exception("User ID is required");
//     }

//     $user_id = (int)$user_id;

//     // Verify logged-in Teacher
//     $userQuery = "
//         SELECT id, full_name, role
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

//     if (strtolower(trim($user["role"])) !== "teacher") {
//         throw new Exception(
//             "Only teachers can access teacher notices"
//         );
//     }

//     // Fetch notices created by this Teacher
//     $query = "
//         SELECT
//             id,
//             title,
//             description,
//             notice_type,
//             priority,
//             notice_for,
//             created_by,
//             created_role,
//             publish_date,
//             expiry_date,
//             status,
//             created_at,
//             updated_at
//         FROM notices
//         WHERE created_by = ?
//           AND created_role = 'teacher'
//         ORDER BY created_at DESC
//     ";

//     $stmt = $conn->prepare($query);

//     if (!$stmt) {
//         throw new Exception(
//             "Unable to prepare notice query: " . $conn->error
//         );
//     }

//     $stmt->bind_param("i", $user_id);
//     $stmt->execute();

//     $result = $stmt->get_result();

//     $notices = [];

//     // Count recipients from notifications
//     $countQuery = "
//         SELECT COUNT(*) AS total
//         FROM notifications
//         WHERE notice_id = ?
//     ";

//     $countStmt = $conn->prepare($countQuery);

//     if (!$countStmt) {
//         throw new Exception(
//             "Unable to prepare recipient query: " . $conn->error
//         );
//     }

//     while ($row = $result->fetch_assoc()) {

//         $noticeId = (int)$row["id"];

//         $countStmt->bind_param("i", $noticeId);
//         $countStmt->execute();

//         $countResult = $countStmt->get_result();
//         $countRow = $countResult->fetch_assoc();

//         $row["recipient_count"] = (int)(
//             $countRow["total"] ?? 0
//         );

//         // Keep frontend-compatible field
//         $row["for"] = $row["notice_for"];

//         $notices[] = $row;
//     }

//     $countStmt->close();
//     $stmt->close();

//     ob_clean();

//     echo json_encode([
//         "status" => true,
//         "message" => "Teacher notices fetched successfully",
//         "data" => $notices
//     ]);

// } catch (Exception $e) {

//     ob_clean();

//     http_response_code(400);

//     echo json_encode([
//         "status" => false,
//         "message" => $e->getMessage()
//     ]);
// }
// ?>


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

    $userId = isset($_GET["user_id"])
        ? (int)$_GET["user_id"]
        : 0;

    if ($userId <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid user ID"
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify teacher
    |--------------------------------------------------------------------------
    */

    $userStmt = $conn->prepare("
        SELECT id, full_name, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->bind_param("i", $userId);
    $userStmt->execute();

    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "User not found"
        ]);
        exit;
    }

    $user = $userResult->fetch_assoc();

    if (strtolower($user["role"]) !== "teacher") {
        echo json_encode([
            "status" => false,
            "message" => "Only teachers can access teacher notices"
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get notices created by this teacher
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            n.id,
            n.title,
            n.description,
            n.notice_type,
            n.priority,
            n.notice_for,
            n.created_by,
            n.created_role,
            n.publish_date,
            n.expiry_date,
            n.status,
            n.created_at,
            n.updated_at

        FROM notices n

        WHERE n.created_by = ?
          AND n.created_role = 'teacher'

        ORDER BY
            n.created_at DESC,
            n.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    $notices = [];

    while ($row = $result->fetch_assoc()) {

        $noticeId = (int)$row["id"];

        /*
        |--------------------------------------------------------------------------
        | Count actual notification recipients
        |--------------------------------------------------------------------------
        */

        $countStmt = $conn->prepare("
            SELECT COUNT(*) AS recipient_count
            FROM notifications
            WHERE notice_id = ?
        ");

        $countStmt->bind_param("i", $noticeId);
        $countStmt->execute();

        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();

        $recipientCount = (int)$countRow["recipient_count"];

        /*
        |--------------------------------------------------------------------------
        | Frontend compatibility
        |--------------------------------------------------------------------------
        */

        $row["id"] = $noticeId;

        $row["for"] = $row["notice_for"];

        $row["recipient_count"] = $recipientCount;

        /*
        |--------------------------------------------------------------------------
        | Explicit sent time
        |--------------------------------------------------------------------------
        */

        $row["sent_at"] = $row["publish_date"] ?: $row["created_at"];

        $notices[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Teacher notices fetched successfully",
        "teacher" => [
            "id" => (int)$user["id"],
            "name" => $user["full_name"],
            "role" => $user["role"]
        ],
        "total" => count($notices),
        "data" => $notices
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch teacher notices",
        "error" => $e->getMessage()
    ]);
}
?>