<?php
$conn = new mysqli('localhost', 'root', '', 'cafetrack');
$conn->set_charset('utf8mb4');
$result = $conn->query('DESCRIBE products');
while($row = $result->fetch_assoc()) {
  echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
$result->free();
$conn->close();
?>
