<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include("../../config/db.php");

try {

//    GET PARAMETERS

    $created_by = isset($_GET['created_by'])
        ? intval($_GET['created_by'])
        : 0;

    $created_role = isset($_GET['created_role'])
        ? strtolower(trim($_GET['created_role']))
        : '';

//    BASE QUERY

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
            n.updated_at,

            u.full_name AS creator_name

        FROM notices n

        LEFT JOIN users u
            ON u.id = n.created_by
    ";

    /*
    | FILTER
    
     Admin / Principal / Teacher:
     show notices created by that user.
    
     If no user filter is supplied:
     show all notices.
    
    */

    $conditions = [];
    $params = [];
    $types = "";

    if ($created_by > 0) {
        $conditions[] = "n.created_by = ?";
        $params[] = $created_by;
        $types .= "i";
    }

    if (
        in_array(
            $created_role,
            ['admin', 'principal', 'teacher']
        )
    ) {
        $conditions[] = "n.created_role = ?";
        $params[] = $created_role;
        $types .= "s";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // ORDER

    $sql .= "
        ORDER BY
            n.created_at DESC,
            n.id DESC
    ";
//  PREPARE

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Query preparation failed: " . $conn->error
        );
    }

//    BIND PARAMETERS

    if (!empty($params)) {
        $stmt->bind_param(
            $types,
            ...$params
        );
    }

//   EXECUTE

    if (!$stmt->execute()) {
        throw new Exception(
            "Query execution failed: " . $stmt->error
        );
    }

    $result = $stmt->get_result();

    $notices = [];

    while ($row = $result->fetch_assoc()) {

        $notices[] = [
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "notice_type" => $row['notice_type'],
            "priority" => $row['priority'],
            "notice_for" => $row['notice_for'],

            "created_by" => $row['created_by']
                !== null
                ? (int)$row['created_by']
                : null,

            "created_role" => $row['created_role'],

            "creator_name" => $row['creator_name'],

            "publish_date" => $row['publish_date'],
            "expiry_date" => $row['expiry_date'],

            "status" => $row['status'],

            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at']
        ];
    }

    $stmt->close();

//  SUCCESS RESPONSE

    echo json_encode([
        "status" => true,
        "message" => "Notices fetched successfully",
        "total" => count($notices),
        "data" => $notices
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>
