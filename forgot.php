<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$message = '';
$step = $_SESSION['reset_step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* STEP 1 — Enter Email & Send OTP */
    if ($step == 1) {
        $email = trim($_POST['email'] ?? '');

        // CHECK IN *users* TABLE (corrected)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = "Email not found.";
        } else {

            $_SESSION['reset_user'] = $user['id'];
            $_SESSION['reset_email'] = $email;

            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['reset_otp'] = $otp;

            // Send OTP via PHPMailer
            require __DIR__ . "/includes/send_otp.php";
            sendOTP($email, $otp);

            $_SESSION['reset_step'] = 2;
            $step = 2;
            $message = "OTP sent to your email.";
        }
    }

    /* STEP 2 — Verify OTP */
    else if ($step == 2) {

        $entered = trim($_POST['otp'] ?? '');

        if ($entered == $_SESSION['reset_otp']) {
            $_SESSION['reset_step'] = 3;
            $step = 3;
        } else {
            $message = "Invalid OTP.";
        }
    }

    /* STEP 3 — Save New Password */
    else if ($step == 3) {

        $new = $_POST['new_pw'] ?? '';
        $confirm = $_POST['confirm_pw'] ?? '';

        if ($new !== $confirm) {
            $message = "Passwords do not match.";
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*]).{10,}$/', $new)) {
            $message = "Password does not meet requirements.";
        } else {

            $hash = password_hash($new, PASSWORD_DEFAULT);
            $uid = $_SESSION['reset_user'];

            // Update password in *users* table (corrected)
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hash, $uid]);

            // Cleanup session
            unset($_SESSION['reset_step'], $_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_email']);

            $message = "Password updated successfully.";
            $step = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - CafeKantina</title>

<style>
/* ======================
   COFFEE LOGIN-THEME  
   (EXACT DESIGN PRESERVED)
   ====================== */
:root{
  --coffee-dark:#4a2e21;
  --coffee-mid:#6d4c41;
  --coffee-light:#a98274;
  --cream-bg:#f9efe6;
  --white:#ffffff;
}

body{
  background: linear-gradient(145deg,#f3e9e2,#fff8f3);
  display:flex;
  justify-content:center;
  align-items:center;
  min-height:100vh;
  padding:25px;
  font-family:"Poppins",sans-serif;
  color:var(--coffee-dark);
}

/* MAIN CARD */
.container{
  width:100%;
  max-width:520px;
  background:var(--cream-bg);
  border-radius:16px;
  padding:35px 32px;
  box-shadow:0 12px 35px rgba(0,0,0,0.08);
  border:1px solid #e5d6cd;
}

/* HEADER */
h1{
  font-size:26px;
  font-weight:800;
  margin-bottom:4px;
  display:flex;
  align-items:center;
  gap:10px;
}

.subtitle{
  color:#866c5f;
  margin-bottom:25px;
  font-size:14px;
}

/* FORM */
label{
  font-weight:600;
  font-size:14px;
  margin-bottom:5px;
}

input{
  width:100%;
  padding:12px 14px;
  margin-bottom:15px;
  background:#fff;
  border-radius:12px;
  border:1px solid #e0d5cf;
  font-size:15px;
  transition:0.2s;
}

input:focus{
  border-color:var(--coffee-mid);
  box-shadow:0 0 0 3px rgba(109,76,65,0.18);
}

/* REQUIREMENTS BOX */
.requirements{
  background:#ffffff;
  border-radius:12px;
  border:1px solid #ebdbd3;
  padding:14px 18px;
  font-size:13px;
  margin-top:5px;
  line-height:1.55;
  color:#6b5a57;
}

/* BUTTON */
button{
  width:100%;
  padding:14px;
  background:linear-gradient(135deg,#6d4c41,#4a2e21);
  color:#fff;
  border:none;
  border-radius:12px;
  margin-top:18px;
  font-size:16px;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 4px 18px rgba(80,50,38,0.22);
  transition:0.22s;
}

button:hover{
  transform:translateY(-2px);
  box-shadow:0 6px 22px rgba(60,40,30,0.26);
}

/* BACK LINK */
.back-link{
  text-align:center;
  margin-top:18px;
}

.back-link a{
  color:var(--coffee-mid);
  text-decoration:none;
  font-size:14px;
  font-weight:600;
  display:inline-flex;
  align-items:center;
  gap:6px;
}

.back-link a:hover{
  color:var(--coffee-dark);
}

/* SUCCESS / ERROR MESSAGES */
.msg{
  padding:14px;
  border-radius:10px;
  margin-bottom:12px;
  display:none;
  font-size:14px;
}

.msg.error{
  display:block;
  background:#ffe8e8;
  color:#c62828;
  border-left:4px solid #c62828;
}

.msg.success{
  display:block;
  background:#e8f6e8;
  color:#2e7d32;
  border-left:4px solid #2e7d32;
}
</style>

</head>
<body>

<div class="container">

    <h1><i class="fas fa-key"></i> Reset Password</h1>
    <p class="subtitle">Use OTP to reset your password</p>

    <?php if($message): ?>
        <div class="msg <?php echo (strpos($message,'successfully') !== false) ? 'success' : 'error'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <?php if($step == 1): ?>
            <label>Email</label>
            <input type="email" name="email" placeholder="Enter your email" required>
        <?php endif; ?>

        <?php if($step == 2): ?>
            <label>Enter OTP</label>
            <input type="text" name="otp" placeholder="6-digit OTP" required>
        <?php endif; ?>

        <?php if($step == 3): ?>
            <label>New Password</label>
            <input type="password" name="new_pw" placeholder="Enter new password" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm_pw" placeholder="Confirm new password" required>

            <div class="requirements">
                <strong>Password requirements:</strong><br>
                • At least 10 characters<br>
                • Uppercase letter (A–Z)<br>
                • Lowercase letter (a–z)<br>
                • Number (0–9)<br>
                • Special character (!@#$%^&*)
            </div>
        <?php endif; ?>

        <button type="submit">✔ Continue</button>
    </form>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to login</a>
    </div>

</div>

</body>
</html>
