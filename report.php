<?php
// report.php (FULL REDESIGN — Café Kantina theme)
// Keep your auth and DB includes. This file renders a modern reports page
// with charts and tables using Chart.js and server-side PDO queries.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$username = $user['username'] ?? 'Staff';

// --- Utility: detect date / qty columns similarly to original file (keeps compatibility) ---
$possibleDateCols = ['order_date','created_at','created','date','timestamp'];
$dateCol = null;
foreach ($possibleDateCols as $c) {
    $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE '" . str_replace("'","\\'", $c) . "'");
    if ($chk && $chk->rowCount() > 0) { $dateCol = $c; break; }
}
if (!$dateCol) {
    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        $type = strtolower($col['Type'] ?? '');
        if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false) { $dateCol = $col['Field']; break; }
    }
}
if (!$dateCol) { $dateCol = 'created_at'; }

$possibleQtyCols = ['quantity','qty','amount'];
$qtyCol = null;
foreach ($possibleQtyCols as $c) {
    $chk = $pdo->query("SHOW COLUMNS FROM order_items LIKE '" . str_replace("'","\\'", $c) . "'");
    if ($chk && $chk->rowCount() > 0) { $qtyCol = $c; break; }
}
if (!$qtyCol) { $qtyCol = 'quantity'; }

// --- Parameters ---
$view = $_GET['view'] ?? 'daily';
$self = basename($_SERVER['PHP_SELF']);
$dateColEsc = str_replace('`','', $dateCol);

// --- Sales: last 30 days grouped by day ---
$salesStmt = $pdo->prepare("SELECT DATE(`$dateColEsc`) as d, SUM(total_amount) as total, COUNT(*) as orders FROM orders WHERE `$dateColEsc` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(`$dateColEsc`) ORDER BY DATE(`$dateColEsc`)");
$salesStmt->execute();
$salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Monthly sales (last 12 months) ---
$monthlyStmt = $pdo->prepare("SELECT YEAR(`$dateColEsc`) as y, MONTH(`$dateColEsc`) as m, SUM(total_amount) as total FROM orders WHERE `$dateColEsc` >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(`$dateColEsc`), MONTH(`$dateColEsc`) ORDER BY YEAR(`$dateColEsc`), MONTH(`$dateColEsc`)");
$monthlyStmt->execute();
$monthlyRows = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Recent orders (latest 10) ---
$recentStmt = $pdo->prepare("SELECT o.id, o.total_amount AS total, o.`$dateColEsc` as created_at, (SELECT SUM(`$qtyCol`) FROM order_items WHERE order_id=o.id) as items_count FROM orders o ORDER BY o.`$dateColEsc` DESC LIMIT 10");
$recentStmt->execute();
$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Inventory (products) ---
$invStmt = $pdo->prepare('SELECT p.id, p.name, c.name AS category, p.price, i.available_in_hand AS stock FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN inventory i ON i.name = p.name AND i.type = "product" ORDER BY p.name');
$invStmt->execute();
$inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Best selling item overall ---
$bestStmt = $pdo->prepare('SELECT oi.product_id, p.name, SUM(`' . $qtyCol . '`) as sold FROM order_items oi JOIN products p ON p.id = oi.product_id GROUP BY oi.product_id, p.name ORDER BY sold DESC LIMIT 1');
$bestStmt->execute();
$best = $bestStmt->fetch(PDO::FETCH_ASSOC);

// Forecast removed per user request

// --- Summary cards ---
$totalTodayStmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as total FROM orders WHERE DATE(`$dateColEsc`)=CURDATE()");
$totalTodayStmt->execute(); $totalToday = $totalTodayStmt->fetchColumn();
$totalOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders'); $totalOrdersStmt->execute(); $totalOrders = $totalOrdersStmt->fetchColumn();
$avgOrderStmt = $pdo->prepare('SELECT IFNULL(AVG(total_amount),0) as avg_order FROM orders'); $avgOrderStmt->execute(); $avgOrder = $avgOrderStmt->fetchColumn();

