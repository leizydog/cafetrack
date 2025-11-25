<?php
// Staff login page - uses session-based authentication
require_once __DIR__ . '/includes/auth.php';
// No require_login here because this is the login page
$login_message = '';

// Handle server-side staff login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim($_POST['staff-identifier'] ?? '');
  $password = $_POST['staff-pass'] ?? '';

  if ($identifier === '' || $password === '') {
    $login_message = 'Please enter username and password.';
  } else {
    // Use login_user function which handles staff table
    $res = login_user($identifier, $password);
    if ($res['success']) {
      // Always redirect to welcome page first
      header('Location: welcome.php');
      exit();
    } else {
      $login_message = $res['message'] ?? 'Invalid credentials.';
    }
  }
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
  $user = current_user();
  if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
  } else {
    header('Location: dashboard.php');
  }
  exit;
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CafeKantina - Staff Login</title>
<style>
  /* Coffee theme — unified inline styles */
  :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
  *{box-sizing:border-box;margin:0;padding:0;font-family:"Poppins","Segoe UI",sans-serif}
  body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(245,242,239,0.6));min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;color:var(--coffee-dark)}
  .navbar{position:fixed;top:0;left:0;right:0;background:linear-gradient(90deg,var(--coffee-medium),var(--coffee-warm));color:var(--cream);padding:18px 0;box-shadow:0 3px 10px rgba(93,64,55,0.18);z-index:100;display:flex;justify-content:space-between;align-items:center}
  .navbar h1{position:relative;font-size:22px;font-weight:700;letter-spacing:1.2px;margin:0;display:flex;align-items:center;justify-content:center;gap:10px}
  .navbar a{color:var(--cream);text-decoration:none;cursor:pointer;transition:color .18s;font-size:15px;font-weight:600;padding:8px 15px;border-radius:8px}
  .navbar a:hover{background:var(--cream);color:var(--coffee-medium)}
  .auth-container{background:linear-gradient(145deg,var(--cream),#f3e9e2);padding:44px 54px;border-radius:22px;box-shadow:0 10px 40px rgba(93,64,55,0.10);text-align:center;max-width:420px;width:100%;margin-top:90px;border:1.5px solid #e0d6d2}
  .auth-container h2{color:var(--coffee-medium);font-size:2rem;margin-bottom:25px;font-weight:700}
  form{display:flex;flex-direction:column;gap:18px}
  .form-group{text-align:left}
  label{display:flex;align-items:center;margin-bottom:7px;font-size:15px;color:var(--coffee-medium);font-weight:600;gap:8px}
  label i{color:var(--coffee-warm);font-size:17px}
  input{width:100%;padding:13px 15px;border:2px solid #e0d6d2;border-radius:13px;font-size:15px;background:#faf7f4;color:var(--coffee-dark);transition:all .3s}
  input:focus{outline:none;border-color:var(--coffee-warm);background:#fff8f3;box-shadow:0 0 0 3px rgba(169,130,116,0.10)}
  .password-container{position:relative;display:flex;align-items:center}
  .toggle-pass{position:absolute;right:12px;background:transparent;border:none;cursor:pointer;font-size:12px;color:#d49a6a;font-weight:600}
  .toggle-pass:hover{color:#c08a5a}
  button[type="submit"],#switch-user{padding:13px 24px;border:none;border-radius:13px;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 2px 8px rgba(93,64,55,0.08)}
  button[type="submit"]{background:linear-gradient(90deg,var(--coffee-medium) 60%,var(--coffee-warm) 100%);color:var(--cream);margin-top:8px}
  button[type="submit"]:hover{background:var(--cream);color:var(--coffee-medium);box-shadow:0 4px 12px rgba(93,64,55,0.13);transform:translateY(-2px)}
  #switch-user{background:linear-gradient(90deg,var(--coffee-warm),var(--coffee-medium));color:var(--cream);margin-top:10px}
  #switch-user:hover{background:var(--cream);color:var(--coffee-medium);box-shadow:0 4px 12px rgba(93,64,55,0.13);transform:translateY(-2px)}
  #login-message{margin-top:18px;font-size:14px;min-height:22px;font-weight:500;padding:8px 12px;border-radius:8px}
  #login-message.error{color:#c62828;background:#ffebee;border-left:4px solid #c62828}
  #login-message.success{color:#2e7d32;background:#e8f5e9;border-left:4px solid #2e7d32}
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;justify-content:center;align-items:center;z-index:999}
  .modal{background:var(--cream);padding:35px 30px;border-radius:15px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(93,64,55,0.10);animation:fadeIn 0.3s;border:1.5px solid #e0d6d2}
  .modal h3{color:var(--coffee-medium);margin-bottom:15px;font-size:20px}
  .modal p{margin-bottom:25px;color:var(--coffee-warm);font-size:15px}
  .cancel-btn{background:#e0d6d2;color:var(--coffee-medium)}
  .cancel-btn:hover{background:#d9ccc2;transform:translateY(-2px)}
  .logout-btn{background:linear-gradient(135deg,#d32f2f 0%,#c62828 100%);color:#fff}
  .logout-btn:hover{background:linear-gradient(135deg,#c62828 0%,#b71c1c 100%);transform:translateY(-2px)}
  @media(max-width:768px){.auth-container{padding:35px 30px;max-width:100%;margin-top:70px}.auth-container h2{font-size:24px}.navbar h1{font-size:20px}.navbar a{font-size:12px}body{background-size:80% auto}}
  @media(max-width:480px){body{padding:15px;background-size:100% auto}.navbar{padding:12px 20px;flex-wrap:wrap;gap:12px}.navbar h1{font-size:18px;flex:1;text-align:left}.navbar a{font-size:11px}.auth-container{padding:30px 20px;margin-top:65px;border-radius:15px;max-width:100%}.auth-container h2{font-size:20px}form{gap:14px}input{font-size:16px}button[type="submit"],#switch-user{padding:12px 20px;font-size:14px}.modal{padding:25px 20px}.modal button{padding:10px 15px}}
</style>
      font-size: 12px;
      margin: 5px;
    }
  }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="navbar" style="justify-content:center;">
  <h1 style="display:flex;align-items:center;justify-content:center;gap:10px;"><i class="fas fa-mug-hot"></i> CafeKantina</h1>
  <div style="position:absolute;right:24px;top:0;display:flex;align-items:center;height:100%;">
    <a onclick="showLogoutModal()"><i class="fas fa-sign-out-alt"></i> Log Out</a>
  </div>
</div>
  #login-message.error{color:#a98274;background:#fff8f3;border-left:4px solid #a98274}

<div class="auth-container">
  <h2><i class="fas fa-user-tie"></i> Staff Login</h2>
  <form method="POST">
    <div class="form-group">
      <label id="username-label"><i class="fas fa-user"></i> Username or Email</label>
      <input type="text" id="staff-username" name="staff-identifier" placeholder="Enter username or email" value="<?php echo htmlspecialchars($username ?? ''); ?>" <?php echo $username ? 'readonly' : ''; ?> >
    </div>
    <div class="form-group">
      <label><i class="fas fa-lock"></i> Password</label>
      <div class="password-container">
        <input type="password" id="staff-password" name="staff-pass" placeholder="Enter your password" required>
        <button type="button" class="toggle-pass" onclick="togglePassword()">Show</button>
      </div>
    </div>
    <button type="submit"><i class="fas fa-sign-in-alt"></i> Log In</button>
    <button type="button" id="switch-user" style="display:none;" onclick="switchUser()"><i class="fas fa-exchange-alt"></i> Switch User</button>
  </form>
  <p id="login-message" class="error"></p>
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
// Pre-fill saved username from session if available
const sessionUser = <?php echo json_encode($username); ?>;
if(sessionUser) localStorage.setItem('staffUsername', sessionUser);

window.onload = function() {
  const savedUsername = localStorage.getItem("staffUsername");
  const message = document.getElementById("login-message");
  
  if(savedUsername){
    document.getElementById("staff-username").value = savedUsername;
    document.getElementById("username-label").innerHTML = '<i class="fas fa-user"></i> Username (saved)';
    document.getElementById("switch-user").style.display = "flex";
  } else {
    document.getElementById("staff-username").removeAttribute("readonly");
    document.getElementById("staff-username").placeholder = "Enter username or email";
  }
  
  // Display any login message
  if(message.textContent.trim()) {
    message.classList.add('error');
  }
};

function togglePassword() {
  const passInput = document.getElementById("staff-password");
  const btn = document.querySelector(".toggle-pass");
  if(passInput.type === "password"){
    passInput.type = "text";
    btn.textContent = "Hide";
  } else {
    passInput.type = "password";
    btn.textContent = "Show";
  }
}

function switchUser(){
  localStorage.removeItem("staffUsername");
  localStorage.removeItem("staffPassword");
  const usernameField = document.getElementById("staff-username");
  usernameField.removeAttribute("readonly");
  usernameField.value = "";
  usernameField.placeholder = "Enter username or email";
  document.getElementById("username-label").innerHTML = '<i class="fas fa-user"></i> Username or Email';
  document.getElementById("switch-user").style.display = "none";
  document.getElementById("login-message").textContent = "";
}

// Logout Modal
function showLogoutModal(){
  document.getElementById('logoutModal').style.display = 'flex';
}

function hideLogoutModal(){
  document.getElementById('logoutModal').style.display = 'none';
}

function logout(){
  localStorage.removeItem("staffUsername");
  localStorage.removeItem("staffPassword");
  window.location.href = 'login.php';
}
</script>

</body>
</html>
