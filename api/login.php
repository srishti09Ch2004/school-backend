
<?php

session_start();

// CORS Headers Setup
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_origins = [
    // Localhost (for development)
    "http://localhost:5173",
    "http://localhost:3000",

    // Aapke Netlify URLs (Live Frontend)
    "https://dreamy-bavarois-57693b.netlify.app",
    "https://stirring-lolly-baaec4.netlify.app",

    // Aapke Vercel URLs (Agar future mein use karo toh)
    "https://school-management-two-lake.vercel.app",
    "https://school-management-n2ew-kcp9nl0hv-futureacademy2026.vercel.app",
    "https://school-management-n2ew-git-main-futureacademy2026.vercel.app",
    "https://school-management-dpjv9epe0-futureacademy2026.vercel.app"
];

// Agar origin allowed list mein hai, toh CORS headers bhejo
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
} else {
    // Fallback: Agar origin list mein nahi hai, toh bhi request block mat karo
    // (Yeh testing ke liye helpful hai, lekin security ke liye ise strictly allowed list par rakhna chahiye)
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Preflight (OPTIONS) request ko handle karo aur yahin se exit kar do
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Sirf POST request allow karo
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => false,
        "message" => "Invalid Request Method"
    ]);
    exit;
}

// Safe Database Connection Include
$db_file = __DIR__ . "/../config/db.php";
if (file_exists($db_file)) {
    include($db_file);
} else {
    // Agar path alag ho toh direct alternative path try karega
    include("../config/db.php");
}

// GET REQUEST DATA
$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

// VALIDATION
if ($email === "" || $password === "") {
    echo json_encode([
        "status" => false,
        "message" => "Email and Password are required"
    ]);
    exit;
}

// USER + STUDENT + PARENT DATA QUERY
$sql = "
    SELECT 
        users.id,
        users.full_name,
        users.email,
        users.password,
        users.role,
        
        /* STUDENT */
        students.id AS student_id,
        students.class,
        students.section,
        students.roll_no,
        students.admission_no,
        
        /* PARENT */
        parents.id AS parent_id,
        parents.student_id AS parent_student_id
    FROM users
    LEFT JOIN students ON students.user_id = users.id
    LEFT JOIN parents ON parents.user_id = users.id
    WHERE users.email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database query preparation failed"
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    echo json_encode([
        "status" => false,
        "message" => "Database query execution failed"
    ]);
    exit;
}

// EMAIL CHECK
if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Email is not registered"
    ]);
    exit;
}

$user = mysqli_fetch_assoc($result);

// PASSWORD CHECK
if ($user["password"] !== $password) {
    echo json_encode([
        "status" => false,
        "message" => "Incorrect password"
    ]);
    exit;
}

$dbRole = $user["role"];

// SESSION SETUP
$_SESSION["user_id"] = $user["id"];
$_SESSION["role"] = $dbRole;
$_SESSION["name"] = $user["full_name"];

// BASIC USER RESPONSE
$userResponse = [
    "id" => (int)$user["id"],
    "full_name" => $user["full_name"],
    "email" => $user["email"],
    "role" => $dbRole
];

// STUDENT LOGIN DATA
if ($dbRole === "student") {
    $userResponse["student_id"] = $user["student_id"] ? (int)$user["student_id"] : null;
    $userResponse["class"] = $user["class"];
    $userResponse["section"] = $user["section"];
    $userResponse["roll_no"] = $user["roll_no"];
    $userResponse["admission_no"] = $user["admission_no"];
}

// PARENT LOGIN DATA
if ($dbRole === "parent") {
    $userResponse["parent_id"] = $user["parent_id"] ? (int)$user["parent_id"] : null;
    $userResponse["student_id"] = $user["parent_student_id"] ? (int)$user["parent_student_id"] : null;
}

// FINAL SUCCESS RESPONSE
echo json_encode([
    "status" => true,
    "message" => "Login Successful",
    "role" => $dbRole,
    "user" => $userResponse
]);

// CLOSE CONNECTIONS
mysqli_stmt_close($stmt);
mysqli_close($conn);

?>