// --- JSON for charts ---
$dailyLabels = []; $dailyData = [];
foreach($salesRows as $row){ $dailyLabels[] = $row['d']; $dailyData[] = (float)$row['total']; }
$monthlyLabels = []; $monthlyData = [];
foreach($monthlyRows as $r){ $monthlyLabels[] = $r['y'].'-'.str_pad($r['m'],2,'0',STR_PAD_LEFT); $monthlyData[] = (float)$r['total']; }

$inventoryJson = json_encode($inventory);
$dailyLabelsJson = json_encode($dailyLabels);
$dailyDataJson = json_encode($dailyData);
$monthlyLabelsJson = json_encode($monthlyLabels);
$monthlyDataJson = json_encode($monthlyData);
$recentOrdersJson = json_encode($recentOrders);
$bestJson = json_encode($best ?: []);
// Forecast removed per request; no forecast JSON emitted

// Local asset path (user uploaded). If you move the file to public folder, update this path accordingly.
$bgImage = 'sandbox:/mnt/data/e339f013-03ef-4c19-9c30-5a98ad21fc84.png';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>CafeKantina — Reports</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --coffee-dark:#3e2f2a; --coffee-mid:#6d4c41; --coffee-warm:#a98274; --cream:#fff8f3; --muted:#f3e9e2; --accent:#c88f62;
  --card-radius:14px;
}
*{box-sizing:border-box}
body{
  margin:0; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Arial;
  background:
    linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,250,248,0.96)),
    url('<?php echo htmlspecialchars($bgImage); ?>') center/1100px repeat;
  color:var(--coffee-dark);
  -webkit-font-smoothing:antialiased;
  min-height:100vh;
}

/* NAV */
.header {
  background: linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));
  color: #fff8f3;
  padding: 16px 22px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  box-shadow:0 6px 24px rgba(62,47,42,0.12);
  position:sticky; top:0; z-index:1200;
}
.brand { display:flex; align-items:center; gap:12px; cursor:pointer }
.brand .logo { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; background: rgba(255,255,255,0.06); }
.header .controls { display:flex; gap:12px; align-items:center; }

/* LAYOUT */
.wrapper { max-width:1200px; margin:28px auto 60px; padding:18px; display:grid; grid-template-columns: 240px 1fr; gap:20px; }
@media (max-width:1000px) { .wrapper{ grid-template-columns: 1fr; } }

/* SIDEBAR */
.sidebar {
  background: linear-gradient(180deg,var(--muted), #fff8f3);
  border-radius:var(--card-radius);
  padding:14px;
  border:1px solid rgba(0,0,0,0.04);
  box-shadow:0 8px 28px rgba(62,47,42,0.06);
  height:fit-content;
}
.nav-list { list-style:none; padding:6px; margin:0; display:flex; flex-direction:column; gap:8px; }
.nav-list a { display:block; text-decoration:none; color:var(--coffee-dark); padding:12px 10px; border-radius:10px; font-weight:600; }
.nav-list a.active, .nav-list a:hover { background: linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm)); color: #fff8f3; transform:translateX(4px); box-shadow:0 8px 20px rgba(62,47,42,0.08); }

/* MAIN AREA */
.content { display:flex; flex-direction:column; gap:18px; }

