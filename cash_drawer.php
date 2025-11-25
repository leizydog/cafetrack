<?php
// cashdrawer.php
// Fixed/cleaned version:
// - Server-side AJAX handlers placed before HTML output
// - Consistent endpoint names (cashdrawer.php?action=...)
// - JS moved into proper <script> tags
// - Added basic validation and safe JSON responses
// Requires: includes/auth.php (login helpers), includes/db.php (creates $pdo as PDO)

require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$username = $user['username'] ?? 'Unknown';

// Handle AJAX endpoints before emitting HTML
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // GET: cash sales for today
    if ($action === 'get_cash_sales') {
        require_once __DIR__ . '/includes/db.php'; // expects $pdo (PDO)
        // determine date column
        $dateCol = 'order_date';
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_date'");
            if (!$chk || $chk->rowCount() === 0) $dateCol = 'created_at';
        } catch (Exception $e) {
            $dateCol = 'created_at';
        }
        try {
            $sql = "SELECT IFNULL(SUM(total_amount),0) as total FROM orders WHERE DATE(`$dateCol`) = CURDATE()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $total = (float)$stmt->fetchColumn();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'total_sales' => $total]);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // POST: save closeout
    if ($action === 'save_closeout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/includes/db.php';
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }
        $date = $payload['date'] ?? date('Y-m-d');
        $cashier = $payload['cashier'] ?? '';
        $opening = floatval($payload['opening'] ?? 0);
        $cashSale = floatval($payload['cashSale'] ?? 0);
        $actualCash = floatval($payload['actualCash'] ?? 0);
        $expenses = is_array($payload['expenses']) ? $payload['expenses'] : [];
        try {
            // Ensure the tables exist — we assume you will create them with provided SQL.
            $pdo->beginTransaction();
            $sql = "INSERT INTO cash_closeouts (`date`, cashier, opening, cash_sale, actual_cash, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date, $cashier, $opening, $cashSale, $actualCash]);
            $closeout_id = $pdo->lastInsertId();
            // Insert expenses
            $insertExp = $pdo->prepare("INSERT INTO cash_expenses (closeout_id, description, amount, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            foreach ($expenses as $exp) {
                $desc = trim($exp['desc'] ?? '');
                $amt = floatval($exp['amt'] ?? ($exp['amount'] ?? 0));
                if ($desc !== '' && $amt > 0) {
                    $insertExp->execute([$closeout_id, $desc, $amt]);
                }
            }
            $pdo->commit();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'closeout_id' => $closeout_id]);
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// If no action, render the HTML page below
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CafeKantina — Cash Drawer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --brand-1:#6d4c41;
  --brand-2:#a98274;
  --cream:#fff8f3;
  --card:#fffefc;
  --muted:#8a7267;
  --glass: rgba(255,255,255,0.6);
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
  background:linear-gradient(180deg,var(--cream),#fff);
  color:var(--brand-1);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}
