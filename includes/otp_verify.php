<?php
session_start();

// -----------------------------------------------
// 1. DATABASE CONNECTION
// -----------------------------------------------
$host = "localhost";       // your database host
$dbname = "cafetrack";     // your database name
$username = "root";        // your DB username
$password = "";            // your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// -----------------------------------------------
// 2. VERIFY FORM SUBMISSION
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_SESSION['reset_email'] ?? null;
    $otp   = $_POST['otp'] ?? '';
    $newPass = $_POST['new_password'] ?? '';

    if (!$email) {
        die("Session expired. Please restart password reset.");
    }

    // -----------------------------------------------
    // 3. CHECK OTP
    // -----------------------------------------------
    $stmt = $pdo->prepare("SELECT otp, otp_expire FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Account not found.");
    }

    // OTP expired?
    if (time() > strtotime($row['otp_expire'])) {
        die("OTP expired. Please restart password reset.");
    }

    // Check OTP
    if ($otp != $row['otp']) {
        die("Invalid OTP. Try again.");
    }

    // -----------------------------------------------
    // 4. UPDATE PASSWORD
    // -----------------------------------------------
    $hashed = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ?, otp = NULL, otp_expire = NULL WHERE email = ?");
    $stmt->execute([$hashed, $email]);

    // clear session
    unset($_SESSION['reset_email']);

    echo "<script>
        alert('Password successfully reset!');
        window.location.href='login.php';
    </script>";
    exit;
}
?>

