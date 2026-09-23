<?php

header("Content-Type: application/json");

require_once "../config/db.php";

$response = [
    "status" => false,
    "message" => "",
    "data" => null
];

try {

    // ==========================================
    // TOTAL STUDENTS
    // ==========================================

    $studentQuery = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM students
         WHERE status = 'Active'"
    );

    $studentData = mysqli_fetch_assoc($studentQuery);

    $totalStudents = (int) $studentData["total"];


    // ==========================================
    // TOTAL TEACHERS
    // ==========================================

    $teacherQuery = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM users
         WHERE role = 'teacher'"
    );

    $teacherData = mysqli_fetch_assoc($teacherQuery);

    $totalTeachers = (int) $teacherData["total"];


    // ==========================================
    // FEES COLLECTED
    // ==========================================

    $feeQuery = mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM fees
         WHERE payment_status = 'Paid'"
    );

    $feeData = mysqli_fetch_assoc($feeQuery);

    $feesCollected = (float) $feeData["total"];


    // ==========================================
    // LIBRARY BOOKS
    // ==========================================

    $bookQuery = mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(quantity), 0) AS total
         FROM library_books"
    );

    $bookData = mysqli_fetch_assoc($bookQuery);

    $libraryBooks = (int) $bookData["total"];


    // ==========================================
    // STAFF MEMBERS
    // ==========================================

    $staffQuery = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM staff
         WHERE status = 'Active'"
    );

    $staffData = mysqli_fetch_assoc($staffQuery);

    $staffMembers = (int) $staffData["total"];


    // ==========================================
    // TODAY ATTENDANCE
    // ==========================================

    $attendanceQuery = mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'Present') AS present
         FROM attendance
         WHERE attendance_date = CURDATE()"
    );

    $attendanceData = mysqli_fetch_assoc($attendanceQuery);

    $attendanceTotal = (int) $attendanceData["total"];
    $attendancePresent = (int) $attendanceData["present"];

    $todayAttendance = 0;

    if ($attendanceTotal > 0) {
        $todayAttendance = round(
            ($attendancePresent / $attendanceTotal) * 100
        );
    }


    // ==========================================
    // RECENT ADMISSIONS
    // ==========================================

    $admissionQuery = mysqli_query(
        $conn,
        "SELECT
            full_name,
            class_name,
            admission_no,
            status
         FROM students
         ORDER BY id DESC
         LIMIT 5"
    );

    $recentAdmissions = [];

    while ($row = mysqli_fetch_assoc($admissionQuery)) {

        $recentAdmissions[] = [
            "student" => $row["full_name"],
            "class" => $row["class_name"],
            "admission" => $row["admission_no"],
            "status" => $row["status"]
        ];
    }


    // ==========================================
    // NOTIFICATIONS
    // ==========================================

    $notificationQuery = mysqli_query(
        $conn,
        "SELECT
            title,
            message,
            type,
            created_at
         FROM notifications
         ORDER BY id DESC
         LIMIT 5"
    );

    $notifications = [];

    while ($row = mysqli_fetch_assoc($notificationQuery)) {

        $notifications[] = [
            "title" => $row["title"],
            "message" => $row["message"],
            "type" => $row["type"],
            "created_at" => $row["created_at"]
        ];
    }


    // ==========================================
    // FINAL RESPONSE
    // ==========================================

    $response["status"] = true;

    $response["message"] = "Admin dashboard data fetched successfully.";

    $response["data"] = [

        "stats" => [
            "total_students" => $totalStudents,
            "total_teachers" => $totalTeachers,
            "fees_collected" => $feesCollected,
            "library_books" => $libraryBooks,
            "staff_members" => $staffMembers,
            "today_attendance" => $todayAttendance
        ],

        "recent_admissions" => $recentAdmissions,

        "notifications" => $notifications
    ];


} catch (Exception $e) {

    $response["message"] = $e->getMessage();
}


echo json_encode($response);

?>