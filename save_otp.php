<?php
// save_otp.php - Generate and send OTP via email
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }

    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['id'];
        // Generate 6-digit OTP
        $otp = sprintf("%06d", rand(0, 999999));
        $expiry = date('Y-m-d H:i:s', time() + ($otp_expiry_seconds ?? 60));

        // Remove old OTPs for this user
        $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->bind_param("i", $user_id);
        $del->execute();
        $del->close();

        // Save OTP and expiry in database (table: password_resets)
        $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $user_id, $otp, $expiry);
        $stmt2->execute();
        $stmt2->close();

        // Save OTP in session for validation
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_otp_time'] = time();

        // Send OTP via email
        require_once 'mailer.php';
        // Enable PHPMailer debug output
        $debugOutput = '';
        $mailDebug = true;
        // Patch sendOtpEmail to accept debug output
        $sendResult = sendOtpEmail($email, $otp, $mailDebug, $debugOutput);
        if ($sendResult === true) {
            echo json_encode([
                'status' => 'sent',
                'expires_in' => $otp_expiry_seconds,
                'message' => 'OTP sent to your email successfully',
                'debug' => $debugOutput
            ]);
        } else {
            // Always return error and debug output, fallback to plain text if JSON fails
            $response = [
                'status' => 'error',
                'message' => 'Failed to send OTP. Please try again.',
                'error' => $sendResult,
                'debug' => $debugOutput
            ];
            $json = json_encode($response);
            if ($json === false) {
                header('Content-Type: text/plain');
                echo "OTP ERROR\n" . print_r($response, true);
            } else {
                echo $json;
            }
        }
    } else {
        echo json_encode([
            'status' => 'no_user',
            'message' => 'Email not registered in the system'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>