<?php
// Database connection helper (provides both PDO and MySQLi)
// Update credentials below as needed for your environment.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'cafetrack';
$DB_USER = 'root';
$DB_PASS = '';

// Create PDO for pages that expect $pdo
try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // In CLI or debugging, fail loudly; in production you may want to log instead
    die('PDO connection failed: ' . $e->getMessage());
}

// Create MySQLi for pages that use $conn
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('MySQLi connection failed: ' . $conn->connect_error);
}

// Backwards compat: some code expects $db or $mysqli
$db = $conn;
$mysqli = $conn;

// Short helper to get mysqli with exception on error
function mysqli_throw_on_error($mysqli) {
    if ($mysqli->connect_error) {
        throw new Exception('MySQLi connection error: ' . $mysqli->connect_error);
    }
}

?>
