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

if (!$data) {
    echo json_encode([
        "status" => false,
        "message" => "No Data Received"
    ]);
    exit;
}

$title = trim($data["title"] ?? "");
$author = trim($data["author"] ?? "");
$category = trim($data["category"] ?? "");
$isbn = trim($data["isbn"] ?? "");
$publisher = trim($data["publisher"] ?? "");
$price = $data["price"] ?? 0;
$total_copies = $data["total_copies"] ?? 0;
$shelf_location = trim($data["shelf_location"] ?? "");
$description = trim($data["description"] ?? "");

if (
    $title === "" ||
    $author === "" ||
    $category === "" ||
    $isbn === "" ||
    $total_copies <= 0
) {
    echo json_encode([
        "status" => false,
        "message" => "Please fill all required fields"
    ]);
    exit;
}



$available_copies = $total_copies;
$status = "Available";


$cover_image = "https://covers.openlibrary.org/b/isbn/" . $isbn . "-L.jpg";


$sql = "INSERT INTO library_books
(
    title,
    author,
    category,
    isbn,
    publisher,
    price,
    total_copies,
    available_copies,
    shelf_location,
    description,
    cover_image,
    status
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    "sssssdiissss",
    $title,
    $author,
    $category,
    $isbn,
    $publisher,
    $price,
    $total_copies,
    $available_copies,
    $shelf_location,
    $description,
    $cover_image,
    $status
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "message" => "Book Added Successfully",
        "id" => $stmt->insert_id
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "Book Add Failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>