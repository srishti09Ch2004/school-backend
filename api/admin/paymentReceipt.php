<?php

require_once __DIR__ . "/../../../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

include("../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo "Invalid request method";
    exit;
}

$payment_id = isset($_GET["payment_id"])
    ? intval($_GET["payment_id"])
    : 0;

if ($payment_id <= 0) {
    http_response_code(400);
    echo "payment_id is required";
    exit;
}

// Fetch payment + student + fee details

$sql = "
SELECT
    fp.id AS payment_id,
    fp.amount,
    fp.payment_date,
    fp.payment_method,
    fp.transaction_id,
    fp.receipt_no,
    fp.remarks,
    fp.created_at,

    f.id AS fee_id,
    f.total_fee,
    f.paid_fee,
    f.due_fee,
    f.status AS fee_status,

    s.id AS student_id,
    s.admission_no,
    s.class,
    s.section,
    s.roll_no,

    u.full_name,
    u.email

FROM fee_payments fp

INNER JOIN fees f
    ON fp.fee_id = f.id

INNER JOIN students s
    ON fp.student_id = s.id

INNER JOIN users u
    ON s.user_id = u.id

WHERE fp.id = ?

LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    http_response_code(500);
    echo "Database prepare failed";
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $payment_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$payment = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$payment) {
    http_response_code(404);
    echo "Payment receipt not found";
    exit;
}

//  Dompdf Configuration

$options = new Options();

$options->set("isRemoteEnabled", true);
$options->set("defaultFont", "DejaVu Sans");

$dompdf = new Dompdf($options);

// Format Data

$receiptNo = htmlspecialchars(
    $payment["receipt_no"] ?: "N/A"
);

$studentName = htmlspecialchars(
    $payment["full_name"] ?: "N/A"
);

$email = htmlspecialchars(
    $payment["email"] ?: "N/A"
);

$admissionNo = htmlspecialchars(
    $payment["admission_no"] ?: "N/A"
);

$class = htmlspecialchars(
    $payment["class"] ?: "N/A"
);

$section = htmlspecialchars(
    $payment["section"] ?: "N/A"
);

$rollNo = htmlspecialchars(
    $payment["roll_no"] ?: "N/A"
);

$amount = number_format(
    (float)$payment["amount"],
    2
);

$totalFee = number_format(
    (float)$payment["total_fee"],
    2
);

$paidFee = number_format(
    (float)$payment["paid_fee"],
    2
);

$dueFee = number_format(
    (float)$payment["due_fee"],
    2
);

$paymentDate = date(
    "d M Y",
    strtotime($payment["payment_date"])
);

$paymentMethod = htmlspecialchars(
    $payment["payment_method"] ?: "N/A"
);

$transactionId = htmlspecialchars(
    $payment["transaction_id"] ?: "N/A"
);

$remarks = htmlspecialchars(
    $payment["remarks"] ?: "No remarks"
);

/*
|--------------------------------------------------------------------------
| Receipt HTML
|--------------------------------------------------------------------------
*/

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
    color: #222;
    font-size: 13px;
}

.receipt {
    border: 1px solid #ddd;
    padding: 28px;
}

.header {
    text-align: center;
    border-bottom: 2px solid #222;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.school-name {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.school-subtitle {
    font-size: 12px;
    color: #666;
}

.receipt-title {
    font-size: 20px;
    font-weight: bold;
    margin-top: 18px;
}

.receipt-info {
    width: 100%;
    margin-bottom: 20px;
}

.receipt-info td {
    padding: 5px 0;
}

.right {
    text-align: right;
}

.section-title {
    font-size: 15px;
    font-weight: bold;
    background: #f3f3f3;
    padding: 8px;
    margin-top: 15px;
    margin-bottom: 8px;
}

table.details {
    width: 100%;
    border-collapse: collapse;
}

table.details td {
    border: 1px solid #ddd;
    padding: 9px;
}

.label {
    font-weight: bold;
    width: 35%;
    background: #fafafa;
}

.amount-box {
    border: 2px solid #222;
    padding: 15px;
    margin-top: 20px;
    text-align: center;
}

.amount-label {
    font-size: 12px;
    color: #555;
}

.amount {
    font-size: 24px;
    font-weight: bold;
    margin-top: 5px;
}

.footer {
    margin-top: 35px;
    text-align: center;
    font-size: 11px;
    color: #777;
}

.signature {
    margin-top: 45px;
    text-align: right;
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

        <div class="receipt-title">
            PAYMENT RECEIPT
        </div>

    </div>

    <table class="receipt-info">

        <tr>

            <td>
                <strong>Receipt No:</strong>
                ' . $receiptNo . '
            </td>

            <td class="right">
                <strong>Date:</strong>
                ' . $paymentDate . '
            </td>

        </tr>

    </table>

    <div class="section-title">
        Student Details
    </div>

    <table class="details">

        <tr>

            <td class="label">
                Student Name
            </td>

            <td>
                ' . $studentName . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Admission No
            </td>

            <td>
                ' . $admissionNo . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Class
            </td>

            <td>
                ' . $class . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Section
            </td>

            <td>
                ' . $section . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Roll No
            </td>

            <td>
                ' . $rollNo . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Email
            </td>

            <td>
                ' . $email . '
            </td>

        </tr>

    </table>

    <div class="section-title">
        Payment Details
    </div>

    <table class="details">

        <tr>

            <td class="label">
                Payment Amount
            </td>

            <td>
                ₹' . $amount . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Payment Method
            </td>

            <td>
                ' . $paymentMethod . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Transaction ID
            </td>

            <td>
                ' . $transactionId . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Remarks
            </td>

            <td>
                ' . $remarks . '
            </td>

        </tr>

    </table>

    <div class="amount-box">

        <div class="amount-label">
            AMOUNT PAID
        </div>

        <div class="amount">
            ₹' . $amount . '
        </div>

    </div>

    <div class="section-title">
        Fee Summary
    </div>

    <table class="details">

        <tr>

            <td class="label">
                Total Fee
            </td>

            <td>
                ₹' . $totalFee . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Total Paid
            </td>

            <td>
                ₹' . $paidFee . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Remaining Due
            </td>

            <td>
                ₹' . $dueFee . '
            </td>

        </tr>

        <tr>

            <td class="label">
                Fee Status
            </td>

            <td>
                ' . htmlspecialchars($payment["fee_status"]) . '
            </td>

        </tr>

    </table>

    <div class="signature">
        Authorized Signature
    </div>

    <div class="footer">

        This is a system-generated payment receipt.
        <br>
        Future Academy

    </div>

</div>

</body>

</html>
';
//  Generate PDF

$dompdf->loadHtml($html);

$dompdf->setPaper("A4", "portrait");

$dompdf->render();

// Download PDF

$filename = "Payment_Receipt_" .
    ($payment["receipt_no"] ?: $payment_id) .
    ".pdf";

$dompdf->stream(
    $filename,
    [
        "Attachment" => true
    ]
);

exit;
?>