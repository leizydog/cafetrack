<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user(); $username = $user['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CafeKantina - Sales Forecasting</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', 'Segoe UI', sans-serif; }
body {
  background: linear-gradient(135deg,#f3e9e2 0%,#f5f2ef 100%);
  color: #4a2c2a;
}
.navbar {
  background: linear-gradient(90deg, #6d4c41, #a98274);
  color: #fff8f3;
  text-align: center;
  padding: 18px 0;
  font-size: 1.7rem;
  font-weight: bold;
  letter-spacing: 1px;
  box-shadow: 0 3px 10px rgba(93,64,55,0.18);
  text-shadow: 0 1px 0 #f3e9e2;
}
.forecast-container {
  width: 92%;
  max-width: 1100px;
  margin: 44px auto;
  background: linear-gradient(145deg, #fff8f3, #f3e9e2);
  padding: 32px 32px 28px 32px;
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(93,64,55,0.10);
  border: 1.5px solid #e0d6d2;
}
.forecast-header {
  text-align: center;
  margin-bottom: 28px;
}
.forecast-header h2 {
  color: #6d4c41;
  font-size: 2.1rem;
  margin-bottom: 10px;
  text-shadow: 0 1px 0 #f3e9e2;
}
.table-wrapper { overflow-x:auto; margin-top:18px; }
table {
  width: 100%;
  border-collapse: collapse;
  background: #f9f6f2;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(93,64,55,0.04);
  border: 1.5px solid #e0d6d2;
}
th, td {
  padding: 13px 15px;
  text-align: center;
  border-bottom: 1px solid #e0d6d2;
  font-size: 15px;
}
th {
  background: linear-gradient(135deg,#a98274 0%,#6d4c41 100%);
  color: #fff8f3;
  font-weight: bold;
  letter-spacing: 0.2px;
  text-shadow: 0 1px 0 #7e5c4a;
}
.best-seller {
  background-color: #ffe6c6 !important;
  font-weight: bold;
  color: #a98274;
}
.forecast-btn {
  text-align: center;
  margin: 25px 0 10px 0;
}
.forecast-btn button, button#exportCsv {
  background: linear-gradient(90deg,#6d4c41 60%,#a98274 100%);
  color: #fff8f3;
  border: none;
  padding: 10px 25px;
  border-radius: 7px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  transition: background 0.18s, color 0.18s, box-shadow 0.18s;
  box-shadow: 0 2px 8px rgba(93,64,55,0.08);
}
.forecast-btn button:hover, button#exportCsv:hover {
  background: #f3e9e2;
  color: #6d4c41;
  box-shadow: 0 4px 12px rgba(93,64,55,0.13);
}
.forecast-result {
  text-align: center;
  background: #fff8f3;
  padding: 24px;
  border-radius: 14px;
  margin-top: 24px;
  box-shadow: 0 3px 10px rgba(93,64,55,0.05);
  border: 1.5px solid #e0d6d2;
}
.forecast-result h3 {
  color: #6d4c41;
  margin-bottom: 12px;
  text-shadow: 0 1px 0 #f3e9e2;
}
#forecastMessage {
  font-weight: bold;
  color: #a98274;
  font-size: 17px;
}
@media(max-width:768px){
  .forecast-container{ padding:15px; }
  table th, td{ padding:10px; font-size:14px; }
}
</style>
</head>
<body>

<div class="navbar">CafeKantina</div>

<script>localStorage.setItem('staffUsername', <?php echo json_encode($username); ?>);</script>

<div class="forecast-container">
  <div class="forecast-header">
    <h2>📊 Sales Forecasting</h2>
    <p>Identify the best-selling products for this month to prepare next month's restock.</p>
  </div>

  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
    <label for="timeWindow">Window:</label>
    <select id="timeWindow">
      <option value="3">3 months</option>
      <option value="6" selected>6 months</option>
      <option value="12">12 months</option>
    </select>

    <label for="topN">Top N:</label>
    <select id="topN">
      <option value="5" selected>5</option>
      <option value="8">8</option>
      <option value="10">10</option>
    </select>

    <button id="exportCsv" class="forecast-btn" style="margin-left:auto;">Export CSV</button>
  </div>

  <div class="table-wrapper">
    <?php
  // Build forecast data from DB: last 12 months grouped by product (server provides up to 12 months)
    require_once __DIR__ . '/includes/db.php';

    // products list
    $prodStmt = $pdo->prepare('SELECT p.id, p.name, i.available_in_hand AS stock FROM products p LEFT JOIN inventory i ON i.name = p.name AND i.type = "product" ORDER BY p.name');
    $prodStmt->execute();
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

  // months keys (last 12 months)
  $months = [];
  for($i=11;$i>=0;$i--){ $m = date('Y-m', strtotime("-{$i} months")); $months[] = $m; }

  // Detect which date column exists on orders table (order_date, created_at, etc.)
  $possibleDateCols = ['order_date','created_at','created','date','timestamp'];
  $dateCol = null;
  foreach ($possibleDateCols as $c) {
    $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE '" . str_replace("'","\\'", $c) . "'");
    if ($chk && $chk->rowCount() > 0) { $dateCol = $c; break; }
  }
  if (!$dateCol) {
    // fallback: pick first column with type DATETIME or TIMESTAMP
    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
      $type = strtolower($col['Type'] ?? '');
      if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false) { $dateCol = $col['Field']; break; }
    }
  }
  if (!$dateCol) { $dateCol = 'order_date'; }

  // sales per product per month (last 12 months) — use detected date column
  $dateColEsc = str_replace('`','', $dateCol);
  $salesSql = "SELECT oi.product_id, YEAR(o.`$dateColEsc`) as y, MONTH(o.`$dateColEsc`) as m, SUM(oi.quantity) as qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.`$dateColEsc` >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY oi.product_id, y, m";
  $salesStmt = $pdo->prepare($salesSql);
  $salesStmt->execute();
  $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];
    foreach($salesRows as $r){
        $key = sprintf('%04d-%02d', $r['y'], $r['m']);
        $salesMap[$r['product_id']][$key] = (int)$r['qty'];
    }

    $forecastRows = [];
    foreach($products as $p){
        $pid = $p['id'];
        $row = ['product'=>$p['name'], 'stock'=>(int)$p['stock'], 'months'=>[]];
        foreach($months as $mkey){ $row['months'][$mkey] = $salesMap[$pid][$mkey] ?? 0; }
        // compute 3-month moving average using the last 3 months
        $last3 = array_slice(array_values($row['months']), -3);
        $forecast = 0;
        if(count($last3)>0){ $forecast = array_sum($last3)/count($last3); }
        $row['forecast'] = round($forecast,2);
        $forecastRows[] = $row;
    }

    // embed as JSON for client-side chart and dynamic windowing
    $forecastJson = json_encode($forecastRows);
    $monthsJson = json_encode($months);
    ?>
    <table id="forecastTable">
      <thead></thead>
      <tbody></tbody>
    </table>
  </div>



  <div class="forecast-result" id="forecastResult">
    <h3>📈 Forecast Result:</h3>
    <p id="forecastMessage">Click "Generate Forecast" to see which product should be restocked more next month.</p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const forecastRows = <?php echo $forecastJson; ?>;
