<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

ob_start();
ini_set("display_errors", 0);

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        throw new Exception("Only GET method is allowed");
    }

    $user_id = $_GET["user_id"] ?? null;

    if (!$user_id || !is_numeric($user_id)) {
        throw new Exception("User ID is required");
    }

    $user_id = (int)$user_id;

    /*
     * Verify Principal
     */
    $userQuery = "
        SELECT
            id,
            full_name,
            role
        FROM users
        WHERE id = ?
        LIMIT 1
    ";

    $userStmt = $conn->prepare($userQuery);

    if (!$userStmt) {
        throw new Exception(
            "Unable to verify user: " . $conn->error
        );
    }

    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();

    $user = $userStmt
        ->get_result()
        ->fetch_assoc();

    $userStmt->close();

    if (!$user) {
        throw new Exception("User not found");
    }

    if (strtolower(trim($user["role"])) !== "principal") {
        throw new Exception(
            "Only principals can access principal notices"
        );
    }

    /*
     * Mark expired notices automatically
     */
    $expireQuery = "
        UPDATE notices
        SET status = 'Expired'
        WHERE created_by = ?
          AND created_role = 'principal'
          AND status = 'Published'
          AND expiry_date IS NOT NULL
          AND expiry_date < NOW()
    ";

    $expireStmt = $conn->prepare($expireQuery);

    if ($expireStmt) {
        $expireStmt->bind_param("i", $user_id);
        $expireStmt->execute();
        $expireStmt->close();
    }

    /*
     * Fetch notices
     */
    $query = "
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
            n.updated_at,
            u.full_name AS creator_name,
            COUNT(DISTINCT no.id) AS recipient_count,
            COUNT(
                DISTINCT CASE
                    WHEN no.is_read = 1
                    THEN no.id
                END
            ) AS read_count,
            COUNT(
                DISTINCT CASE
                    WHEN no.is_read = 0
                    THEN no.id
                END
            ) AS unread_count
        FROM notices n

        LEFT JOIN users u
            ON u.id = n.created_by

        LEFT JOIN notifications no
            ON no.notice_id = n.id

        WHERE n.created_by = ?
          AND n.created_role = 'principal'

        GROUP BY
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
            n.updated_at,
            u.full_name

        ORDER BY n.created_at DESC
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception(
            "Unable to prepare notice query: " .
            $conn->error
        );
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $notices = [];

    /*
     * Target summary query
     */
    $targetQuery = "
        SELECT
            nt.target_type,
            nt.target_role,
            nt.target_id,
            s.class,
            s.section
        FROM notice_targets nt

        LEFT JOIN students s
            ON s.id = nt.target_id

        WHERE nt.notice_id = ?
    ";

    $targetStmt = $conn->prepare($targetQuery);

    if (!$targetStmt) {
        throw new Exception(
            "Unable to prepare target query: " .
            $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {

        $noticeId = (int)$row["id"];

        /*
         * Get target details
         */
        $targetStmt->bind_param(
            "i",
            $noticeId
        );

        $targetStmt->execute();

        $targetResult =
            $targetStmt->get_result();

        $classesFound = [];
        $sectionsFound = [];

        while (
            $target = $targetResult->fetch_assoc()
        ) {

            if (
                !empty($target["class"]) &&
                !empty($target["section"])
            ) {

                $group =
                    "Class " .
                    $target["class"] .
                    " - Section " .
                    $target["section"];

                $classesFound[$group] = true;
                $sectionsFound[
                    $target["class"] . "-" .
                    $target["section"]
                ] = true;
            }
        }

        /*
         * Build audience label
         */
        if ($row["notice_for"] === "Student") {

            if (count($classesFound) === 0) {

                $row["target_label"] =
                    "All Students";

            } else {

                $groups =
                    array_keys($classesFound);

                if (count($groups) === 1) {

                    $row["target_label"] =
                        "Students - " .
                        $groups[0];

                } else {

                    $row["target_label"] =
                        "Students - " .
                        count($groups) .
                        " Class/Section Groups";
                }
            }

        } elseif ($row["notice_for"] === "Parent") {

            if (count($classesFound) === 0) {

                $row["target_label"] =
                    "All Parents";

            } else {

                $groups =
                    array_keys($classesFound);

                if (count($groups) === 1) {

                    $row["target_label"] =
                        "Parents - " .
                        $groups[0];

                } else {

                    $row["target_label"] =
                        "Parents - " .
                        count($groups) .
                        " Class/Section Groups";
                }
            }

        } elseif ($row["notice_for"] === "Teacher") {

            $row["target_label"] =
                "All Teachers";

        } elseif ($row["notice_for"] === "All") {

            $row["target_label"] =
                "Entire School";

        } else {

            $row["target_label"] =
                $row["notice_for"] ?? "-";
        }

        /*
         * Exact sender timestamp
         */
        $row["sent_at"] =
            $row["created_at"];

        /*
         * Exact published timestamp
         */
        $row["published_at"] =
            $row["publish_date"];

        /*
         * Convert counts to integer
         */
        $row["recipient_count"] =
            (int)$row["recipient_count"];

        $row["read_count"] =
            (int)$row["read_count"];

        $row["unread_count"] =
            (int)$row["unread_count"];

        $notices[] = $row;
    }

    $targetStmt->close();
    $stmt->close();

    ob_clean();

    echo json_encode([
        "status" => true,
        "message" =>
            "Principal notices fetched successfully",
        "data" => $notices
    ]);

} catch (Exception $e) {

    ob_clean();

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>