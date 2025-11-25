<?php
// Simple admin PIN login API
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$pin = isset($input['pin']) ? $input['pin'] : null;

// Hardcoded PIN for admin access
$correctPIN = '1234';

if ($pin !== null) {
    if ($pin === $correctPIN) {
            // Set session for admin (resolve numeric user id from users table)
            session_start();
            // Find an admin user id in the users table; if none exists, create one
            $mysqli = new mysqli('localhost','root','','cafetrack');
            if ($mysqli->connect_errno) {
                echo json_encode(['success' => true]);
                exit;
            }
            $admin_id = null;
            $res = $mysqli->query("SELECT id, username FROM users WHERE role='admin' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $admin_id = (int)$row['id'];
                $admin_username = $row['username'];
            }
            // If no admin user, create one with password 'admin' (hashed) — only as a convenience fallback
            if (!$admin_id) {
                $hash = password_hash('admin', PASSWORD_DEFAULT);
                $ins = $mysqli->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, '', ?, 'admin', TRUE)");
                $admin_username = 'admin';
                $ins->bind_param('ss', $admin_username, $hash);
                $ins->execute();
                $admin_id = $ins->insert_id;
                $ins->close();
            }
            if ($admin_id) {
                $_SESSION['user_id'] = (int)$admin_id;
                $_SESSION['username'] = $admin_username;
                $_SESSION['role'] = 'admin';
            }
            $mysqli->close();
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
        exit;
    }
}

// If using account login, you can add logic here for email/password
if (isset($input['email']) && isset($input['password'])) {
    // TODO: Implement account login if needed
    echo json_encode(['success' => false, 'message' => 'Account login not implemented.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
