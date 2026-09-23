<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

include("../../config/db.php");

try {

    /*
    |--------------------------------------------------------------------------
    | 1. GET LOGGED-IN USER
    |--------------------------------------------------------------------------
    */

    $user_id = isset($_GET["user_id"])
        ? intval($_GET["user_id"])
        : 0;

    if ($user_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Teacher user ID is required"
        ]);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | 2. GET TEACHER DETAILS
    |--------------------------------------------------------------------------
    */

    $teacherStmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.full_name,
            u.email,

            t.id AS teacher_id,
            t.employee_id,
            t.department,
            t.qualification,
            t.phone,
            t.address

        FROM users u

        INNER JOIN teachers t
            ON t.user_id = u.id

        WHERE u.id = ?
          AND u.role = 'teacher'

        LIMIT 1
    ");

    $teacherStmt->bind_param("i", $user_id);
    $teacherStmt->execute();

    $teacherResult = $teacherStmt->get_result();
    $teacher = $teacherResult->fetch_assoc();

    if (!$teacher) {

        echo json_encode([
            "success" => false,
            "message" => "Teacher not found"
        ]);

        exit;
    }

    $teacher_id = intval($teacher["teacher_id"]);


    /*
    |--------------------------------------------------------------------------
    | 3. TODAY'S SCHEDULE
    |--------------------------------------------------------------------------
    |
    | timetable:
    | teacher_id
    | class_id
    | subject_id
    | day_name
    | start_time
    | end_time
    |
    | classes:
    | id
    | class_name
    | section
    |
    | subjects:
    | id
    | subject_name
    |
    */

    $schedule = [];

    $dayName = date("l");

    $scheduleStmt = $conn->prepare("
        SELECT
            tt.id,

            s.subject_name,

            c.class_name,
            c.section,

            tt.start_time,
            tt.end_time

        FROM timetable tt

        LEFT JOIN classes c
            ON c.id = tt.class_id

        LEFT JOIN subjects s
            ON s.id = tt.subject_id

        WHERE tt.teacher_id = ?
          AND tt.day_name = ?

        ORDER BY tt.start_time ASC
    ");

    $scheduleStmt->bind_param(
        "is",
        $teacher_id,
        $dayName
    );

    $scheduleStmt->execute();

    $scheduleResult = $scheduleStmt->get_result();

    while ($row = $scheduleResult->fetch_assoc()) {

        $className = trim(
            ($row["class_name"] ?? "") .
            (
                !empty($row["section"])
                    ? " - " . $row["section"]
                    : ""
            )
        );

        $schedule[] = [
            "id" => intval($row["id"]),

            "subject" =>
                $row["subject_name"]
                ?? "Subject",

            "className" =>
                $className !== ""
                    ? $className
                    : "Class",

            "startTime" =>
                $row["start_time"],

            "endTime" =>
                $row["end_time"]
        ];
    }

    $today_classes = count($schedule);


    /*
    |--------------------------------------------------------------------------
    | 4. GET TEACHER'S ASSIGNED CLASSES
    |--------------------------------------------------------------------------
    |
    | A teacher can have multiple timetable entries.
    | Therefore we use DISTINCT class_id.
    |
    */

    $assignedClasses = [];

    $classStmt = $conn->prepare("
        SELECT DISTINCT
            tt.class_id,
            c.class_name,
            c.section

        FROM timetable tt

        INNER JOIN classes c
            ON c.id = tt.class_id

        WHERE tt.teacher_id = ?
    ");

    $classStmt->bind_param(
        "i",
        $teacher_id
    );

    $classStmt->execute();

    $classResult = $classStmt->get_result();

    while ($row = $classResult->fetch_assoc()) {

        $assignedClasses[] = [
            "class_id" =>
                intval($row["class_id"]),

            "class_name" =>
                $row["class_name"],

            "section" =>
                $row["section"]
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 5. TOTAL STUDENTS
    |--------------------------------------------------------------------------
    |
    | Count students belonging to classes assigned to this teacher.
    |
    | This is NOT based on attendance history.
    |
    */

    $total_students = 0;

    if (count($assignedClasses) > 0) {

        $studentStmt = $conn->prepare("
            SELECT COUNT(DISTINCT st.id) AS total_students

            FROM students st

            INNER JOIN classes c
                ON c.class_name = st.class
               AND (
                    c.section = st.section
                    OR c.section IS NULL
                    OR c.section = ''
               )

            INNER JOIN timetable tt
                ON tt.class_id = c.id

            WHERE tt.teacher_id = ?
              AND st.status = 'Active'
        ");

        $studentStmt->bind_param(
            "i",
            $teacher_id
        );

        $studentStmt->execute();

        $studentResult =
            $studentStmt->get_result()->fetch_assoc();

        $total_students =
            intval(
                $studentResult["total_students"]
                ?? 0
            );
    }


    /*
    |--------------------------------------------------------------------------
    | 6. ASSIGNMENTS / HOMEWORK
    |--------------------------------------------------------------------------
    */

    $assignments = 0;

    $assignmentStmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM homework

        WHERE teacher_id = ?
    ");

    $assignmentStmt->bind_param(
        "i",
        $teacher_id
    );

    $assignmentStmt->execute();

    $assignmentResult =
        $assignmentStmt->get_result()->fetch_assoc();

    $assignments =
        intval(
            $assignmentResult["total"]
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | 7. TEACHER ATTENDANCE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | attendance table uses marked_by_teacher_id
    |
    */

    $attendance_percentage = 0;

    $attendanceStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN status = 'Present'
                    THEN 1
                    ELSE 0
                END
            ) AS present

        FROM attendance

        WHERE marked_by_teacher_id = ?
    ");

    $attendanceStmt->bind_param(
        "i",
        $teacher_id
    );

    $attendanceStmt->execute();

    $attendanceResult =
        $attendanceStmt
            ->get_result()
            ->fetch_assoc();

    $attendanceTotal =
        intval(
            $attendanceResult["total"]
            ?? 0
        );

    $attendancePresent =
        intval(
            $attendanceResult["present"]
            ?? 0
        );

    if ($attendanceTotal > 0) {

        $attendance_percentage =
            round(
                (
                    $attendancePresent
                    /
                    $attendanceTotal
                ) * 100,
                1
            );
    }


    /*
    |--------------------------------------------------------------------------
    | 8. TEACHER NOTIFICATIONS
    |--------------------------------------------------------------------------
    |
    | notifications table does NOT contain title/message.
    |
    | We get:
    |
    | notifications.notice_id
    |        ↓
    | notices.id
    |
    */

    $notifications = [];

    $notificationStmt = $conn->prepare("
        SELECT

            n.id,

            no.title,
            no.description,

            n.is_read,
            n.created_at,

            no.notice_type,
            no.priority,
            no.publish_date

        FROM notifications n

        INNER JOIN notices no
            ON no.id = n.notice_id

        WHERE n.user_id = ?

        ORDER BY n.created_at DESC

        LIMIT 10
    ");

    $notificationStmt->bind_param(
        "i",
        $user_id
    );

    $notificationStmt->execute();

    $notificationResult =
        $notificationStmt->get_result();

    while ($row =
        $notificationResult->fetch_assoc()
    ) {

        $notifications[] = [

            "id" =>
                intval($row["id"]),

            "title" =>
                $row["title"]
                ?? "Notification",

            "message" =>
                $row["description"]
                ?? "",

            "is_read" =>
                intval(
                    $row["is_read"]
                    ?? 0
                ),

            "created_at" =>
                $row["created_at"],

            "notice_type" =>
                $row["notice_type"],

            "priority" =>
                $row["priority"],

            "publish_date" =>
                $row["publish_date"]
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 9. FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "teacher" => $teacher,

        "stats" => [

            "todayClasses" =>
                $today_classes,

            "totalStudents" =>
                $total_students,

            "assignments" =>
                $assignments,

            "attendance" =>
                $attendance_percentage
        ],

        "schedule" =>
            $schedule,

        "notifications" =>
            $notifications,

        "assignedClasses" =>
            $assignedClasses

    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" =>
            "Teacher dashboard error: " .
            $e->getMessage()

    ]);
}