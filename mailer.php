
<?php
// mailer.php - Email sending functions using PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

function sendAccountCreatedEmail($email, $username) {
    global $mailFromName, $mailUsername, $mailPassword, $mailHost, $mailPort, $mailSMTPSecure, $mailDebug;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $mailHost ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $smtpPass = str_replace(' ', '', $mailPassword);
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = ($mailSMTPSecure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mailPort ?? 465;
        $mail->setFrom($mailUsername, $mailFromName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Account Successfully Created';
        $mail->Body = '<div style="font-family:Arial,sans-serif;padding:24px;background:#f9f9f9;border-radius:8px;max-width:400px;margin:auto;">
            <h2 style="color:#2d6a4f;">Welcome, ' . htmlspecialchars($username) . '!</h2>
            <p>Your account has been <b>successfully created</b>.</p>
            <p>System: <b>Richwell Colleges Student Grading System</b></p>
            <hr style="margin:16px 0;">
            <p style="font-size:13px;color:#888;">If you did not request this, please ignore this email.</p>
        </div>';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Account Created Email Error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendOtpEmail($email, $otp, $mailDebug = false, & $debugOutput = null) {
    global $mailFromName, $mailUsername, $mailPassword, $mailHost, $mailPort, $mailSMTPSecure, $mailDebug, $mailFromEmail;
    $mail = new PHPMailer(true);
    if ($debugOutput === null) {
        $debugOutput = '';
    }
    try {
        $mail->isSMTP();
        $mail->Host = $mailHost ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $smtpPass = str_replace(' ', '', $mailPassword);
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = ($mailSMTPSecure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mailPort ?? 465;
        // Helpful options for local development (adjust for production)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Use config variable for from address
        $mail->setFrom($mailFromEmail, $mailFromName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = '<div style="font-family:Arial,sans-serif;padding:24px;background:#f9f9f9;border-radius:8px;max-width:400px;margin:auto;">
            <h2 style="color:#d00000;">Your OTP Code</h2>
            <div style="font-size:2em;font-weight:bold;color:#2d6a4f;margin:16px 0;">' . htmlspecialchars($otp) . '</div>
            <p style="color:#d00000;font-weight:bold;">This code expires in 60 seconds.</p>
            <hr style="margin:16px 0;">
            <p style="font-size:13px;color:#888;">If you did not request this, please ignore this email.</p>
        </div>';

        if ($mailDebug) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                $debugOutput .= trim($str) . "\n";
            };
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Return detailed error (ErrorInfo + debug output) for troubleshooting
        $err = $mail->ErrorInfo ?: $e->getMessage();
        if (!empty($debugOutput)) {
            $err .= "\n\nDebug Output:\n" . $debugOutput;
        }
        error_log('OTP Email Error: ' . $err);
        return $err;
    }
}
