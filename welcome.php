<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$username = htmlspecialchars($user['username'] ?? 'User');
$role = htmlspecialchars($user['role'] ?? 'staff');

// Store that user has seen welcome page
$_SESSION['welcome_seen'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CafeKantina - Welcome</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  /* Coffee theme — unified inline styles */
  :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
  *{box-sizing:border-box;margin:0;padding:0;font-family:"Poppins","Segoe UI",sans-serif}
  body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(245,242,239,0.6));min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;color:var(--coffee-dark)}
  .navbar{background:linear-gradient(90deg,var(--coffee-medium),var(--coffee-warm));color:var(--cream);width:100%;padding:14px 0;display:flex;justify-content:space-between;align-items:center;box-shadow:0 3px 10px rgba(93,64,55,0.18);position:fixed;top:0;left:0;right:0;z-index:100}
  .navbar h1{position:relative;font-size:22px;font-weight:700;letter-spacing:1.2px;margin:0;display:flex;align-items:center;justify-content:center;gap:10px}
  .nav-links a{color:var(--cream);text-decoration:none;font-size:16px;padding:8px 15px;background-color:var(--coffee-warm);border-radius:8px;transition:all .18s;font-weight:600}
  .nav-links a:hover{background:var(--cream);color:var(--coffee-medium)}
  .auth-container{background:linear-gradient(145deg,var(--cream),#f3e9e2);margin:90px auto 0;padding:44px 54px;border-radius:22px;box-shadow:0 10px 40px rgba(93,64,55,0.10);text-align:center;max-width:520px;width:90%;border:1.5px solid #e0d6d2}
  .auth-container h2{color:var(--coffee-medium);font-size:2rem;margin-bottom:12px;font-weight:700}
  .auth-container p{color:var(--coffee-warm);font-size:16px;margin-bottom:8px}
  .meta{margin-bottom:18px;color:var(--coffee-warm);font-weight:600}
  .role-buttons{display:flex;justify-content:center;gap:20px}
  .role-buttons button{background:linear-gradient(90deg,var(--coffee-medium) 60%,var(--coffee-warm) 100%);color:var(--cream);border:none;padding:12px 25px;font-size:16px;border-radius:13px;cursor:pointer;transition:all .18s;font-weight:700;box-shadow:0 2px 8px rgba(93,64,55,0.08)}
  .role-buttons button:hover{background:var(--cream);color:var(--coffee-medium);box-shadow:0 4px 12px rgba(93,64,55,0.13);transform:translateY(-3px) scale(1.05)}
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;justify-content:center;align-items:center;z-index:999}
  .modal{background:var(--cream);padding:30px 25px;border-radius:15px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 25px rgba(93,64,55,0.10);border:1.5px solid #e0d6d2}
  .modal h3{color:var(--coffee-medium);margin-bottom:20px}
  .modal p{margin-bottom:25px;color:var(--coffee-warm);font-size:16px}
  .modal button{padding:10px 25px;border:none;border-radius:8px;font-size:16px;cursor:pointer;margin:0 10px;transition:.18s;font-weight:600}
  .modal .cancel-btn{background:#ccc;color:#333}.modal .cancel-btn:hover{background:#bbb}
  .modal .logout-btn{background:#d9534f;color:#fff}.modal .logout-btn:hover{background:#c9302c}
  @media(max-width:480px){.auth-container{padding:30px 20px}.role-buttons{flex-direction:column}.role-buttons button{width:100%}body{background-size:100% auto}}
</style>
</head>
<body>

  <!-- Navbar -->
  <div class="navbar" style="justify-content:center;">
    <h1 style="display:flex;align-items:center;justify-content:center;gap:10px;"><i class="fas fa-mug-hot"></i> CafeKantina</h1>
    <div class="nav-links" style="position:absolute;right:24px;top:0;display:flex;align-items:center;height:100%;">
      <a onclick="showLogoutModal()"><i class="fas fa-sign-out-alt"></i> Log Out</a>
    </div>
  </div>

  <!-- Welcome Container -->
  <div class="auth-container">
    <h2 id="welcome-message">Welcome back, <?php echo $username ?: 'User'; ?>!</h2>
    <p class="meta">Role: <?php echo $role ?: 'N/A'; ?></p>
    <p>Click the button below to continue to your dashboard:</p>
    <div class="role-buttons">
      <button onclick="location.href='staff-login.php'">Staff Login</button>
      <?php if ($role === 'admin'): ?>
        <button onclick="location.href='adminlogin.php'">Admin Login</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Logout Modal -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to log out?</p>
      <button class="cancel-btn" onclick="hideLogoutModal()">Cancel</button>
      <button class="logout-btn" onclick="logout()">Logout</button>
    </div>
  </div>

<script>
  // Logout Modal Functions
  function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
  function hideLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
  function logout() { window.location.href = 'logout.php'; }
</script>

</body>
</html>
