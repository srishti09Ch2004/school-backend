<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "status" => false,
            "message" => "Only POST method is allowed"
        ]);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid JSON data"
        ]);
        exit;
    }
// INPUT

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $notice_type = trim($input['notice_type'] ?? 'General');
    $priority = trim($input['priority'] ?? 'Medium');
    $notice_for = trim($input['notice_for'] ?? 'All');

    $created_by = intval($input['created_by'] ?? 0);
    $created_role = strtolower(trim($input['created_role'] ?? ''));

    $publish_date = !empty($input['publish_date'])
        ? $input['publish_date']
        : null;

    $expiry_date = !empty($input['expiry_date'])
        ? $input['expiry_date']
        : null;

    $status = trim($input['status'] ?? 'Published');

    /*
     * targets example:
     
      [
       {
           "target_type": "class",
           "target_id": 5
        }
      ]
     
     target_type:
     role
     class
     student
     teacher
    
     For role:
     {
       "target_type": "role",
       "target_role": "student"
     }
     */

    $targets = $input['targets'] ?? [];

    if (!is_array($targets)) {
        $targets = [];
    }

//  VALIDATION

    if ($title === '') {
        echo json_encode([
            "status" => false,
            "message" => "Notice title is required"
        ]);
        exit;
    }

    if ($description === '') {
        echo json_encode([
            "status" => false,
            "message" => "Notice description is required"
        ]);
        exit;
    }

    if ($created_by <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid creator user ID"
        ]);
        exit;
    }

    if (!in_array($created_role, [
        'admin',
        'principal',
        'teacher'
    ])) {
        echo json_encode([
            "status" => false,
            "message" => "You are not allowed to create notices"
        ]);
        exit;
    }

    if (!in_array($priority, [
        'Low',
        'Medium',
        'High'
    ])) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid priority"
        ]);
        exit;
    }

    if (!in_array($notice_for, [
        'Student',
        'Teacher',
        'Parent',
        'All'
    ])) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid notice audience"
        ]);
        exit;
    }

    if (!in_array($status, [
        'Draft',
        'Published',
        'Scheduled',
        'Expired',
        'Archived'
    ])) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid notice status"
        ]);
        exit;
    }

  // VERIFY CREATOR

    $userStmt = $conn->prepare("
        SELECT id, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$userStmt) {
        throw new Exception(
            "User query preparation failed: " . $conn->error
        );
    }

    $userStmt->bind_param("i", $created_by);
    $userStmt->execute();

    $userResult = $userStmt->get_result();
    $creator = $userResult->fetch_assoc();

    $userStmt->close();

    if (!$creator) {
        echo json_encode([
            "status" => false,
            "message" => "Creator user not found"
        ]);
        exit;
    }

    if (strtolower($creator['role']) !== $created_role) {
        echo json_encode([
            "status" => false,
            "message" => "Creator role does not match user role"
        ]);
        exit;
    }

    // TRANSACTION START

    $conn->begin_transaction();

    // INSERT NOTICE

    $stmt = $conn->prepare("
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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception(
            "Notice preparation failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "sssssiisss",
        $title,
        $description,
        $notice_type,
        $priority,
        $notice_for,
        $created_by,
        $created_role,
        $publish_date,
        $expiry_date,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Notice insertion failed: " . $stmt->error
        );
    }

    $notice_id = $stmt->insert_id;

    $stmt->close();

   // FIND TARGET USERS

    $targetUsers = [];

    /*  CASE 1: ALL     */

    if ($notice_for === 'All') {

        $sql = "
            SELECT id
            FROM users
            WHERE role IN (
                'student',
                'parent',
                'teacher'
            )
        ";

        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception(
                "Unable to find target users: " . $conn->error
            );
        }

        while ($row = $result->fetch_assoc()) {
            $targetUsers[] = intval($row['id']);
        }
    }

    /*  CASE 2: STUDENT     */

    elseif ($notice_for === 'Student') {

        $sql = "
            SELECT id
            FROM users
            WHERE role = 'student'
        ";

        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception(
                "Unable to find students: " . $conn->error
            );
        }

        while ($row = $result->fetch_assoc()) {
            $targetUsers[] = intval($row['id']);
        }
    }

//    CASE 3: PARENT

    elseif ($notice_for === 'Parent') {

        $sql = "
            SELECT id
            FROM users
            WHERE role = 'parent'
        ";

        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception(
                "Unable to find parents: " . $conn->error
            );
        }

        while ($row = $result->fetch_assoc()) {
            $targetUsers[] = intval($row['id']);
        }
    }

//    CASE 4: TEACHER

    elseif ($notice_for === 'Teacher') {

        $sql = "
            SELECT id
            FROM users
            WHERE role = 'teacher'
        ";

        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception(
                "Unable to find teachers: " . $conn->error
            );
        }

        while ($row = $result->fetch_assoc()) {
            $targetUsers[] = intval($row['id']);
        }
    }
