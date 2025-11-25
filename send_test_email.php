<?php
// send_test_email.php
// Quick endpoint to test SMTP settings. Usage: open in browser or via fetch.
require_once 'config.php';
require_once 'mailer.php';

$to = $mailFromEmail;
if (isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $to = $_GET['to'];
}

$subject = 'Test email from student_grading_system';
$body = '<p>This is a test email to verify SMTP configuration.</p>';

$res = sendMail($to, $subject, $body, true);
header('Content-Type: application/json');
if ($res === true) {
    echo json_encode(['status' => 'ok', 'to' => $to]);
} else {
    echo json_encode(['status' => 'error', 'message' => $res]);
}

?>
