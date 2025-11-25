<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user(); $username = $user['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CafeKantina - Staff Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    /* === General Reset === */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Poppins", sans-serif; }
    body { background-color: #f8f5f0; color: #333; min-height: 100vh; display: flex; flex-direction: column; }
    .navbar { background-color: #4a2c2a; color: white; display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; box-shadow: 0 3px 8px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 100; }
    .navbar h1 { font-size: 26px; font-weight: bold; letter-spacing: 1px; }
    .nav-links a { color: white; text-decoration: none; font-size: 16px; display: flex; align-items: center; gap: 6px; transition: 0.3s; font-weight: 500; }
    .nav-links a:hover { color: #f3c693; transform: scale(1.05); }
    .dashboard { text-align: center; padding: 50px 20px; flex: 1; background: linear-gradient(to bottom, #f8f5f0, #fdfbf8); }
    .dashboard h2 { color: #4a2c2a; font-size: 30px; font-weight: 600; margin-bottom: 40px; text-transform: uppercase; letter-spacing: 1px; }
    .welcome { font-size: 18px; color: #4a2c2a; margin-bottom: 20px; font-weight: 500; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 50px; }
    .stat-card { background-color: white; padding: 25px 20px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-left: 5px solid #d1a573; transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
    .stat-card i { font-size: 34px; color: #4a2c2a; margin-bottom: 10px; }
    .stat-card h3 { font-size: 16px; color: #555; font-weight: 500; margin-bottom: 6px; }
    .stat-card p { font-size: 22px; font-weight: bold; color: #4a2c2a; }
    .table-section { background-color: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 1000px; margin: 0 auto 50px auto; }
    .table-section h3 { text-align: left; color: #4a2c2a; margin-bottom: 15px; font-size: 20px; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table th, table td { padding: 12px 10px; text-align: center; border-bottom: 1px solid #ddd; font-size: 15px; }
    table th { background-color: #f4e9dc; color: #4a2c2a; font-weight: 600; }
    table tr:hover { background-color: #fdf8f3; }
    @media (max-width: 768px) { .dashboard h2 { font-size: 26px; } .table-section { padding: 15px; } table th, table td { font-size: 13px; padding: 8px; } }
  </style>
  </style>
  <link rel="stylesheet" href="assets/css/theme.css">
  </head>

  <body>
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <script>localStorage.setItem('staffUsername', <?php echo json_encode($username); ?>);</script>

  <!-- Dashboard -->
  <div class="dashboard">
    <h2>Staff Dashboard</h2>
    <p class="welcome">Welcome, <strong><?php echo htmlspecialchars($username ?: 'Staff'); ?></strong> 👋</p>

    <!-- Stats Overview -->
    <div class="stats">
      <div class="stat-card">
        <i class="fas fa-peso-sign"></i>
        <h3>Total Sales</h3>
        <p>₱8,450</p>
      </div>

      <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h3>Orders Completed</h3>
        <p>38</p>
      </div>

      <div class="stat-card">
        <i class="fas fa-clock"></i>
        <h3>Pending Orders</h3>
        <p>4</p>
      </div>

      <div class="stat-card">
        <i class="fas fa-mug-hot"></i>
        <h3>Best Selling Item</h3>
        <p>Cappuccino</p>
      </div>
    </div>

    <!-- Sales History Table -->
    <div class="table-section">
      <h3>Recent Sales Records</h3>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Order ID</th>
            <th>Items Sold</th>
            <th>Total Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Oct 20, 2025</td>
            <td>#1024</td>
            <td>3</td>
            <td>₱320</td>
            <td>Completed</td>
          </tr>
          <tr>
            <td>Oct 20, 2025</td>
            <td>#1025</td>
            <td>2</td>
            <td>₱220</td>
            <td>Completed</td>
          </tr>
          <tr>
            <td>Oct 21, 2025</td>
            <td>#1028</td>
            <td>1</td>
            <td>₱120</td>
            <td>Pending</td>
          </tr>
          <tr>
            <td>Oct 21, 2025</td>
            <td>#1030</td>
            <td>5</td>
            <td>₱600</td>
            <td>Completed</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