const months = <?php echo $monthsJson; ?>; // array of YYYY-MM keys, earliest -> latest

// UI elements
const timeSelect = document.getElementById('timeWindow');
const topNSelect = document.getElementById('topN');
const exportBtn = document.getElementById('exportCsv');
const table = document.getElementById('forecastTable');
const chartCanvasId = 'forecastChartFixed';

// Create fixed canvas area
(function createCanvas(){
  const container = document.getElementById('forecastResult');
  const canvas = document.createElement('canvas');
  canvas.id = chartCanvasId;
  canvas.style.maxWidth = '900px';
  canvas.style.margin = '12px auto';
  container.insertBefore(canvas, container.firstChild);
})();

let chartInstance = null;

function computeForecastForWindow(windowMonths){
  // windowMonths: integer number of months to use from the end of months array
  const n = parseInt(windowMonths,10);
  const lastKeys = months.slice(-n);
  const results = forecastRows.map(r => {
    const vals = lastKeys.map(k => parseInt(r.months[k]||0,10));
    const forecast = vals.length? (vals.reduce((s,v)=>s+v,0)/vals.length) : 0;
    return { product: r.product, stock: r.stock, months: vals, forecast: parseFloat(forecast.toFixed(2)) };
  });
  return { lastKeys, results };
}

function renderTableAndChart(){
  const windowMonths = parseInt(timeSelect.value,10);
  const topN = parseInt(topNSelect.value,10);
  const { lastKeys, results } = computeForecastForWindow(windowMonths);

  // build table header
  const thead = table.querySelector('thead');
  const tbody = table.querySelector('tbody');
  thead.innerHTML = '';
  tbody.innerHTML = '';
  const trHead = document.createElement('tr');
  trHead.innerHTML = '<th>Product</th>' + lastKeys.map(k=>`<th>${new Date(k+'-01').toLocaleString('en-US',{month:'short',year:'numeric'})}</th>`).join('') + '<th>Forecast</th><th>Stock</th>';
  thead.appendChild(trHead);

  // sort by product name
  results.sort((a,b)=>a.product.localeCompare(b.product));

  results.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td style="text-align:left;padding-left:12px">${escapeHtml(r.product)}</td>` +
      r.months.map(v=>`<td>${v}</td>`).join('') +
      `<td>${r.forecast.toFixed(2)}</td><td>${r.stock}</td>`;
    tbody.appendChild(tr);
  });

  // chart topN
  const sorted = results.slice().sort((a,b)=>b.forecast - a.forecast).slice(0, topN);
  const labels = sorted.map(x=>x.product);
  const data = sorted.map(x=>x.forecast);

  const ctx = document.getElementById(chartCanvasId).getContext('2d');
  if(chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, { type:'bar', data:{ labels, datasets:[{ label:'Forecast (units)', data, backgroundColor:'#6f4e37' }] }, options:{ plugins:{ legend:{display:false} }, responsive:true } });

  // highlight best
  if(sorted.length>0){
    document.getElementById('forecastMessage').innerHTML = `🔥 <strong>${escapeHtml(sorted[0].product)}</strong> is projected to be the best-selling item next month with an estimated <strong>${sorted[0].forecast.toFixed(2)}</strong> units!`;
  } else {
    document.getElementById('forecastMessage').textContent = 'No forecast data available for the selected window.';
  }
}

function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function exportCSV(){
  const windowMonths = parseInt(timeSelect.value,10);
  const { lastKeys, results } = computeForecastForWindow(windowMonths);
  const headers = ['Product'].concat(lastKeys.map(k=>new Date(k+'-01').toLocaleString('en-US',{month:'short',year:'numeric'}))).concat(['Forecast','Stock']);
  const rows = results.map(r => [r.product].concat(r.months).concat([r.forecast,r.stock]));
  let csv = headers.join(',') + '\n';
  rows.forEach(r => { csv += r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',') + '\n'; });
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = `forecast_${windowMonths}m.csv`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
}

timeSelect.addEventListener('change', renderTableAndChart);
topNSelect.addEventListener('change', renderTableAndChart);
exportBtn.addEventListener('click', exportCSV);

// initial render
document.addEventListener('DOMContentLoaded', renderTableAndChart);
</script>

</body>
</html>
