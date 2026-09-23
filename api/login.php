<?php

// =====================================================
// CORS CONFIGURATION
// =====================================================

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_origins = [

    // Local development
    "http://localhost:5173",
    "http://localhost:3000",

    // Current Netlify frontend
    "https://ephemeral-kringle-2091c6.netlify.app",

    // Older Netlify frontends
    "https://dreamy-bavarois-57693b.netlify.app",
    "https://stirring-lolly-baaec4.netlify.app",

    // Vercel frontends
    "https://school-management-two-lake.vercel.app",
    "https://school-management-n2ew-kcp9nl0hv-futureacademy2026.vercel.app",
    "https://school-management-n2ew-git-main-futureacademy2026.vercel.app",
    "https://school-management-dpjv9epe0-futureacademy2026.vercel.app"
];


// =====================================================
// CORS HEADERS
// =====================================================

if (in_array($origin, $allowed_origins, true)) {

    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Vary: Origin");

header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// HANDLE OPTIONS / PREFLIGHT REQUEST
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {

    http_response_code(200);
    exit;
}


// =====================================================
// START SESSION
// =====================================================

session_start();


// =====================================================
// ONLY POST REQUEST ALLOWED
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "status" => false,
        "message" => "Invalid Request Method"
    ]);

    exit;
}


// =====================================================
// DATABASE CONFIGURATION
// =====================================================

$db_file = __DIR__ . "/../config/db.php";

if (!file_exists($db_file)) {

    echo json_encode([
        "status" => false,
        "message" => "Database configuration file not found"
    ]);

    exit;
}

require_once $db_file;


// =====================================================
// READ JSON REQUEST
// =====================================================

$rawData = file_get_contents("php://input");

$data = json_decode($rawData, true);

if (!is_array($data)) {

    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON request"
    ]);

    exit;
}


// =====================================================
// GET LOGIN DATA
// =====================================================

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";


// =====================================================
// VALIDATE LOGIN DATA
// =====================================================

if ($email === "" || $password === "") {

    echo json_encode([
        "status" => false,
        "message" => "Email and Password are required"
    ]);

    exit;
}


// =====================================================
// LOGIN QUERY
// =====================================================

$sql = "
    SELECT 
        users.id,
        users.full_name,
        users.email,
        users.password,
        users.role,

        students.id AS student_id,
        students.class,
        students.section,
        students.roll_no,
        students.admission_no,

        parents.id AS parent_id,
        parents.student_id AS parent_student_id

    FROM users

    LEFT JOIN students
        ON students.user_id = users.id

    LEFT JOIN parents
        ON parents.user_id = users.id

    WHERE users.email = ?

    LIMIT 1
";


// =====================================================
// PREPARE QUERY
// =====================================================

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Database query preparation failed"
    ]);

    exit;
}


// =====================================================
// BIND EMAIL
// =====================================================

mysqli_stmt_bind_param($stmt, "s", $email);


// =====================================================
// EXECUTE QUERY
// =====================================================

if (!mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => false,
        "message" => "Database query execution failed"
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


// =====================================================
// GET RESULT
// =====================================================

$result = mysqli_stmt_get_result($stmt);

if (!$result) {

    echo json_encode([
        "status" => false,
        "message" => "Database result could not be loaded"
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


// =====================================================
// CHECK USER EXISTS
// =====================================================

if (mysqli_num_rows($result) === 0) {

    echo json_encode([
        "status" => false,
        "message" => "Email is not registered"
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


// =====================================================
// GET USER
// =====================================================

$user = mysqli_fetch_assoc($result);


// =====================================================
// CHECK PASSWORD
// =====================================================

if ($user["password"] !== $password) {

    echo json_encode([
        "status" => false,
        "message" => "Incorrect password"
    ]);

    mysqli_stmt_close($stmt);
    exit;
}


// =====================================================
// USER ROLE
// =====================================================

$dbRole = $user["role"];


// =====================================================
// CREATE SESSION
// =====================================================

$_SESSION["user_id"] = $user["id"];
$_SESSION["role"] = $dbRole;
$_SESSION["name"] = $user["full_name"];


// =====================================================
// BASIC USER RESPONSE
// =====================================================

$userResponse = [
    "id" => (int) $user["id"],
    "full_name" => $user["full_name"],
    "email" => $user["email"],
    "role" => $dbRole
];


// =====================================================
// STUDENT DATA
// =====================================================

if ($dbRole === "student") {

    $userResponse["student_id"] =
        $user["student_id"]
            ? (int) $user["student_id"]
            : null;

    $userResponse["class"] = $user["class"];
    $userResponse["section"] = $user["section"];
    $userResponse["roll_no"] = $user["roll_no"];
    $userResponse["admission_no"] = $user["admission_no"];
}


// =====================================================
// PARENT DATA
// =====================================================

if ($dbRole === "parent") {

    $userResponse["parent_id"] =
        $user["parent_id"]
            ? (int) $user["parent_id"]
            : null;

    $userResponse["student_id"] =
        $user["parent_student_id"]
            ? (int) $user["parent_student_id"]
            : null;
}


// =====================================================
// LOGIN SUCCESS RESPONSE
// =====================================================

echo json_encode([
    "status" => true,
    "message" => "Login Successful",
    "role" => $dbRole,
    "user" => $userResponse
]);


// =====================================================
// CLOSE CONNECTIONS
// =====================================================

mysqli_stmt_close($stmt);
mysqli_close($conn);
