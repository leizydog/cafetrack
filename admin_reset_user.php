<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
if(($user['role'] ?? '') !== 'admin'){
    header('Location: dashboard.php'); exit;
}
require_once __DIR__ . '/includes/db.php';

$uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $uid = (int)($_POST['user_id'] ?? 0);
    $new = $_POST['new_pw'] ?? '';
    $confirm = $_POST['confirm_pw'] ?? '';

    if(!$uid){ $message = 'Invalid user'; }
    elseif(!$new || !$confirm){ $message = 'All fields required'; }
    elseif($new !== $confirm){ $message = 'Passwords do not match'; }
    else {
        // server-side strength checks (same as staff_change_password)
        $pwErrors = [];
        if(strlen($new) < 10) $pwErrors[] = 'at least 10 characters long';
        if(!preg_match('/[A-Z]/', $new)) $pwErrors[] = 'one uppercase letter';
        if(!preg_match('/[a-z]/', $new)) $pwErrors[] = 'one lowercase letter';
        if(!preg_match('/[0-9]/', $new)) $pwErrors[] = 'one number';
        if(!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\"\\|,.<>\/\?]/', $new)) $pwErrors[] = 'one special character';

        if($pwErrors){
            $message = 'New password must contain: ' . implode(', ', $pwErrors) . '.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
            $u->execute([':pw'=>$hash, ':id'=>$uid]);

            // optionally update role if provided and valid
            $roleUpdated = false;
            if(isset($_POST['role']) && $_POST['role'] !== ''){
              $role = $_POST['role'];
              $allowed = ['admin','staff','member'];
              if(in_array($role, $allowed, true)){
                $r = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $r->execute([':role'=>$role, ':id'=>$uid]);
                $roleUpdated = true;
              }
            }

            // log the password change
            try{
                $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action ENUM('login','logout','password_change') NOT NULL,
                    ip VARCHAR(45) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX(user_id), INDEX(action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ins = $pdo->prepare('INSERT INTO login_logs (user_id, action, ip) VALUES (:uid, "password_change", :ip)');
                $ins->execute([':uid'=>$uid, ':ip'=>$ip]);
            } catch(Exception $e){ error_log('admin pw change log failed: '.$e->getMessage()); }

            $message = 'Password updated successfully for user ID ' . $uid . '. Provide the temporary password to the user.';
            if(!empty($roleUpdated)){
              $message .= ' Role updated.';
            }
        }
    }
}

// fetch user info for display
$userRow = null;
if($uid){
  $s = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = :id LIMIT 1');
    $s->execute([':id'=>$uid]);
    $userRow = $s->fetch();
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Reset User Password - CafeTrack</title>
  <style>
    /* Coffee theme — admin reset user */
    :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Poppins','Segoe UI',sans-serif}
    body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(255,253,249,0.6));min-height:100vh;padding:30px;color:var(--coffee-dark)}
    .box{max-width:560px;margin:40px auto;background:linear-gradient(145deg,var(--cream),#f3e9e2);padding:24px;border-radius:12px;box-shadow:0 8px 24px rgba(93,64,55,0.08);border:1px solid #e8dcd7}
    h2{margin:0 0 12px;color:var(--coffee-medium)}
    input, select{width:100%;padding:12px;margin:8px 0;border-radius:8px;border:1px solid #e7e0db;background:#faf7f4}
    input:focus, select:focus{outline:none;border-color:var(--coffee-warm);box-shadow:0 6px 20px rgba(169,130,116,0.06)}
    button{background:linear-gradient(135deg,var(--coffee-medium),var(--coffee-dark));color:var(--cream);padding:10px 14px;border:none;border-radius:8px;cursor:pointer}
    .note{font-size:13px;color:#6d544c}
    a{color:var(--coffee-medium)}
    @media(max-width:600px){body{padding:20px}.box{margin:20px auto;padding:18px}}
  </style>
</head>
<link rel="stylesheet" href="assets/css/theme.css">
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
  <div class="box">
    <h2>Reset password for user</h2>
    <?php if($message): ?><p style="color:<?php echo (strpos($message, 'successfully') !== false) ? 'green' : 'red'; ?>"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

    <?php if(!$userRow): ?>
      <p>Select a user from <a href="admin_staff_logs.php">staff activity</a> or provide a user id in the URL (?user_id=).</p>
    <?php else: ?>
      <p><strong><?php echo htmlspecialchars($userRow['username']); ?></strong> &lt;<?php echo htmlspecialchars($userRow['email']); ?>&gt;</p>
      <form method="POST">
        <input type="hidden" name="user_id" value="<?php echo (int)$userRow['id']; ?>">
        <label>New password</label>
        <input type="password" name="new_pw" placeholder="Enter new temporary password" required>
        <label>Confirm password</label>
        <input type="password" name="confirm_pw" placeholder="Confirm new password" required>

        <label>Role (optional)</label>
        <select name="role">
          <option value="">— keep current —</option>
          <option value="admin" <?php if(!empty($userRow['role']) && $userRow['role']==='admin') echo 'selected'; ?>>Admin</option>
          <option value="staff" <?php if(!empty($userRow['role']) && $userRow['role']==='staff') echo 'selected'; ?>>Staff</option>
          <option value="member" <?php if(!empty($userRow['role']) && $userRow['role']==='member') echo 'selected'; ?>>Member</option>
        </select>

        <p class="note">Password must be at least 10 characters and contain uppercase, lowercase, number and special character.</p>
        <button type="submit">Set password and role</button>
      </form>
    <?php endif; ?>

    <p style="margin-top:12px"><a href="admin_staff_logs.php">← Back to activity logs</a></p>
  </div>
</body>
</html>