<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("../../config/db.php");

$sql = "SELECT * FROM library_books ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$books = [];

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $books[] = $row;
    }

    echo json_encode([
        "status" => true,
        "data" => $books
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => mysqli_error($conn)
    ]);
}