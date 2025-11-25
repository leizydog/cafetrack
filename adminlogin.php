<?php
// Simple admin PIN page converted to PHP; does not require login (it's the login page)
require_once __DIR__ . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CafeKantina - Admin Login</title>
<style>
  /* Coffee theme — unified inline styles for admin login */
  :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
  *{box-sizing:border-box;margin:0;padding:0;font-family:"Poppins","Segoe UI",sans-serif}
  body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(245,242,239,0.6));min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;color:var(--coffee-dark)}
  .navbar{position:fixed;top:0;left:0;right:0;width:100%;background:linear-gradient(90deg,var(--coffee-medium),var(--coffee-warm));color:var(--cream);padding:16px 24px;box-shadow:0 6px 20px rgba(93,64,55,0.12);z-index:100;text-align:center}
    .navbar h1{position:relative;font-size:22px;font-weight:700;letter-spacing:1.2px;margin:0;display:flex;align-items:center;justify-content:center;gap:10px}
  .auth-container{background:linear-gradient(180deg,var(--cream),#f9f3ee);margin-top:90px;padding:36px;border-radius:18px;box-shadow:0 14px 40px rgba(93,64,55,0.12);text-align:center;max-width:440px;width:92%;border:1px solid #e8dcd7}
  .auth-container h2{color:var(--coffee-dark);font-size:28px;margin-bottom:8px;font-weight:700}
  .auth-container > p{color:#7d6d63;font-size:14px;margin-bottom:18px}
  .pin-inputs{display:flex;justify-content:center;gap:12px;margin:22px 0}
  .pin-inputs input{width:54px;height:64px;text-align:center;font-size:24px;font-weight:700;border:1.5px solid #e6d9d4;border-radius:12px;background:#fff8f3;color:var(--coffee-dark);outline:none;letter-spacing:6px;transition:all .18s}
  .pin-inputs input:focus{border-color:var(--coffee-warm);box-shadow:0 6px 20px rgba(169,130,116,0.10);background:#fff}
  .login-btn{margin-top:20px;width:100%;padding:14px 20px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--coffee-warm),var(--coffee-medium));color:var(--cream);font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:transform .12s,box-shadow .12s}
  .login-btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(93,64,55,0.14)}
  #login-message{margin-top:14px;font-size:14px;font-weight:600;min-height:22px;padding:8px 12px;border-radius:8px}
  #login-message.success{color:#2e7d32;background:#e8f5e9;border-left:4px solid #2e7d32}
  #login-message.error{color:#b71c1c;background:#fff0f0;border-left:4px solid #b71c1c}
  .input{width:100%;padding:10px;border-radius:8px;border:1px solid #eee}
  #accountLogin{margin-top:8px;text-align:left}
  a#toggleAccount{color:var(--coffee-medium);text-decoration:none;font-weight:700}
  @media(max-width:768px){.auth-container{padding:30px 22px;margin-top:76px}.auth-container h2{font-size:24px}.navbar h1{font-size:20px}body{background-size:80% auto}}
  @media(max-width:480px){body{padding:12px;background-size:100% auto}.navbar{padding:12px 16px}.navbar h1{font-size:18px}.auth-container{padding:20px;margin-top:68px;border-radius:14px}.pin-inputs input{width:46px;height:54px;font-size:20px}}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>

<div class="navbar"><h1>CafeKantina</h1></div>

<div class="auth-container">
  <h2><i class="fas fa-lock"></i> Admin Access</h2>
  <p>Enter your PIN to access the admin dashboard</p>
  <div class="pin-inputs">
    <input type="password" maxlength="1" id="pin1" oninput="moveNext(1)">
    <input type="password" maxlength="1" id="pin2" oninput="moveNext(2)">
    <input type="password" maxlength="1" id="pin3" oninput="moveNext(3)">
    <input type="password" maxlength="1" id="pin4" oninput="moveNext(4)">
  </div>
  <div style="margin-top:6px; font-size:13px; color:#7d6d63">or</div>
  <div id="accountLogin" style="margin-top:12px; display:none; text-align:left">
    <label style="display:block; font-size:13px; color:#7d6d63; margin-bottom:6px">Staff/Admin Email</label>
    <input id="adminEmail" type="email" class="input" placeholder="email@example.com" style="margin-bottom:8px">
    <label style="display:block; font-size:13px; color:#7d6d63; margin:6px 0 6px">Password</label>
    <input id="adminPassword" type="password" class="input" placeholder="Password">
  </div>
  <div style="margin-top:10px; text-align:center">
    <a href="#" id="toggleAccount" style="color:#6d4c41; text-decoration:none; font-weight:600">Sign in with account</a>
  </div>
  <button class="login-btn" id="loginBtn" onclick="checkPIN()"><i class="fas fa-sign-in-alt"></i> <span id="loginBtnText">Confirm PIN</span></button>
  <p id="login-message" aria-live="polite" role="status"></p>
</div>

<script>
// client will call server to perform admin login and set session
const correctPIN = "1234"; // kept for instant-check UX fallback (optional)
// UI elements
const toggleAccount = document.getElementById('toggleAccount');
const accountLogin = document.getElementById('accountLogin');
const loginBtn = document.getElementById('loginBtn');
const loginBtnText = document.getElementById('loginBtnText');
const loginMessage = document.getElementById('login-message');

// toggle between PIN and account login
toggleAccount.addEventListener('click', function(e){
  e.preventDefault();
  const showing = accountLogin.style.display !== 'none';
  if(showing){
    accountLogin.style.display = 'none';
    toggleAccount.textContent = 'Sign in with account';
    loginBtnText.textContent = 'Confirm PIN';
    document.getElementById('pin1').focus();
  } else {
    accountLogin.style.display = 'block';
    toggleAccount.textContent = 'Use PIN instead';
    loginBtnText.textContent = 'Sign In';
    document.getElementById('adminEmail').focus();
  }
    // clear any previous message when switching
    loginMessage.className = '';
    loginMessage.textContent = '';
});
function moveNext(current) {
  const currentInput = document.getElementById(`pin${current}`);
  if (currentInput.value.length === 1 && current < 4) {
    document.getElementById(`pin${current + 1}`).focus();
  }
}

function checkPIN() {
  const pin = 
    document.getElementById("pin1").value +
    document.getElementById("pin2").value +
    document.getElementById("pin3").value +
    document.getElementById("pin4").value;
  
  const message = loginMessage;
  // determine whether to use account login
  const useAccount = accountLogin.style.display !== 'none' && (document.getElementById('adminEmail').value.trim() !== '');
  const payload = useAccount ? { email: document.getElementById('adminEmail').value.trim(), password: document.getElementById('adminPassword').value } : { pin };

  // basic validation
  if(useAccount){
    if(!payload.email || !payload.password){
      message.className = 'error'; message.textContent = 'Please enter email and password.'; return;
    }
  } else {
    if(!pin || pin.length < 4){ message.className = 'error'; message.textContent = 'Please enter 4-digit PIN.'; return; }
  }

  // show loading state
  loginBtn.disabled = true; loginBtn.style.opacity = '0.7';
  message.className = '';
  message.textContent = '';

  fetch('api/admin_login.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  }).then(res => res.json()).then(res => {
    loginBtn.disabled = false; loginBtn.style.opacity = '';
    if(res.success){
      message.classList.remove('error'); message.classList.add('success');
      message.textContent = '✅ Access Granted. Redirecting...';
      // clear inputs
      document.querySelectorAll('.pin-inputs input').forEach(i=>i.value='');
      document.getElementById('adminPassword').value = '';
      setTimeout(()=>{ window.location.href = 'admin_dashboard.php'; }, 600);
    } else {
      message.classList.remove('success'); message.classList.add('error');
      // prefer server-sent message when available
      message.textContent = '❌ ' + (res.message || 'Login failed.');
      if(useAccount){ document.getElementById('adminPassword').value = ''; document.getElementById('adminPassword').focus(); }
      else { document.querySelectorAll('.pin-inputs input').forEach((input) => (input.value = '')); document.getElementById('pin1').focus(); }
    }
  }).catch(err => {
    loginBtn.disabled = false; loginBtn.style.opacity = '';
    message.classList.remove('success'); message.classList.add('error');
    message.textContent = '❌ Server error. Try again.';
    console.error(err);
  });
}

// submit on Enter for PIN inputs and admin password
document.querySelectorAll('.pin-inputs input').forEach(inp => inp.addEventListener('keydown', (e)=>{ if(e.key === 'Enter'){ e.preventDefault(); checkPIN(); } }));
const adminPasswordEl = document.getElementById('adminPassword');
if(adminPasswordEl) adminPasswordEl.addEventListener('keydown', (e)=>{ if(e.key === 'Enter'){ e.preventDefault(); checkPIN(); } });
</script>

</body>
</html>