// SPECIFIC TARGETS

    foreach ($targets as $target) {

        $targetType = strtolower(
            trim($target['target_type'] ?? '')
        );

        $targetId = intval(
            $target['target_id'] ?? 0
        );

        $targetRole = strtolower(
            trim($target['target_role'] ?? '')
        );

        // ROLE TARGET

        if ($targetType === 'role') {

            if (!in_array($targetRole, [
                'admin',
                'principal',
                'teacher',
                'student',
                'parent'
            ])) {
                continue;
            }

            $stmtRole = $conn->prepare("
                SELECT id
                FROM users
                WHERE role = ?
            ");

            if (!$stmtRole) {
                throw new Exception(
                    "Role target query failed"
                );
            }

            $stmtRole->bind_param(
                "s",
                $targetRole
            );

            $stmtRole->execute();

            $resultRole = $stmtRole->get_result();

            while ($row = $resultRole->fetch_assoc()) {
                $targetUsers[] = intval($row['id']);
            }

            $stmtRole->close();
        }

        // CLASS TARGET

        elseif ($targetType === 'class' && $targetId > 0) {

            /*
             * classes.id represents one class + section.
             *
             * Students are matched through:
             * students.class + students.section
             */

            $classStmt = $conn->prepare("
                SELECT class_name, section
                FROM classes
                WHERE id = ?
                LIMIT 1
            ");

            if (!$classStmt) {
                throw new Exception(
                    "Class query preparation failed"
                );
            }

            $classStmt->bind_param(
                "i",
                $targetId
            );

            $classStmt->execute();

            $classResult = $classStmt->get_result();
            $classData = $classResult->fetch_assoc();

            $classStmt->close();

            if (!$classData) {
                continue;
            }

            $className = $classData['class_name'];
            $section = $classData['section'];

            /* Students of this class     */

            $studentStmt = $conn->prepare("
                SELECT
                    s.id AS student_id,
                    s.user_id AS student_user_id
                FROM students s
                WHERE s.class = ?
                  AND s.section = ?
            ");

            if (!$studentStmt) {
                throw new Exception(
                    "Student class query failed"
                );
            }

            $studentStmt->bind_param(
                "ss",
                $className,
                $section
            );

            $studentStmt->execute();

            $studentResult = $studentStmt->get_result();

            while ($student = $studentResult->fetch_assoc()) {

                // Student user
                if (!empty($student['student_user_id'])) {
                    $targetUsers[] = intval(
                        $student['student_user_id']
                    );
                }

                // Parent of this student
                $studentId = intval(
                    $student['student_id']
                );

                $parentStmt = $conn->prepare("
                    SELECT user_id
                    FROM parents
                    WHERE student_id = ?
                      AND user_id IS NOT NULL
                ");

                if ($parentStmt) {

                    $parentStmt->bind_param(
                        "i",
                        $studentId
                    );

                    $parentStmt->execute();

                    $parentResult =
                        $parentStmt->get_result();

                    while (
                        $parent = $parentResult->fetch_assoc()
                    ) {
                        $targetUsers[] = intval(
                            $parent['user_id']
                        );
                    }

                    $parentStmt->close();
                }
            }

            $studentStmt->close();
        }

        // ---
        // SPECIFIC STUDENT TARGET
        // ---

        elseif (
            $targetType === 'student' &&
            $targetId > 0
        ) {

            $studentStmt = $conn->prepare("
                SELECT user_id
                FROM students
                WHERE id = ?
                LIMIT 1
            ");

            if (!$studentStmt) {
                throw new Exception(
                    "Student target query failed"
                );
            }

            $studentStmt->bind_param(
                "i",
                $targetId
            );

            $studentStmt->execute();

            $studentResult =
                $studentStmt->get_result();

            $student = $studentResult->fetch_assoc();

            $studentStmt->close();

            if ($student && !empty($student['user_id'])) {
                $targetUsers[] = intval(
                    $student['user_id']
                );
            }

            /*
             * Also notify the student's parent
             */

            $parentStmt = $conn->prepare("
                SELECT user_id
                FROM parents
                WHERE student_id = ?
                  AND user_id IS NOT NULL
            ");

            if ($parentStmt) {

                $parentStmt->bind_param(
                    "i",
                    $targetId
                );

                $parentStmt->execute();

                $parentResult =
                    $parentStmt->get_result();

                while (
                    $parent = $parentResult->fetch_assoc()
                ) {
                    $targetUsers[] = intval(
                        $parent['user_id']
                    );
                }

                $parentStmt->close();
            }
        }

        // SPECIFIC TEACHER TARGET

        elseif (
            $targetType === 'teacher' &&
            $targetId > 0
        ) {

            /*
              target_id = teachers.id
             */

            $teacherStmt = $conn->prepare("
                SELECT user_id
                FROM teachers
                WHERE id = ?
                LIMIT 1
            ");

            if (!$teacherStmt) {
                throw new Exception(
                    "Teacher target query failed"
                );
            }

            $teacherStmt->bind_param(
                "i",
                $targetId
            );

            $teacherStmt->execute();

            $teacherResult =
                $teacherStmt->get_result();

            $teacher = $teacherResult->fetch_assoc();

            $teacherStmt->close();

            if ($teacher && !empty($teacher['user_id'])) {
                $targetUsers[] = intval(
                    $teacher['user_id']
                );
            }
        }
    }

    // REMOVE DUPLICATE USERS

    $targetUsers = array_values(
        array_unique(
            array_filter(
                $targetUsers,
                function ($id) {
                    return intval($id) > 0;
                }
            )
        )
    );

    // SAVE TARGET DEFINITIONS

    /*
     * Save notice_for as a role target.
     
     * All is stored as role targets for:
     * student, parent, teacher.
     */

    if ($notice_for === 'All') {

        $roles = [
            'student',
            'parent',
            'teacher'
        ];

        foreach ($roles as $role) {

            $targetStmt = $conn->prepare("
                INSERT INTO notice_targets
                (
                    notice_id,
                    target_type,
                    target_role,
                    target_id
                )
                VALUES (?, 'role', ?, NULL)
            ");

            if (!$targetStmt) {
                throw new Exception(
                    "Target insertion failed"
                );
            }

            $targetStmt->bind_param(
                "is",
                $notice_id,
                $role
            );

            $targetStmt->execute();
            $targetStmt->close();
        }

    } else {

        $roleMap = [
            'Student' => 'student',
            'Teacher' => 'teacher',
            'Parent' => 'parent'
        ];

        $role = $roleMap[$notice_for];

        $targetStmt = $conn->prepare("
            INSERT INTO notice_targets
            (
                notice_id,
                target_type,
                target_role,
                target_id
            )
            VALUES (?, 'role', ?, NULL)
        ");

        if (!$targetStmt) {
            throw new Exception(
                "Target insertion failed"
            );
        }

        $targetStmt->bind_param(
            "is",
            $notice_id,
            $role
        );

        $targetStmt->execute();
        $targetStmt->close();
    }

    /*
     * Save additional targets.
     */

    foreach ($targets as $target) {

        $targetType = strtolower(
            trim($target['target_type'] ?? '')
        );

        $targetId = intval(
            $target['target_id'] ?? 0
        );

        $targetRole = strtolower(
            trim($target['target_role'] ?? '')
        );

        if ($targetType === 'role') {

            if (!in_array($targetRole, [
                'admin',
                'principal',
                'teacher',
                'student',
                'parent'
            ])) {
                continue;
            }

            $targetStmt = $conn->prepare("
                INSERT INTO notice_targets
                (
                    notice_id,
                    target_type,
                    target_role,
                    target_id
                )
                VALUES (?, 'role', ?, NULL)
            ");

            if (!$targetStmt) {
                throw new Exception(
                    "Role target insertion failed"
                );
            }

            $targetStmt->bind_param(
                "is",
                $notice_id,
                $targetRole
            );

            $targetStmt->execute();
            $targetStmt->close();
        }

        elseif (
            in_array($targetType, [
                'class',
                'student',
                'teacher'
            ]) &&
            $targetId > 0
        ) {

            $targetStmt = $conn->prepare("
                INSERT INTO notice_targets
                (
                    notice_id,
                    target_type,
                    target_role,
                    target_id
                )
                VALUES (?, ?, NULL, ?)
            ");

            if (!$targetStmt) {
                throw new Exception(
                    "Specific target insertion failed"
                );
            }

            $targetStmt->bind_param(
                "isi",
                $notice_id,
                $targetType,
                $targetId
            );

            $targetStmt->execute();
            $targetStmt->close();
        }
    }

    // CREATE USER NOTIFICATIONS

    $notificationCount = 0;

    if (
        $status === 'Published' &&
        count($targetUsers) > 0
    ) {

        $notificationStmt = $conn->prepare("
            INSERT IGNORE INTO notifications
            (
                notice_id,
                user_id,
                is_read,
                read_at
            )
            VALUES (?, ?, 0, NULL)
        ");

        if (!$notificationStmt) {
            throw new Exception(
                "Notification preparation failed: "
                . $conn->error
            );
        }

        foreach ($targetUsers as $userId) {

            $userId = intval($userId);

            $notificationStmt->bind_param(
                "ii",
                $notice_id,
                $userId
            );

            if ($notificationStmt->execute()) {
                if ($notificationStmt->affected_rows > 0) {
                    $notificationCount++;
                }
            }
        }

        $notificationStmt->close();
    }

    // COMMIT

    $conn->commit();

    // RESPONSE

    echo json_encode([
        "status" => true,
        "message" => "Notice created successfully",
        "notice_id" => intval($notice_id),
        "target_users" => count($targetUsers),
        "notifications_created" => $notificationCount
    ]);

} catch (Exception $e) {

    if ($conn->errno === 0) {
        // Nothing required
    }

    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Ignore rollback errors
    }

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>

