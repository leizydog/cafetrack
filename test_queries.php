<?php
$conn = new mysqli('localhost', 'root', '', 'cafetrack');
$conn->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Test the categories query
echo "Testing categories query...\n";
try {
  $res = $conn->query("SELECT DISTINCT c.name AS category FROM categories c JOIN products p ON c.id = p.category_id ORDER BY c.name");
  if ($res) {
    while($row = $res->fetch_assoc()) {
      echo "Category: " . $row['category'] . "\n";
    }
    echo "Categories query SUCCESS\n";
  }
} catch (Exception $e) {
  echo "Categories query FAILED: " . $e->getMessage() . "\n";
}

// Test the products query
echo "\nTesting products query...\n";
try {
  $stmt = $conn->prepare("SELECT p.id, p.name, p.price, c.name AS category, COALESCE(p.recipe,'[]') AS recipe FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.name, p.name");
  $stmt->execute();
  $res = $stmt->get_result();
  $count = 0;
  while($row = $res->fetch_assoc()) {
    $count++;
  }
  echo "Products query SUCCESS - found $count products\n";
  $stmt->close();
} catch (Exception $e) {
  echo "Products query FAILED: " . $e->getMessage() . "\n";
}

$conn->close();
?>
