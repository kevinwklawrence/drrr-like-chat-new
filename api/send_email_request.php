<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

error_log("EMAIL_REQUEST: Request received");

if (!isset($_SESSION['user'])) {
    error_log("EMAIL_REQUEST: User not authenticated");
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

include '../db_connect.php';

$to_email = trim($_POST['to_email'] ?? '');
$message = trim($_POST['message'] ?? '');
$user_id = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? 'Unknown';

$allowed_emails = ['admin@duranu.net', 'bugs@duranu.net', 'request@duranu.net'];

if (!in_array($to_email, $allowed_emails)) {
    error_log("EMAIL_REQUEST: Invalid email - $to_email");
    echo json_encode(['status' => 'error', 'message' => 'Invalid recipient email']);
    exit;
}

if (empty($message)) {
    error_log("EMAIL_REQUEST: Empty message");
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit;
}

error_log("EMAIL_REQUEST: Preparing to send - to=$to_email, user=$username");

$signature = "\n\n---\nSent by: $username ($user_id)\nFrom: Duranu Email Request System";
$full_message = $message . $signature;

$subject = "User Request from $username";
$headers = "From: auto@duranu.net\r\n";
$headers .= "Reply-To: auto@duranu.net\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$mail_result = mail($to_email, $subject, $full_message, $headers);
error_log("EMAIL_REQUEST: Mail function result - " . ($mail_result ? 'SUCCESS' : 'FAILED'));

if ($mail_result) {
    error_log("EMAIL_REQUEST: Email sent successfully to $to_email");
    echo json_encode(['status' => 'success', 'message' => 'Email sent successfully']);
} else {
    error_log("EMAIL_REQUEST: Failed to send email to $to_email");
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email']);
}
?>