.top-row { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
.title { font-size:22px; font-weight:700; color:var(--coffee-dark); }
.subtitle { color:#6b5349; font-size:14px; margin-top:4px; }

/* SUMMARY CARDS */
.summary { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
.card {
  background: linear-gradient(180deg,#fff,#fff8f3);
  padding:18px; border-radius:12px; border:1px solid rgba(0,0,0,0.04);
  box-shadow:0 8px 30px rgba(62,47,42,0.06);
}
.card .num { font-size:20px; font-weight:800; color:var(--coffee-dark); }
.card .label { color:#7e5c4a; margin-top:6px; }

/* CHART AREA */
.panel {
  padding:18px; border-radius:12px; background:linear-gradient(180deg,#fff8f3,#fff); border:1px solid rgba(0,0,0,0.04);
  box-shadow:0 8px 28px rgba(62,47,42,0.06);
}

/* table */
.table { width:100%; border-collapse:collapse; margin-top:10px; }
.table th, .table td { padding:12px 10px; border-bottom:1px solid rgba(0,0,0,0.04); text-align:left; font-size:14px; }
.table th { background: linear-gradient(135deg,var(--coffee-warm),var(--coffee-mid)); color:#fff8f3; font-weight:700; }
.small { color:#7e5c4a; font-size:13px; }

/* forecast chart */
@media (max-width:900px) {
  .top-row { flex-direction:column; }
}

/* subtle entrance animations */
.card, .panel { transform: translateY(6px); opacity:0; animation: enter .45s forwards ease; }
.card:nth-child(1){ animation-delay: 0.05s; } .card:nth-child(2){ animation-delay: 0.12s; } .card:nth-child(3){ animation-delay: 0.18s; }
@keyframes enter { to { transform:none; opacity:1; } }
</style>
</head>
<body>

<header class="header">
  <div class="brand" onclick="location.href='dashboard.php'">
    <div class="logo"><i class="fas fa-mug-hot"></i></div>
    <div style="line-height:1">
      <div style="font-weight:800;color:#fff8f3">CafeKantina</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.86)">Reports & Forecast</div>
    </div>
  </div>
  <div class="controls">
    <div style="color:rgba(255,255,255,0.95);font-weight:600">Signed in as <?php echo htmlspecialchars($username); ?></div>
  </div>
</header>

<div class="wrapper">
  <aside class="sidebar" role="navigation">
    <nav>
      <ul class="nav-list">
        <li><a href="report.php" class="<?php echo ($view==='daily') ? 'active' : ''; ?>"><i class="fas fa-sun" style="margin-right:8px"></i> Daily</a></li>
        <li><a href="report.php?view=monthly" class="<?php echo ($view==='monthly') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt" style="margin-right:8px"></i> Monthly</a></li>
        <li><a href="report.php?view=weekly" class="<?php echo ($view==='weekly') ? 'active' : ''; ?>"><i class="fas fa-calendar-week" style="margin-right:8px"></i> Weekly</a></li>
        <li><a href="inventory.php"><i class="fas fa-box-open" style="margin-right:8px"></i> Inventory</a></li>
        <li><a href="forecasting.php"><i class="fas fa-chart-line" style="margin-right:8px"></i> Forecast</a></li>
      </ul>
    </nav>
  </aside>

  <section class="content" role="main">
    <div class="top-row">
      <div>
        <div class="title">
          <?php if($view==='monthly'): ?>Monthly Sales Report<?php elseif($view==='weekly'): ?>Weekly Sales Report<?php else: ?>Daily Sales Report<?php endif; ?>
        </div>
        <div class="subtitle small"><?php echo ($view==='daily') ? date('F j, Y') : ($view==='monthly' ? 'Last 12 months' : 'Last 7 days'); ?></div>
      </div>

      <div style="display:flex;gap:12px;align-items:center">
        <div class="card" style="min-width:140px;text-align:right">
          <div class="num">₱<?php echo number_format($totalToday,2); ?></div>
          <div class="label small">Sales Today</div>
        </div>
        <div class="card" style="min-width:140px;text-align:right">
          <div class="num"><?php echo number_format($totalOrders); ?></div>
          <div class="label small">Total Orders</div>
        </div>
        <div class="card" style="min-width:140px;text-align:right">
          <div class="num">₱<?php echo number_format($avgOrder,2); ?></div>
          <div class="label small">Avg Order</div>
        </div>
      </div>
    </div>

    <div class="panel">
      <canvas id="mainChart" style="width:100%;height:320px"></canvas>
    </div>

    <div class="panel">
      <h3 style="margin:0 0 10px 0">Recent Orders</h3>
      <div class="small" style="margin-bottom:10px">Latest 10 orders</div>
      <table class="table" aria-live="polite">
        <thead><tr><th>Date</th><th>Order #</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($recentOrders as $ro): ?>
          <tr>
            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($ro['created_at']))); ?></td>
            <td>#<?php echo htmlspecialchars($ro['id']); ?></td>
            <td><?php echo (int)$ro['items_count']; ?></td>
            <td>₱<?php echo number_format($ro['total'],2); ?></td>
            <td><span style="color:var(--coffee-mid);font-weight:700">Completed</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:grid;grid-template-columns:1fr 420px;gap:18px">
      <div class="panel">
        <h3 style="margin:0 0 10px 0">Inventory Snapshot</h3>
        <table class="table">
          <thead><tr><th>Item</th><th>Stock</th><th>Price</th><th>Category</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($inventory as $it):
              $status = isset($it['stock']) ? (($it['stock'] == 0) ? '<span style="color:#c0392b">Out</span>' : (($it['stock'] < 5) ? '<span style="color:#b06b1a">Low</span>' : '<span style="color:#2f7a2f">OK</span>')) : '<span style="color:#7e5c4a">N/A</span>';
          ?>
            <tr>
              <td><?php echo htmlspecialchars($it['name']); ?></td>
              <td><?php echo isset($it['stock']) ? (int)$it['stock'] : 'N/A'; ?></td>
              <td>₱<?php echo number_format($it['price'],2); ?></td>
              <td><?php echo htmlspecialchars($it['category']); ?></td>
              <td><?php echo $status; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

        <div class="panel">
          <h3 style="margin:0 0 10px 0">Best-selling</h3>
          <div class="small" style="margin-bottom:12px">Top selling item</div>
          <div style="margin-top:12px">
            <div class="small">Best-selling</div>
            <div style="font-weight:800;font-size:16px"><?php echo htmlspecialchars($best['name'] ?? '—'); ?> <span style="color:#7e5c4a;font-weight:600"> (<?php echo intval($best['sold'] ?? 0); ?> sold)</span></div>
          </div>
        </div>
    </div>

  </section>
</div>

<script>
// Data emitted by server
const dailyLabels = <?php echo $dailyLabelsJson; ?>;
const dailyData = <?php echo $dailyDataJson; ?>;
const monthlyLabels = <?php echo $monthlyLabelsJson; ?>;
const monthlyData = <?php echo $monthlyDataJson; ?>;
const view = '<?php echo $view; ?>';

// Chart colors (coffee palette)
const accent = '#6f4e37';
const accentFill = 'rgba(111,78,55,0.12)';

// Helper to create chart
function createLine(ctx, labels, data){
  return new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Sales', data: data, borderColor: accent, backgroundColor: accentFill, fill: true, tension: 0.25, pointRadius: 3 }]},
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { ticks: { beginAtZero: true } }
      },
      maintainAspectRatio: false
    }
  });
}
function createBar(ctx, labels, data){
  return new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ label:'Sales', data: data, backgroundColor: accent }]},
    options: {
      plugins: { legend: { display:false } },
      scales: { x:{ grid:{display:false} }, y:{ beginAtZero:true } },
      maintainAspectRatio: false
    }
  });
}

// Render main chart depending on view
window.addEventListener('load', ()=>{
  const mainCtx = document.getElementById('mainChart').getContext('2d');
  if (view === 'monthly') {
    createLine(mainCtx, monthlyLabels, monthlyData);
  } else if (view === 'weekly') {
    const last7 = dailyLabels.slice(-7); const data7 = dailyData.slice(-7);
    createBar(mainCtx, last7, data7);
  } else {
    createBar(mainCtx, dailyLabels, dailyData);
  }

  // forecast removed — no forecast chart to render
});
</script>

</body>
</html>
