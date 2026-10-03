<?php
declare(strict_types=1);

// Keep PHP warnings/notices from corrupting the JSON response returned to fetch().
ini_set('display_errors', '0');

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/leads.php';

$smtpConfigPath = __DIR__ . '/smtp-config.php';
if (!is_file($smtpConfigPath)) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Email service is not configured. Please contact the site administrator.',
        'redirect' => ''
    ]);
    exit;
}

$smtp = require $smtpConfigPath;

/*
|--------------------------------------------------------------------------
| SAGI Realty Lead Form
|--------------------------------------------------------------------------
| Sends validated enquiries using authenticated Brevo SMTP.
*/

$subjectPrefix = 'New SAGI Realty Website Enquiry';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, string $redirect = '') {
    http_response_code($success ? 200 : 422);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// Honeypot anti-spam field.
if (!empty($_POST['website'] ?? '')) {
    respond(true, 'Thank you.', 'thank-you.html');
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$interest = trim((string)($_POST['interest'] ?? 'Project Details'));
$consent = (string)($_POST['consent'] ?? '');
$source = trim((string)($_POST['source'] ?? 'SAGI Realty Landing Page'));

$errors = [];

$nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
if ($name === '' || $nameLength < 2 || $nameLength > 80) {
    $errors[] = 'Please enter a valid name.';
}

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    $errors[] = 'Please enter a valid 10-digit Indian mobile number.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    $errors[] = 'Please enter a valid email address.';
}

$allowedInterests = ['3 BHK', '4 BHK', 'Both 3 & 4 BHK', 'Investment / Details'];
if (!in_array($interest, $allowedInterests, true)) {
    $interest = 'Project Details';
}

if ($consent !== '1') {
    $errors[] = 'Consent is required.';
}

if ($errors) {
    respond(false, implode(' ', $errors));
}

try {
    leads_add([
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'interest' => $interest,
        'source' => $source,
    ]);
} catch (Throwable $exception) {
    error_log('SAGI Realty lead storage error: ' . $exception->getMessage());
    respond(false, 'Your enquiry could not be saved right now. Please try again shortly.');
}

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeInterest = htmlspecialchars($interest, ENT_QUOTES, 'UTF-8');
$safeSource = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');

$subject = $subjectPrefix . ' - ' . $name;

$message = "
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family:Arial,sans-serif;color:#18243a;'>
  <h2 style='color:#0b2a50;'>New SAGI Realty Enquiry</h2>
  <table cellpadding='8' cellspacing='0' border='0'>
    <tr><td><strong>Name</strong></td><td>{$safeName}</td></tr>
    <tr><td><strong>Phone</strong></td><td>{$safePhone}</td></tr>
    <tr><td><strong>Email</strong></td><td>{$safeEmail}</td></tr>
    <tr><td><strong>Interest</strong></td><td>{$safeInterest}</td></tr>
    <tr><td><strong>Source</strong></td><td>{$safeSource}</td></tr>
    <tr><td><strong>Submitted</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
  </table>
</body>
</html>
";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string)$smtp['host'];
    $mail->Port = (int)$smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$smtp['username'];
    $mail->Password = (string)$smtp['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    $mail->setFrom((string)$smtp['from_email'], (string)$smtp['from_name']);
    $mail->addAddress((string)$smtp['to_email']);
    if (strcasecmp((string)$smtp['to_email'], 'info@sagirealty.co') !== 0) {
        $mail->addAddress('info@sagirealty.co');
    }
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->AltBody = "New SAGI Realty Enquiry\n"
        . "Name: {$name}\n"
        . "Phone: {$phone}\n"
        . "Email: {$email}\n"
        . "Interest: {$interest}\n"
        . "Source: {$source}\n"
        . 'Submitted: ' . date('Y-m-d H:i:s');

    $mail->send();
} catch (Exception $exception) {
    error_log('SAGI Realty SMTP error: ' . $exception->getMessage());
    // The enquiry is already safely stored for the admin. Email is only a notification.
}

respond(true, 'Thank you. Your enquiry has been submitted.', 'thank-you.html');
?>
