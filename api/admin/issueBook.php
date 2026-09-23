<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

$book_id = $data["book_id"] ?? "";
$student_id = $data["student_id"] ?? null;
$student_name = $data["student_name"] ?? "";
$issue_date = $data["issue_date"] ?? "";
$due_date = $data["due_date"] ?? "";

if (
    empty($book_id) ||
    empty($student_name) ||
    empty($issue_date) ||
    empty($due_date)
) {
    echo json_encode([
        "status" => false,
        "message" => "Please fill all required fields."
    ]);
    exit;
}

/* Check book */

$bookQuery = mysqli_query(
    $conn,
    "SELECT * FROM library_books WHERE id = '$book_id'"
);

if (!$bookQuery || mysqli_num_rows($bookQuery) === 0) {

    echo json_encode([
        "status" => false,
        "message" => "Book not found."
    ]);

    exit;
}

$book = mysqli_fetch_assoc($bookQuery);


/* Check availability */

if ((int)$book["available_copies"] <= 0) {

    echo json_encode([
        "status" => false,
        "message" => "This book is currently out of stock."
    ]);

    exit;
}


/* Insert issue record */

$sql = "INSERT INTO library_issues
(
    book_id,
    student_id,
    student_name,
    issue_date,
    due_date,
    status,
    fine
)
VALUES
(
    '$book_id',
    " . ($student_id !== null && $student_id !== "" ? "'$student_id'" : "NULL") . ",
    '$student_name',
    '$issue_date',
    '$due_date',
    'Issued',
    0
)";

if (mysqli_query($conn, $sql)) {

    /* Reduce available copies */

    $updateBook = mysqli_query(
        $conn,
        "UPDATE library_books
         SET available_copies = available_copies - 1
         WHERE id = '$book_id'"
    );

    if ($updateBook) {

        /* Check remaining copies */

        $checkBook = mysqli_query(
            $conn,
            "SELECT available_copies FROM library_books WHERE id = '$book_id'"
        );

        $updatedBook = mysqli_fetch_assoc($checkBook);

        $newStatus =
            ((int)$updatedBook["available_copies"] > 0)
            ? "Available"
            : "Out of Stock";

        mysqli_query(
            $conn,
            "UPDATE library_books
             SET status = '$newStatus'
             WHERE id = '$book_id'"
        );

        echo json_encode([
            "status" => true,
            "message" => "Book issued successfully."
        ]);

    } else {

        echo json_encode([
            "status" => false,
            "message" => "Book issued but copy count could not be updated."
        ]);
    }

} else {

    echo json_encode([
        "status" => false,
        "message" => mysqli_error($conn)
    ]);
}
?>