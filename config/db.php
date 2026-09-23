<?php

// // CORS Headers for Localhost frontend (Vite/React dev server)
// header("Access-Control-Allow-Origin: http://localhost:5173");
// header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
// header("Content-Type: application/json");

// // Handle preflight OPTIONS request
// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

// $host = "localhost";
// $user = "root";     
// $password = "";       
// $database = "future_academy";

// $conn = mysqli_connect($host, $user, $password, $database);

// if (!$conn) {
//     echo json_encode([
//         "status" => false, 
//         "message" => "Database Connection Failed: " . mysqli_connect_error()
//     ]);
//     exit();
// }

// mysqli_set_charset($conn, "utf8mb4");
// ?>



<?php

// CORS Headers for Live Frontend
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:3000",
    
    "https://dreamy-bavarois-57693b.netlify.app",
    "https://stirring-lolly-baaec4.netlify.app"
];

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
} else {
    // Fallback: Agar origin list mein nahi hai, toh bhi request block mat karo 
    if ($origin) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
    }
}

header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- AIVEN DATABASE CONFIGURATION ---

$host = "mysql-29fff39-srishtichaturvedi2912004-fb24.f.aivencloud.com"; 
$port = 11728; 
$user = "avnadmin"; 
 
$password = getenv('DB_PASSWORD'); 
$database = "defaultdb"; 

// SSL Certificate ka path 
$ca_cert = __DIR__ . '/ca.pem'; 

// MySQLi connection SSL ke saath
$conn = mysqli_init();

// SSL set karo
mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);

// Real connect karo (port number dhyan se daalo)
if (!mysqli_real_connect($conn, $host, $user, $password, $database, $port)) {
    echo json_encode([
        "status" => false, 
        "message" => "Database Connection Failed: " . mysqli_connect_error()
    ]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");
?>
