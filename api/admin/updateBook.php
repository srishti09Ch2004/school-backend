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
        "message" => "No data received"
    ]);
    exit;
}

$id = $data["id"];
$title = $data["title"];
$author = $data["author"];
$category = $data["category"];
$isbn = $data["isbn"];
$publisher = $data["publisher"];
$price = $data["price"];
$total_copies = $data["total_copies"];
$shelf_location = $data["shelf_location"];
$description = $data["description"];

$sql = "UPDATE library_books SET
    title = ?,
    author = ?,
    category = ?,
    isbn = ?,
    publisher = ?,
    price = ?,
    total_copies = ?,
    shelf_location = ?,
    description = ?
    WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssssdisss",
    $title,
    $author,
    $category,
    $isbn,
    $publisher,
    $price,
    $total_copies,
    $shelf_location,
    $description,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "message" => "Book Updated Successfully"
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "Update Failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();