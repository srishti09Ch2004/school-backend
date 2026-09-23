
<?php

// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json");

// include("../../config/db.php");

// try {

//     // GET USER ID

//     $user_id = isset($_GET['user_id'])
//         ? intval($_GET['user_id'])
//         : 0;

//     if ($user_id <= 0) {
//         echo json_encode([
//             "status" => false,
//             "message" => "Valid user_id is required"
//         ]);
//         exit;
//     }

//     // VERIFY USER

//     $userStmt = $conn->prepare("
//         SELECT id, full_name, role
//         FROM users
//         WHERE id = ?
//         LIMIT 1
//     ");

//     if (!$userStmt) {
//         throw new Exception(
//             "User query preparation failed: " . $conn->error
//         );
//     }

//     $userStmt->bind_param("i", $user_id);
//     $userStmt->execute();

//     $userResult = $userStmt->get_result();
//     $user = $userResult->fetch_assoc();

//     $userStmt->close();

//     if (!$user) {
//         echo json_encode([
//             "status" => false,
//             "message" => "User not found"
//         ]);
//         exit;
//     }

//     $role = strtolower($user['role']);

//     // FETCH NOTIFICATIONS

//     /*
//      * Current notifications table directly stores:
     
//      * notice_id
//      * user_id
//      * is_read
     
//      * So we join notifications with notices.
//      */

//     $stmt = $conn->prepare("
//         SELECT
//             nt.id AS notification_id,

//             nt.notice_id,
//             nt.user_id,

//             nt.is_read,
//             nt.read_at,
//             nt.created_at AS notification_created_at,

//             n.title,
//             n.description,
//             n.notice_type,
//             n.priority,
//             n.notice_for,

//             n.created_by,
//             n.created_role,

//             n.publish_date,
//             n.expiry_date,
//             n.status,

//             n.created_at AS notice_created_at,
//             n.updated_at,

//             u.full_name AS creator_name

//         FROM notifications nt

//         INNER JOIN notices n
//             ON n.id = nt.notice_id

//         LEFT JOIN users u
//             ON u.id = n.created_by

//         WHERE nt.user_id = ?

//         ORDER BY
//             nt.is_read ASC,
//             nt.created_at DESC,
//             nt.id DESC
//     ");

//     if (!$stmt) {
//         throw new Exception(
//             "Notification query preparation failed: "
//             . $conn->error
//         );
//     }

//     $stmt->bind_param("i", $user_id);

//     if (!$stmt->execute()) {
//         throw new Exception(
//             "Notification query execution failed: "
//             . $stmt->error
//         );
//     }

//     $result = $stmt->get_result();

//     $notifications = [];

//     while ($row = $result->fetch_assoc()) {

//         $notifications[] = [
//             "notification_id" => (int)$row['notification_id'],

//             "notice_id" => (int)$row['notice_id'],

//             "user_id" => (int)$row['user_id'],

//             "is_read" => (int)$row['is_read'],

//             "read_at" => $row['read_at'],

//             "notification_created_at" =>
//                 $row['notification_created_at'],

//             "title" => $row['title'],

//             "description" => $row['description'],

//             "notice_type" => $row['notice_type'],

//             "priority" => $row['priority'],

//             "notice_for" => $row['notice_for'],

//             "created_by" =>
//                 $row['created_by'] !== null
//                     ? (int)$row['created_by']
//                     : null,

//             "created_role" => $row['created_role'],

//             "creator_name" => $row['creator_name'],

//             "publish_date" => $row['publish_date'],

//             "expiry_date" => $row['expiry_date'],

//             "status" => $row['status'],

//             "notice_created_at" =>
//                 $row['notice_created_at'],

//             "updated_at" => $row['updated_at']
//         ];
//     }

//     $stmt->close();

//     // UNREAD COUNT

//     $unreadStmt = $conn->prepare("
//         SELECT COUNT(*) AS unread_count
//         FROM notifications
//         WHERE user_id = ?
//           AND is_read = 0
//     ");

//     if (!$unreadStmt) {
//         throw new Exception(
//             "Unread count query failed: " . $conn->error
//         );
//     }

//     $unreadStmt->bind_param("i", $user_id);
//     $unreadStmt->execute();

//     $unreadResult = $unreadStmt->get_result();
//     $unreadData = $unreadResult->fetch_assoc();

//     $unreadStmt->close();

//     $unreadCount = intval(
//         $unreadData['unread_count'] ?? 0
//     );

//     // RESPONSE

//     echo json_encode([
//         "status" => true,

//         "message" =>
//             "Notifications fetched successfully",

//         "user" => [
//             "id" => (int)$user['id'],
//             "full_name" => $user['full_name'],
//             "role" => $role
//         ],

//         "total" => count($notifications),

//         "unread_count" => $unreadCount,

//         "data" => $notifications
//     ]);

// } catch (Exception $e) {

//     echo json_encode([
//         "status" => false,
//         "message" => $e->getMessage()
//     ]);
// }

// $conn->close();
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

    $userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;

    if ($userId <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid user ID"
        ]);
        exit;
    }

    // Verify user exists
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

    /*
    |--------------------------------------------------------------------------
    | Get notifications
    |--------------------------------------------------------------------------
    | notification_id = PRIMARY KEY from notifications table
    | notice_id       = PRIMARY KEY from notices table
    |
    | IMPORTANT:
    | Never use notice_id as notification ID.
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            nt.id AS notification_id,
            nt.notice_id,
            nt.user_id,
            nt.is_read,
            nt.read_at,
            nt.created_at AS notification_created_at,

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
            n.created_at AS notice_created_at,
            n.updated_at,

            u.full_name AS creator_name

        FROM notifications nt

        INNER JOIN notices n
            ON n.id = nt.notice_id

        LEFT JOIN users u
            ON u.id = n.created_by

        WHERE nt.user_id = ?

        ORDER BY
            nt.is_read ASC,
            nt.created_at DESC,
            nt.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    $notifications = [];

    while ($row = $result->fetch_assoc()) {

        $notificationId = (int)$row["notification_id"];

        $notifications[] = [
            // Main notification primary key
            "id" => $notificationId,
            "notification_id" => $notificationId,

            // Notice primary key
            "notice_id" => (int)$row["notice_id"],

            "user_id" => (int)$row["user_id"],

            "is_read" => (int)$row["is_read"],

            "read_at" => $row["read_at"],

            // Exact receiver notification time
            "created_at" => $row["notification_created_at"],
            "notification_created_at" => $row["notification_created_at"],

            // Notice information
            "title" => $row["title"],
            "description" => $row["description"],
            "notice_type" => $row["notice_type"],
            "priority" => $row["priority"],
            "notice_for" => $row["notice_for"],

            "created_by" => (int)$row["created_by"],
            "created_role" => $row["created_role"],
            "creator_name" => $row["creator_name"],

            // Notice timing
            "publish_date" => $row["publish_date"],
            "expiry_date" => $row["expiry_date"],
            "notice_created_at" => $row["notice_created_at"],
            "updated_at" => $row["updated_at"],

            "status" => $row["status"]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Unread count
    |--------------------------------------------------------------------------
    */

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE user_id = ?
          AND is_read = 0
    ");

    $countStmt->bind_param("i", $userId);
    $countStmt->execute();

    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();

    $unreadCount = (int)$countRow["unread_count"];

    echo json_encode([
        "status" => true,
        "message" => "Notifications fetched successfully",
        "unread_count" => $unreadCount,
        "total_count" => count($notifications),
        "data" => $notifications
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch notifications",
        "error" => $e->getMessage()
    ]);
}
?>