.navbar{position:fixed;left:0;right:0;top:0;height:64px;background:linear-gradient(90deg,var(--brand-1),var(--brand-2));display:flex;align-items:center;justify-content:center;color:var(--cream);box-shadow:0 6px 28px rgba(93,64,55,0.08);z-index:99}
.navbar h1{margin:0;font-size:20px;letter-spacing:1px}
.page{max-width:1100px;margin:110px auto;padding:20px}
.card{background:var(--card);border-radius:14px;padding:28px;box-shadow:0 14px 40px rgba(93,64,55,0.06);border:1px solid #f0e6e3}
.header-row{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
.header-title{display:flex;gap:12px;align-items:center}
.header-title h2{margin:0;font-size:22px}
.header-sub{color:var(--muted);font-size:14px}
.grid{display:grid;grid-template-columns:1fr 380px;gap:22px;align-items:start}
@media(max-width:980px){.grid{grid-template-columns:1fr}}
.form-collection{display:flex;flex-direction:column;gap:14px}
.row{display:flex;gap:12px;align-items:center}
.row.stack{flex-direction:column;align-items:stretch}
.label{flex:0 0 160px;color:var(--muted);font-weight:600}
.input{flex:1}
.input input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #efe6e2;background:var(--glass);font-size:15px}
.input input:focus{outline:none;box-shadow:0 12px 30px rgba(169,130,116,0.06);border-color:var(--brand-2);background:#fff8f3}
.small-muted{font-size:13px;color:var(--muted)}
.expenses{background:linear-gradient(180deg,#fff,#fffaf6);padding:14px;border-radius:10px;border:1px solid #f3e6e1}
.expenses table{width:100%;border-collapse:collapse}
.expenses th,.expenses td{padding:8px 6px;text-align:left}
.expenses .desc input{width:100%;padding:10px;border-radius:8px;border:1px solid #efe6e2;background:transparent}
.expenses .amt input{text-align:right;width:120px;padding:10px;border-radius:8px;border:1px solid #efe6e2;background:transparent}
.expense-controls{display:flex;justify-content:flex-end;gap:8px;margin-top:8px}
.btn{appearance:none;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;font-weight:700}
.btn.ghost{background:#fff;border:1px solid #efe6e2;color:var(--brand-1)}
.btn.primary{background:linear-gradient(90deg,var(--brand-1),var(--brand-2));color:white}
.btn.warning{background:#f6b27c;color:#4a2c2a}
.summary{padding:18px;border-radius:12px;background:linear-gradient(180deg,#fff8f3,#fff);border:1px solid #efe6e2;text-align:center}
.summary .num{font-size:20px;font-weight:800;color:var(--brand-1)}
.summary .note{color:var(--muted);font-size:13px}
.actions{display:flex;gap:12px;margin-top:14px}
.actions .btn{flex:1}
.fade-in{animation:fadeIn .28s ease both}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media(max-width:520px){.label{flex-basis:120px}.header-title h2{font-size:18px}}
.highlight-cash { background: #eaffea !important; border-color: #3c763d !important; transition: background 0.3s; }
</style>
</head>
<body>
<div class="navbar"><h1>CafeKantina</h1></div>

<div class="page">
  <div class="card fade-in">
    <div class="header-row">
      <div class="header-title">
        <div style="width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,var(--brand-2),var(--brand-1));display:flex;align-items:center;justify-content:center;color:white;font-size:20px"><i class="fas fa-cash-register"></i></div>
        <div>
          <h2>Cash Drawer</h2>
          <div class="header-sub">Quick closeout, expenses and cash reconciliation</div>
        </div>
      </div>
      <div style="text-align:right">
        <div class="small-muted">Signed in as</div>
        <div style="font-weight:700"><?php echo htmlspecialchars($username); ?></div>
      </div>
    </div>

    <div class="grid">
      <!-- LEFT: main form -->
      <div>
        <div class="form-collection">
          <div class="row">
            <div class="label">Date</div>
            <div class="input"><input type="date" id="date"></div>
          </div>

          <div class="row">
            <div class="label">Cashier</div>
            <div class="input"><input type="text" id="cashier" readonly></div>
          </div>

          <div class="row">
            <div class="label">Opening Balance</div>
            <div class="input"><input type="number" id="opening" step="0.01" value="0"></div>
          </div>

          <div class="row">
            <div class="label">Cash Sales (today)</div>
            <div class="input"><input type="number" id="cashSale" step="0.01" value="0"></div>
          </div>

          <!-- Expenses -->
          <div class="expenses">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <strong>Store Expenses</strong>
              <div class="small-muted">Record cash-out expenses</div>
            </div>
            <table id="expensesTable">
              <thead><tr><th style="width:65%">Description</th><th style="width:35%;text-align:right">Amount</th></tr></thead>
              <tbody>
                <tr>
                  <td class="desc"><input class="expense-desc" type="text" placeholder="Milk refill"></td>
                  <td class="amt"><input class="expense" type="number" step="0.01" value="0"></td>
                </tr>
              </tbody>
            </table>
            <div class="expense-controls">
              <button type="button" class="btn ghost" onclick="addExpense()">+ Add expense</button>
              <button type="button" class="btn ghost" onclick="removeExpense()">− Remove last</button>
            </div>
          </div>

          <div class="row">
            <div class="label">Actual Cash in Drawer</div>
            <div class="input"><input type="number" id="actualCash" step="0.01" value="0"></div>
          </div>

          <div class="row stack">
            <div class="label"></div>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
              <button type="button" class="btn primary" onclick="closeDrawer()"><i class="fas fa-door-closed"></i> Close Drawer</button>
              <button type="button" class="btn ghost" onclick="openDrawer()"><i class="fas fa-box-open"></i> Open Drawer</button>

            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: summary -->
      <aside>
        <div class="summary">
          <div style="margin-bottom:10px;color:var(--muted)">Today</div>
          <div class="num" id="expectedCash">₱0.00</div>
          <div class="note" id="overShort">Balanced</div>

          <hr style="margin:12px 0;border:0;border-top:1px solid #f0e7e3">

          <div style="text-align:left">
            <div class="small-muted">Last Sale</div>
            <div style="font-weight:700;margin-bottom:10px" id="last-sale">—</div>
            <div class="small-muted">Opening</div>
            <div id="preview-opening" style="margin-bottom:8px">₱0.00</div>
            <div class="small-muted">Expenses</div>
            <div id="preview-expenses" style="margin-bottom:8px">₱0.00</div>
          </div>

          </div>
        </div>
      </aside>
    </div>
  </div>
</div>

<script>
// initialize
document.addEventListener('DOMContentLoaded', function(){
  // set date to today
  const dateEl = document.getElementById('date');
  if (dateEl) dateEl.valueAsDate = new Date();

  // cashier preview: prefer logged-in username, fallback to local storage
  const cashierEl = document.getElementById('cashier');
  if (cashierEl) cashierEl.value = localStorage.getItem('staffUsername') || <?php echo json_encode($username); ?> || 'Unknown';

  // wire event handlers
  ['opening','cashSale','actualCash'].forEach(id=>{
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
  });

  // expenses input
  document.addEventListener('input', function(e){
    if (e.target && e.target.classList && e.target.classList.contains('expense')) updatePreview();
  });

  // attach refresh button to cashSale input
  const cashSaleRow = document.getElementById('cashSale')?.closest('.row');
  if (cashSaleRow) {
    const refreshBtn = document.createElement('button');
    refreshBtn.textContent = '⟳';
    refreshBtn.className = 'btn ghost';
    refreshBtn.style.marginLeft = '8px';
    refreshBtn.title = 'Refresh Cash Sales';
    refreshBtn.onclick = ()=>fetchCashSales(true);
    cashSaleRow.querySelector('.input')?.appendChild(refreshBtn);
  }

  // restore last sale preview
  const last = localStorage.getItem('lastCashSale');
  if (last) document.getElementById('last-sale').textContent = money(last);

  // initial fetch and periodic refresh
  fetchCashSales();
  setInterval(()=>fetchCashSales(true), 5000);

  updatePreview();
});

// currency formatter
const money = v => '₱' + Number(v || 0).toFixed(2);

function gatherExpenses(){
  let total = 0;
  document.querySelectorAll('.expense').forEach(e=> total += Number(e.value || 0));
  return Number(total.toFixed(2));
}

function updatePreview(){
  const opening = Number(document.getElementById('opening').value || 0);
  const cashSale = Number(document.getElementById('cashSale').value || 0);
  const actual = Number(document.getElementById('actualCash').value || 0);
  const expenses = gatherExpenses();
  const expected = opening + cashSale - expenses;
  const diff = Number((actual - expected).toFixed(2));
  document.getElementById('expectedCash').textContent = money(expected);
  document.getElementById('preview-opening').textContent = money(opening);
  document.getElementById('preview-expenses').textContent = money(expenses);
  const overShort = document.getElementById('overShort');
  if (diff === 0) { overShort.textContent = 'Balanced'; overShort.style.color = '' }
  else if (diff > 0) { overShort.textContent = 'Over by ' + money(diff); overShort.style.color = 'green' }
  else { overShort.textContent = 'Short by ' + money(Math.abs(diff)); overShort.style.color = 'red' }
}

function addExpense(){
  const tbody = document.querySelector('#expensesTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="desc"><input class="expense-desc" type="text" placeholder="Description"></td>
    <td class="amt"><input class="expense" type="number" step="0.01" value="0"></td>
  `;
  tbody.appendChild(tr);
  tr.querySelector('.expense').addEventListener('input', updatePreview);
}

function removeExpense(){
  const tbody = document.querySelector('#expensesTable tbody');
  if (tbody.children.length > 1) tbody.removeChild(tbody.lastElementChild);
  updatePreview();
}

// Export / Save / Open drawer stubs
function openDrawer(){ alert('Drawer opened — hardware action required (stub)'); }
function closeDrawer(){ alert('Drawer closed — hardware action required (stub)'); }
function exportReport(){ alert('Exported (stub). Implement CSV or PDF server-side.'); }

async function saveClose(){
  // Gather closeout data
  const date = document.getElementById('date').value;
  const cashier = document.getElementById('cashier').value;
  const opening = Number(document.getElementById('opening').value || 0);
  const cashSale = Number(document.getElementById('cashSale').value || 0);
  const actualCash = Number(document.getElementById('actualCash').value || 0);
  const expenses = [];
  document.querySelectorAll('#expensesTable tbody tr').forEach(tr => {
    const desc = (tr.querySelector('.expense-desc')?.value || '').trim();
    const amt = Number(tr.querySelector('.expense')?.value || 0);
    if (desc && amt > 0) expenses.push({desc, amt});
  });

  try {
    const res = await fetch('cash_drawer.php?action=save_closeout', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({date, cashier, opening, cashSale, actualCash, expenses})
    });
    const data = await res.json();
    if (data.ok) {
      alert('Closeout saved successfully!');
      // update local preview & storage
      localStorage.setItem('lastCashSale', Number(cashSale).toFixed(2));
      window.dispatchEvent(new StorageEvent('storage', {key:'lastCashSale', newValue: Number(cashSale).toFixed(2)}));
      fetchCashSales(true);
    } else {
      alert('Error saving closeout: ' + (data.error || 'Unknown error'));
    }
  } catch (e) {
    alert('Error saving closeout: ' + e);
  }
}

let lastCashSalesValue = 0;
async function fetchCashSales(highlight=false){
  try{
    const res = await fetch('cash_drawer.php?action=get_cash_sales');
    const data = await res.json();
    if (data.ok){
      const cashSaleInput = document.getElementById('cashSale');
      const newVal = Number(data.total_sales || 0).toFixed(2);
      if (highlight && cashSaleInput && cashSaleInput.value !== newVal) {
        cashSaleInput.classList.add('highlight-cash');
        setTimeout(()=>cashSaleInput.classList.remove('highlight-cash'), 1200);
      }
      if (cashSaleInput) cashSaleInput.value = newVal;
      lastCashSalesValue = newVal;
      localStorage.setItem('lastCashSale', newVal);
      const lsEl = document.getElementById('last-sale');
      if (lsEl) lsEl.textContent = money(newVal);
      updatePreview();
    }
  }catch(e){ console.log('fetch failed',e) }
}

// storage listener for cross-tab updates
window.addEventListener('storage', e=>{
  if (e.key === 'refreshCashDrawer') fetchCashSales(true);
  if (e.key === 'lastCashSale') document.getElementById('last-sale').textContent = money(e.newValue);
});
</script>

</body>
</html>
