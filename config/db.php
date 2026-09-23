 <?php

// $host = "sql211.infinityfree.com";
// $user = "if0_42912018";
// $password = "Iu6CJH0x7KJ";
// $database = "if0_42912018_mydb";

// $conn = mysqli_connect($host, $user, $password, $database);

// if (!$conn) {
//     die("Connection Failed: " . mysqli_connect_error());
// }

// mysqli_set_charset($conn, "utf8mb4");

// ?>





<?php

// CORS Headers for Localhost frontend (Vite/React dev server)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root";     
$password = "";       
$database = "future_academy";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    echo json_encode([
        "status" => false, 
        "message" => "Database Connection Failed: " . mysqli_connect_error()
    ]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");
?>