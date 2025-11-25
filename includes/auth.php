<?php
// Authentication helpers for CafeTrack
// Uses users table for all users (admin, staff, manager)
session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? ''
    ];
}

/**
 * Login user using users table
 * Accepts either username or email as identifier
 * Returns ['success'=>true, 'role'=>'admin'|'staff'|'manager'] or ['success'=>false, 'message'=>...]
 */
function login_user($identifier, $password) {
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['success' => false, 'message' => 'Username/Email and password are required.'];
    }

    // DB connection using PDO
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=cafetrack', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Search users table by username OR email
    $stmt = $pdo->prepare("SELECT id, username, email, password, role, first_name, last_name, is_active FROM users WHERE (username = :identifier OR email = :identifier) AND is_active = TRUE LIMIT 1");
    $stmt->execute([':identifier' => $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    // Verify password
    $verified = false;
    if (password_verify($password, $user['password'])) {
        $verified = true;
    } elseif ($password === $user['password']) {
        // Legacy plain-text fallback
        $verified = true;
    }

    if (!$verified) {
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    // Success: set session and update last_login
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'] ?? 'staff';
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name'] = $user['last_name'] ?? '';

    // Update last_login timestamp
    try {
        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);
    } catch (Exception $e) {
        error_log('Failed to update last_login for user ' . $user['id']);
    }

    return ['success' => true, 'role' => $_SESSION['role']];
}

/**
 * Register a new user and send welcome email
 * Accepts username, email, password, first_name, last_name, role
 */
function register_user($username, $email, $password, $first_name = '', $last_name = '', $role = 'staff') {
    $username = trim($username);
    $email = trim($email);
    $password = trim($password);
    $first_name = trim($first_name);
    $last_name = trim($last_name);

    if ($username === '' || $password === '') {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }

    try {
        $pdo = new PDO('mysql:host=localhost;dbname=cafetrack', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check if username already exists
    $chkUsername = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $chkUsername->execute([':username' => $username]);
    if ($chkUsername->fetch()) {
        return ['success' => false, 'message' => 'Username is already registered.'];
    }

    // Check if email already exists (if email provided)
    if ($email !== '') {
        $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $chkEmail->execute([':email' => $email]);
        if ($chkEmail->fetch()) {
            return ['success' => false, 'message' => 'Email is already registered.'];
        }
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, is_active) VALUES (:username, :email, :password, :role, :first_name, :last_name, TRUE)");
    try {
        $ins->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hash,
            ':role' => $role,
            ':first_name' => $first_name,
            ':last_name' => $last_name
        ]);
        
        // Email sending disabled (mailer removed)
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

?>
