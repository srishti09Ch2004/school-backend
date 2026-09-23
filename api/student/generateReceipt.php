<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

include("../../config/db.php");

require_once("../../../vendor/autoload.php");

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo "Invalid request method";
    exit;
}

$payment_id = intval($_GET["payment_id"] ?? 0);
$user_id = intval($_GET["user_id"] ?? 0);

if ($payment_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo "payment_id and user_id are required";
    exit;
}

// Find Student

$studentSql = "
    SELECT
        s.id,
        u.full_name,
        u.email,
        s.admission_no,
        s.class,
        s.section,
        s.roll_no
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.id
    WHERE s.user_id = ?
    LIMIT 1
";

$studentStmt = mysqli_prepare($conn, $studentSql);

if (!$studentStmt) {
    http_response_code(500);
    echo "Student query failed";
    exit;
}

mysqli_stmt_bind_param(
    $studentStmt,
    "i",
    $user_id
);

mysqli_stmt_execute($studentStmt);

$studentResult = mysqli_stmt_get_result($studentStmt);
$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStmt);

if (!$student) {
    http_response_code(404);
    echo "Student not found";
    exit;
}

$student_id = intval($student["id"]);

// Find Payment
$paymentSql = "
    SELECT
        fp.id,
        fp.fee_id,
        fp.student_id,
        fp.amount,
        fp.payment_date,
        fp.payment_method,
        fp.transaction_id,
        fp.receipt_no,
        fp.remarks,

        f.total_fee,
        f.paid_fee,
        f.due_fee,
        f.status

    FROM fee_payments fp

    INNER JOIN fees f
        ON fp.fee_id = f.id

    WHERE fp.id = ?
    AND fp.student_id = ?

    LIMIT 1
";

$paymentStmt = mysqli_prepare($conn, $paymentSql);

if (!$paymentStmt) {
    http_response_code(500);
    echo "Payment query failed";
    exit;
}

mysqli_stmt_bind_param(
    $paymentStmt,
    "ii",
    $payment_id,
    $student_id
);

mysqli_stmt_execute($paymentStmt);

$paymentResult = mysqli_stmt_get_result($paymentStmt);
$payment = mysqli_fetch_assoc($paymentResult);

mysqli_stmt_close($paymentStmt);

if (!$payment) {
    http_response_code(404);
    echo "Payment record not found";
    exit;
}

// Format Data

$studentName = htmlspecialchars(
    $student["full_name"] ?? "N/A"
);

$admissionNo = htmlspecialchars(
    $student["admission_no"] ?? "N/A"
);

$class = htmlspecialchars(
    $student["class"] ?? "N/A"
);

$section = htmlspecialchars(
    $student["section"] ?? "N/A"
);

$receiptNo = htmlspecialchars(
    $payment["receipt_no"] ?? "N/A"
);

$transactionId = htmlspecialchars(
    $payment["transaction_id"] ?? "N/A"
);

$paymentMethod = htmlspecialchars(
    $payment["payment_method"] ?? "N/A"
);

$paymentDate = !empty($payment["payment_date"])
    ? date(
        "d M Y",
        strtotime($payment["payment_date"])
    )
    : "N/A";

$amount = number_format(
    floatval($payment["amount"]),
    2
);

$totalFee = number_format(
    floatval($payment["total_fee"]),
    2
);

$paidFee = number_format(
    floatval($payment["paid_fee"]),
    2
);

$dueFee = number_format(
    floatval($payment["due_fee"]),
    2
);

$status = htmlspecialchars(
    $payment["status"] ?? "Pending"
);

// HTML Receipt

$html = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

body {
    font-family: DejaVu Sans, sans-serif;
    margin: 0;
    padding: 30px;
    color: #1e293b;
}

.receipt {
    border: 1px solid #dbe2ea;
    padding: 30px;
}

.header {
    text-align: center;
    border-bottom: 2px solid #2563eb;
    padding-bottom: 20px;
    margin-bottom: 25px;
}

.school-name {
    font-size: 26px;
    font-weight: bold;
    color: #1d4ed8;
}

.school-subtitle {
    font-size: 13px;
    color: #64748b;
    margin-top: 5px;
}

.title {
    font-size: 20px;
    font-weight: bold;
    margin-top: 15px;
}

.info-table,
.payment-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.info-table td {
    padding: 8px 5px;
    font-size: 13px;
}

.info-label {
    color: #64748b;
    width: 25%;
}

.payment-table th {
    background: #f1f5f9;
    padding: 10px;
    text-align: left;
    font-size: 13px;
}

.payment-table td {
    padding: 10px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

.amount {
    font-weight: bold;
}

.status {
    font-weight: bold;
}

.footer {
    margin-top: 35px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
    font-size: 11px;
    color: #64748b;
}

</style>
</head>
<body>

<div class="receipt">

    <div class="header">

        <div class="school-name">
            Future Academy
        </div>

        <div class="school-subtitle">
            School Management System
        </div>

        <div class="title">
            FEE PAYMENT RECEIPT
        </div>

    </div>

    <table class="info-table">

        <tr>

            <td class="info-label">
                Student Name
            </td>

            <td>
                <strong>' . $studentName . '</strong>
            </td>

            <td class="info-label">
                Receipt No.
            </td>

            <td>
                <strong>' . $receiptNo . '</strong>
            </td>

        </tr>

        <tr>

            <td class="info-label">
                Admission No.
            </td>

            <td>
                ' . $admissionNo . '
            </td>

            <td class="info-label">
                Payment Date
            </td>

            <td>
                ' . $paymentDate . '
            </td>

        </tr>

        <tr>
            <td class="info-label">
                Class
            </td>

            <td>
                ' . $class . ' - ' . $section . '
            </td>

            <td class="info-label">
                Payment Method
            </td>

            <td>
                ' . $paymentMethod . '
            </td>

        </tr>

    </table>

    <table class="payment-table">

        <thead>

            <tr>

                <th>
                    Description
                </th>

                <th>
                    Transaction ID
                </th>

                <th>
                    Amount
                </th>

            </tr>

        </thead>

        <tbody>

            <tr>

                <td>
                    Fee Payment
                </td>

                <td>
                    ' . $transactionId . '
                </td>

                <td class="amount">
                    ₹' . $amount . '
                </td>

            </tr>

        </tbody>

    </table>

    <table class="info-table">

        <tr>

            <td class="info-label">
                Total Fee
            </td>

            <td>
                ₹' . $totalFee . '
            </td>

        </tr>

        <tr>

            <td class="info-label">
                Total Paid
            </td>

            <td>
                ₹' . $paidFee . '
            </td>

        </tr>

        <tr>

            <td class="info-label">
                Remaining Due
            </td>

            <td>
                ₹' . $dueFee . '
            </td>

        </tr>

        <tr>

            <td class="info-label">
                Status
            </td>

            <td class="status">
                ' . $status . '
            </td>

        </tr>

    </table>


    <div class="footer">

        This is a computer-generated payment receipt.
        No signature is required.

        <br><br>

        Future Academy

    </div>

</div>

</body>

</html>
';

// Generate PDF

$options = new Options();

$options->set(
    "isRemoteEnabled",
    true
);

$options->set(
    "defaultFont",
    "DejaVu Sans"
);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper(
    "A4",
    "portrait"
);

$dompdf->render();

$filename =
    "Fee_Receipt_" .
    ($payment["receipt_no"] ?? $payment_id) .
    ".pdf";

$dompdf->stream(
    $filename,
    [
        "Attachment" => true
    ]
);

exit;
?>