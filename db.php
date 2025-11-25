<?php
// Database connection for MySQLi (update credentials as needed)
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'cafetrack';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>
