<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// record logout for the current user if possible
if(!empty($_SESSION['user']['id'])){
	$uid = (int)$_SESSION['user']['id'];
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
		$ins = $pdo->prepare('INSERT INTO login_logs (user_id, action, ip) VALUES (:uid, "logout", :ip)');
		$ins->execute([':uid'=>$uid, ':ip'=>$ip]);
	} catch(Exception $e) { error_log('logout log failed: '.$e->getMessage()); }
}

session_unset();
session_destroy();
header('Location: login.php');
exit;
