<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
if(!$user) header('Location: login.php');

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST')
    require_once __DIR__ . '/includes/db.php';
    $current = $_POST['current_pw'] ?? '';
    $new = $_POST['new_pw'] ?? '';
    $confirm = $_POST['confirm_pw'] ?? '';

  if(!$current || !$new || !$confirm){
    $message = 'All fields are required';
  } elseif($new !== $confirm){
    $message = 'New passwords do not match';
  } else {
    // stronger server-side password rules
    $pwErrors = [];
    if(strlen($new) < 10) $pwErrors[] = 'at least 10 characters long';
    if(!preg_match('/[A-Z]/', $new)) $pwErrors[] = 'one uppercase letter';
    if(!preg_match('/[a-z]/', $new)) $pwErrors[] = 'one lowercase letter';
    if(!preg_match('/[0-9]/', $new)) $pwErrors[] = 'one number';
    if(!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\"\\|,.<>\/\?]/', $new)) $pwErrors[] = 'one special character';

    if($pwErrors){
      $message = 'New password must contain: ' . implode(', ', $pwErrors) . '.';
    } else {
        // verify current
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id'=>$user['id']]);
        $row = $stmt->fetch();
        if(!$row || !password_verify($current, $row['password'])){
            $message = 'Current password is incorrect';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
            $u->execute([':pw'=>$hash, ':id'=>$user['id']]);

            // log password change
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
                $ins->execute([':uid'=>$user['id'], ':ip'=>$ip]);
            } catch(Exception $e){ error_log('pw change log failed: '.$e->getMessage()); }

            $message = 'Password updated successfully';
        }
    }
  }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Change Password - CafeKantina</title>
  <style>
    /* Coffee theme — change password */
    :root{ --coffee-dark:#3e2f2a; --coffee-medium:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; }
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Poppins','Segoe UI',sans-serif}
    body{background:linear-gradient(135deg,rgba(243,233,226,0.6),rgba(255,253,249,0.6));min-height:100vh;padding:30px;color:var(--coffee-dark)}
    .box{max-width:480px;margin:40px auto;background:linear-gradient(145deg,var(--cream),#f3e9e2);padding:24px;border-radius:12px;box-shadow:0 8px 24px rgba(93,64,55,0.08);border:1px solid #e8dcd7}
    h2{margin:0 0 12px;color:var(--coffee-medium)}
    input{width:100%;padding:12px;margin:8px 0;border-radius:8px;border:1px solid #e7e0db;background:#faf7f4}
    input:focus{outline:none;border-color:var(--coffee-warm);box-shadow:0 6px 20px rgba(169,130,116,0.06)}
    button{background:linear-gradient(135deg,var(--coffee-medium),var(--coffee-dark));color:var(--cream);padding:10px 14px;border:none;border-radius:8px;cursor:pointer}
    a{color:var(--coffee-medium);text-decoration:none}
    @media(max-width:600px){body{padding:20px}.box{margin:20px auto;padding:18px}}
  </style>
</head>
<link rel="stylesheet" href="assets/css/theme.css">
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
  <div class="box">
    <h2>Change password</h2>
    <?php if($message): ?><p style="color:<?php echo ($message==='Password updated successfully')?'green':'red'; ?>"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <form method="POST">
      <input type="password" name="current_pw" placeholder="Current password" required>
      <input type="password" name="new_pw" placeholder="New password (min 8 chars)" required>
      <input type="password" name="confirm_pw" placeholder="Confirm new password" required>
      <button type="submit">Change password</button>
    </form>
    <p style="margin-top:12px"><a href="staff.php">Back to dashboard</a></p>
  </div>
</body>
</html>