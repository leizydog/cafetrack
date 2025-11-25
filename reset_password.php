<?php
require_once __DIR__ . '/includes/db.php';
// token in GET
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password - CafeKantina</title>
  <style>
    /* Coffee theme — reset password */
    :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3 }
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Poppins','Segoe UI',sans-serif}
    body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(255,253,249,0.6));min-height:100vh;padding:30px;color:var(--coffee-dark);display:flex;align-items:center;justify-content:center}
    .box{max-width:480px;margin:20px auto;background:linear-gradient(145deg,var(--cream),#f3e9e2);padding:24px;border-radius:12px;box-shadow:0 8px 24px rgba(93,64,55,0.08);border:1px solid #e8dcd7}
    input{width:100%;padding:12px;margin:8px 0;border-radius:8px;border:1px solid #e7e0db;background:#faf7f4}
    input:focus{outline:none;border-color:var(--coffee-warm);box-shadow:0 6px 20px rgba(169,130,116,0.06)}
    button{background:linear-gradient(135deg,var(--coffee-medium),var(--coffee-dark));color:var(--cream);padding:12px 16px;border:none;border-radius:8px;cursor:pointer;width:100%;font-size:16px;transition:transform .18s}
    button:hover{transform:translateY(-2px)}
    button:disabled{opacity:0.6;cursor:not-allowed}
    .msg{margin:12px 0;padding:12px;border-radius:8px;display:none}
    .msg.error{display:block;background:#fee5e5;color:#b71c1c;border-left:4px solid #b71c1c}
    .msg.success{display:block;background:#e8f5e9;color:#2e7d32;border-left:4px solid #2e7d32}
    .password-requirements{font-size:14px;color:#666;margin:16px 0;padding:12px;background:#fff8f3;border-radius:8px}
    .requirement{margin:4px 0;display:flex;align-items:center;gap:8px}
    .requirement.met{color:#2e7d32}
    .requirement.met::before{content:'✓';color:#2e7d32}
    .requirement:not(.met)::before{content:'○';color:#666}
    .loader{display:none;width:20px;height:20px;border:2px solid #f3f3f3;border-top:2px solid var(--coffee-medium);border-radius:50%;animation:spin 1s linear infinite;margin:10px auto}
    @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
  </style>
    <link rel="stylesheet" href="assets/css/theme.css">
  </head>
  <body>
  <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="box">
    <h2>Set a new password</h2>
    <div id="msg" class="msg"></div>
    <form id="resetForm" onsubmit="return false;">
      <input type="password" id="pw1" placeholder="New password" required>
      <div class="password-requirements" id="requirements">
        <div class="requirement" data-req="length">At least 8 characters long</div>
        <div class="requirement" data-req="uppercase">Contains uppercase letter</div>
        <div class="requirement" data-req="lowercase">Contains lowercase letter</div>
        <div class="requirement" data-req="number">Contains number</div>
        <div class="requirement" data-req="special">Contains special character</div>
      </div>
      <input type="password" id="pw2" placeholder="Confirm password" required>
      <button id="saveBtn" type="submit">
        <span>Reset password</span>
        <div class="loader" id="loader"></div>
      </button>
      <p style="margin-top:16px;text-align:center">
        <a href="login.php" style="color:#5d4037;text-decoration:none">← Back to login</a>
      </p>
    </form>
  </div>

  <script>
  const token = <?php echo json_encode($token); ?>;
  const form = document.getElementById('resetForm');
  const pw1Input = document.getElementById('pw1');
  const pw2Input = document.getElementById('pw2');
  const saveBtn = document.getElementById('saveBtn');
  const msg = document.getElementById('msg');
  const loader = document.getElementById('loader');
  const requirements = document.getElementById('requirements');

  function showMessage(message, type) {
    msg.className = 'msg ' + type;
    msg.textContent = message;
  }

  function setLoading(isLoading) {
    saveBtn.disabled = isLoading;
    loader.style.display = isLoading ? 'block' : 'none';
    saveBtn.querySelector('span').style.display = isLoading ? 'none' : 'inline';
  }

  function validatePassword(password) {
    const rules = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password),
      special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };

    requirements.querySelectorAll('.requirement').forEach(req => {
      const rule = req.dataset.req;
      req.className = 'requirement' + (rules[rule] ? ' met' : '');
    });

    return Object.values(rules).every(Boolean);
  }

  pw1Input.addEventListener('input', function() {
    validatePassword(this.value);
  });

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const password = pw1Input.value;
    const confirm = pw2Input.value;

    showMessage('', '');

    if (!validatePassword(password)) {
      showMessage('Please meet all password requirements', 'error');
      return;
    }

    if (password !== confirm) {
      showMessage('Passwords do not match', 'error');
      pw2Input.focus();
      return;
    }

    setLoading(true);

    try {
      const response = await fetch('api/perform_password_reset.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: token, password: password})
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const data = await response.json();

      if (data.success) {
        showMessage('Password successfully updated! Redirecting to login...', 'success');
        setTimeout(() => location.href = 'login.php', 2000);
      } else {
        showMessage(data.message || 'Error updating password', 'error');
      }
    } catch(e) {
      showMessage('Failed to update password. Please try again.', 'error');
    } finally {
      setLoading(false);
    }
  });
  </script>
</body>
</html>
