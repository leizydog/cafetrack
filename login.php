<?php
require_once __DIR__ . '/includes/auth.php';
$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['user-email'] ?? '';
    $password = $_POST['user-pass'] ?? '';
    $res = login_user($email, $password);
    if($res['success']){
        // redirect to welcome or dashboard
      header('Location: welcome.php'); exit;
    } else {
        $message = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CafeTrack - Login</title>
<style>
  /* Coffee theme — unified inline styles */
  :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
  *{box-sizing:border-box;margin:0;padding:0;font-family:"Poppins","Segoe UI",sans-serif}
  body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(245,242,239,0.6));min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;color:var(--coffee-dark)}
  .navbar{position:fixed;top:0;left:0;right:0;background:linear-gradient(90deg,var(--coffee-medium),var(--coffee-warm));color:var(--cream);padding:18px 0;box-shadow:0 3px 10px rgba(93,64,55,0.18);z-index:100;text-align:center}
  .navbar h1{position:relative;font-size:22px;font-weight:700;letter-spacing:1.2px;margin:0;display:flex;align-items:center;justify-content:center;gap:10px}
  .auth-container{background:linear-gradient(145deg,var(--cream),#f3e9e2);padding:44px 48px;border-radius:20px;box-shadow:0 10px 40px rgba(93,64,55,0.10);width:100%;max-width:420px;text-align:center;margin-top:90px;border:1.5px solid #e0d6d2;position:relative}
  .auth-container::before{content:'';position:absolute;inset:0;border-radius:20px;background:rgba(0,0,0,0.02);pointer-events:none}
  .auth-container *{position:relative;z-index:1}
  .auth-container h2{color:var(--coffee-medium);font-size:2rem;margin-bottom:12px;font-weight:700}
  .auth-container > p:first-of-type{color:var(--coffee-warm);font-size:15px;margin-bottom:20px}
  form{display:flex;flex-direction:column;gap:16px}
  .form-group{text-align:left}
  .form-group label{display:flex;align-items:center;color:var(--coffee-medium);font-size:15px;font-weight:600;margin-bottom:7px;gap:8px}
  .form-group label i{color:var(--coffee-warm);font-size:17px}
  .form-group input{width:100%;padding:13px 15px;border:2px solid #e0d6d2;border-radius:12px;font-size:15px;background:#faf7f4;color:var(--coffee-dark);transition:box-shadow .2s,border-color .2s}
  .form-group input:focus{outline:none;border-color:var(--coffee-warm);background:#fff8f3;box-shadow:0 8px 30px rgba(169,130,116,0.08)}
  .form-group input::placeholder{color:#b5a99a}
  button[type="submit"]{background:linear-gradient(90deg,var(--coffee-medium) 60%,var(--coffee-warm) 100%);color:var(--cream);padding:13px 24px;border:none;border-radius:13px;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 18px rgba(93,64,55,0.12)}
  button[type="submit"]:hover{background:#f3e9e2;color:var(--coffee-medium);transform:translateY(-2px);box-shadow:0 12px 30px rgba(93,64,55,0.14)}
  #login-message{margin-top:12px;min-height:20px;padding:10px 12px;border-radius:8px;font-size:14px}
  #login-message.error{color:#b71c1c;background:#fff0f0;border-left:4px solid #b71c1c}
  #login-message.success{color:#2e7d32;background:#e8f5e9;border-left:4px solid #2e7d32}
  .auth-container p{margin-top:16px;color:var(--coffee-warm);font-size:14px}
  .auth-container a{color:var(--coffee-medium);text-decoration:none;font-weight:700}
  .auth-container a:hover{color:var(--coffee-warm);text-decoration:underline}
  @media(max-width:768px){.auth-container{padding:36px 30px;margin-top:76px}.auth-container h2{font-size:24px}.navbar h1{font-size:20px}body{background-size:80% auto}}
  @media(max-width:480px){body{padding:15px;background-size:100% auto}.auth-container{padding:28px 20px;margin-top:65px;border-radius:15px}.auth-container h2{font-size:20px}.form-group input{font-size:16px}button[type="submit"]{padding:12px 20px;font-size:14px}}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="navbar"><h1><i class="fas fa-mug-hot"></i> CafeKantina</h1></div>
<div class="auth-container">
  <h2>Login</h2>
  <?php if($message): ?><p id="login-message" class="error"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <form method="POST" action="login.php">
    <div class="form-group">
      <label for="user-email"><i class="fas fa-envelope"></i> Email</label>
      <input type="email" id="user-email" name="user-email" placeholder="Enter your email address" required>
    </div>
    <div class="form-group">
      <label for="user-pass"><i class="fas fa-lock"></i> Password</label>
      <input type="password" id="user-pass" name="user-pass" placeholder="Enter your password" required>
    </div>
    <button type="submit"><i class="fas fa-sign-in-alt"></i> Log In</button>
  </form>
  <p style="margin-top:10px"><a href="forgot.php">Forgot password?</a></p>
  <p>Don’t have an account? <a href="register.php">Register</a></p>
</div>
</body>
</html>
