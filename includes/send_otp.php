<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;

        // YOUR GMAIL + APP PASSWORD
        $mail->Username = "jerbertmape619@gmail.com";  // <-- change this
        $mail->Password = "qxwu saax fxlo ldbo";    // <-- change this

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // EMAIL DETAILS
        $mail->setFrom("yourgmail@gmail.com", "CafeKantina");
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your CafeKantina OTP Code";
        $mail->Body = "
            <h2>Your OTP Code</h2>
            <p>Your password reset OTP is:</p>
            <h1> $otp </h1>
            <p>This code expires soon. Do not share it with anyone.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}
