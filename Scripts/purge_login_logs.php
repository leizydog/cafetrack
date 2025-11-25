<?php
// Purge login_logs older than X days. Can be run via web by an admin or CLI.
// Usage (web): POST days=90 (default 90)
// Usage (cli): php purge_login_logs.php 90

// If run from web, ensure admin
if(PHP_SAPI !== 'cli'){
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    $user = current_user();
    if(($user['role'] ?? '') !== 'admin'){
        http_response_code(403); echo "Forbidden"; exit;
    }
}

require_once __DIR__ . '/../includes/db.php';

$days = 90;
if(PHP_SAPI === 'cli'){
    global $argv; if(isset($argv[1]) && is_numeric($argv[1])) $days = (int)$argv[1];
} else {
    if(isset($_POST['days']) && is_numeric($_POST['days'])) $days = (int)$_POST['days'];
}

try{
    $stmt = $pdo->prepare('DELETE FROM login_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->rowCount();
    $msg = "Purged {$count} log entries older than {$days} days.";
} catch(Exception $e){
    $msg = 'Failed to purge: ' . $e->getMessage();
}

if(PHP_SAPI === 'cli'){
    echo $msg . "\n";
} else {
    // redirect back with message
    header('Location: /admin_staff_logs.php?purge_result=' . urlencode($msg));
    exit;
}
?>