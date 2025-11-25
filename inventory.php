<?php
// inventory.php
// Unified inventory page (single inventory table) — PHP + MySQLi + AJAX
// Theme: Coffee palette, single-file logic, prepared statements only.

session_start();
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cafetrack';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB connect error: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Ensure `updated_at` column exists in `inventory`. Older DBs may have `created_at` only.
$has_updated_at = false;
try {
  $check = $conn->query("SHOW COLUMNS FROM inventory LIKE 'updated_at'");
  if ($check && $check->num_rows > 0) {
    $has_updated_at = true;
  } else {
    // try to add the column (best-effort). If this fails we'll fall back to using created_at as alias.
    $conn->query("ALTER TABLE inventory ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    // populate from created_at for existing rows
    $conn->query("UPDATE inventory SET updated_at = created_at WHERE updated_at IS NULL");
    $has_updated_at = true;
  }
} catch (Exception $e) {
  // leave $has_updated_at false and fall back to created_at where needed
  $has_updated_at = false;
}

// choose timestamp field expression for SELECTs (alias to updated_at for UI)
$ts_select = $has_updated_at ? 'updated_at' : 'created_at AS updated_at';

/* JSON helper for AJAX */
function json_res($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* ---------- AJAX endpoints (unchanged logic) ---------- */
$action = $_GET['action'] ?? null;

// --- Backend: error handling and endpoint cleanup ---
if ($action === 'get_items') {
    $type = $_GET['type'] ?? '';
    $q = trim($_GET['q'] ?? '');
    $limit = intval($_GET['limit'] ?? 200);
    $offset = intval($_GET['offset'] ?? 0);
    try {
        if ($q !== '') {
            $like = "%$q%";
            if ($type) {
                $stmt = $conn->prepare("SELECT id,name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status,".$ts_select." FROM inventory WHERE type = ? AND (name LIKE ? OR category LIKE ?) ORDER BY name LIMIT ? OFFSET ?");
                $stmt->bind_param('sssii', $type, $like, $like, $limit, $offset);
            } else {
                $stmt = $conn->prepare("SELECT id,name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status,".$ts_select." FROM inventory WHERE (name LIKE ? OR category LIKE ?) ORDER BY name LIMIT ? OFFSET ?");
                $stmt->bind_param('ssii', $like, $like, $limit, $offset);
            }
        } else {
            if ($type) {
                $stmt = $conn->prepare("SELECT id,name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status,".$ts_select." FROM inventory WHERE type = ? ORDER BY name LIMIT ? OFFSET ?");
                $stmt->bind_param('sii', $type, $limit, $offset);
            } else {
                $stmt = $conn->prepare("SELECT id,name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status,".$ts_select." FROM inventory ORDER BY type, name LIMIT ? OFFSET ?");
                $stmt->bind_param('ii', $limit, $offset);
            }
        }
        if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        json_res(['ok'=>1,'items'=>$rows]);
    } catch (Exception $e) {
        json_res(['ok'=>0,'msg'=>$e->getMessage()]);
    }
}

if ($action === 'add_item' && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT')) {
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  if (!is_array($payload)) $payload = $_POST;
  $name = trim($payload['name'] ?? '');
  $type = in_array($payload['type'] ?? '', ['product','ingredient','supply']) ? $payload['type'] : 'ingredient';
  $category = trim($payload['category'] ?? '');
  $opening = max(0, intval($payload['opening_count'] ?? 0));
  $avail = max(0, intval($payload['available_in_hand'] ?? $opening));
  $closing = max(0, intval($payload['closing_count'] ?? $opening));
  $no_utilized = max(0, intval($payload['no_utilized'] ?? 0));
  $unit = trim($payload['unit'] ?? 'pcs');
  if ($name === '') json_res(['ok'=>0,'msg'=>'Name required.']);
  $status = ($avail <= 0) ? 'Out of Stock' : 'Available';
  try {
    // build INSERT depending on whether updated_at column exists
    if ($has_updated_at) {
      $stmt = $conn->prepare("INSERT INTO inventory (name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status,updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    } else {
      $stmt = $conn->prepare("INSERT INTO inventory (name,type,category,opening_count,available_in_hand,closing_count,no_utilized,unit,status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    }
    if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
    $bind = $stmt->bind_param('sssiiiiss', $name, $type, $category, $opening, $avail, $closing, $no_utilized, $unit, $status);
    if ($bind === false) throw new Exception('Bind failed: '.$stmt->error);
    if ($stmt->execute()) {
      $id = $stmt->insert_id;
      $stmt->close();
      json_res(['ok'=>1,'msg'=>'Item added.','id'=>$id]);
    } else {
      $err = $stmt->error;
      $stmt->close();
      throw new Exception('Add failed: '.$err);
    }
  } catch (Exception $e) {
    json_res(['ok'=>0,'msg'=>$e->getMessage()]);
  }
}

if ($action === 'edit_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  if (!is_array($payload)) $payload = $_POST;
  $id = intval($payload['id'] ?? 0);
  if (!$id) json_res(['ok'=>0,'msg'=>'Invalid id.']);
  $name = trim($payload['name'] ?? '');
  $category = trim($payload['category'] ?? '');
  $unit = trim($payload['unit'] ?? '');
  $opening = max(0, intval($payload['opening_count'] ?? 0));
  $avail = max(0, intval($payload['available_in_hand'] ?? $opening));
  $closing = max(0, intval($payload['closing_count'] ?? $opening));
  $no_utilized = max(0, intval($payload['no_utilized'] ?? 0));
  $type = in_array($payload['type'] ?? '', ['product','ingredient','supply']) ? $payload['type'] : 'ingredient';
    try {
    // UPDATE: include updated_at only when the column exists
    if ($has_updated_at) {
      $stmt = $conn->prepare("UPDATE inventory SET name=?, category=?, unit=?, opening_count=?, available_in_hand=?, closing_count=?, no_utilized=?, type=?, updated_at=NOW() WHERE id=?");
    } else {
      $stmt = $conn->prepare("UPDATE inventory SET name=?, category=?, unit=?, opening_count=?, available_in_hand=?, closing_count=?, no_utilized=?, type=? WHERE id=?");
    }
    if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
    $stmt->bind_param('sssiiiisi', $name, $category, $unit, $opening, $avail, $closing, $no_utilized, $type, $id);
    if ($stmt->execute()) { $stmt->close(); json_res(['ok'=>1,'msg'=>'Item updated.']); }
    $err = $stmt->error;
    $stmt->close();
    throw new Exception('Update failed: '.$err);
  } catch (Exception $e) {
    json_res(['ok'=>0,'msg'=>$e->getMessage()]);
  }
}

if ($action === 'delete_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;
    $id = intval($payload['id'] ?? 0);
    if (!$id) json_res(['ok'=>0,'msg'=>'Invalid id.']);
    try {
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id=?");
        if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) { $stmt->close(); json_res(['ok'=>1,'msg'=>'Deleted.']); }
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Delete failed: '.$err);
    } catch (Exception $e) {
        json_res(['ok'=>0,'msg'=>$e->getMessage()]);
    }
}

/* If not AJAX - render HTML page below */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CafeKantina — Inventory</title>
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --coffee-dark:#3e2f2a;
  --coffee-mid:#6d4c41;
  --coffee-warm:#a98274;
  --cream:#fff8f3;
  --muted:#f3e9e2;
  --accent:#c88f62;
  --danger:#e74c3c;
  --success:#2f7a2f;
}
*{box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;margin:0;background:linear-gradient(180deg,var(--cream),#fff);color:var(--coffee-dark);-webkit-font-smoothing:antialiased;}
.nav{position:fixed;top:0;left:0;right:0;height:64px;background:linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));display:flex;align-items:center;justify-content:center;color:#fff;z-index:100;box-shadow:0 4px 18px rgba(93,64,55,0.12)}
.nav .brand{font-weight:700;letter-spacing:1px}
.container{max-width:1200px;margin:96px auto;padding:18px}
.header-card{background:#fff;padding:20px;border-radius:14px;box-shadow:0 10px 40px rgba(93,64,55,0.06);display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.header-card h1{flex-basis:100%;text-align:center;margin:6px 0 10px 0;font-size:20px;color:var(--coffee-dark)}
.controls{display:flex;gap:10px;flex:1;align-items:center}
.controls select,.controls input{padding:10px;border-radius:10px;border:1px solid #eee;background:#fff;min-width:160px}
.controls .search-wrap{flex:1;display:flex;gap:8px}
.btn{background:linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));color:#fff;padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700;box-shadow:0 8px 22px rgba(93,64,55,0.07)}
.btn.secondary{background:#fff;border:1px solid #f0e6e3;color:var(--coffee-dark)}
.quick-panel{margin-top:18px;background:#fff;padding:18px;border-radius:12px;box-shadow:0 8px 30px rgba(93,64,55,0.04);display:flex;flex-direction:column;gap:10px}
.quick-panel .row{display:flex;gap:10px;flex-wrap:wrap}
.quick-panel input{padding:10px;border-radius:10px;border:1px solid #f0e6e3;background:#fff;min-width:160px}
.main-panel{margin-top:18px;background:#fff;padding:18px;border-radius:12px;box-shadow:0 10px 40px rgba(93,64,55,0.05)}
.table-wrap{overflow:auto;margin-top:10px;border-radius:10px}
.table{width:100%;border-collapse:collapse;min-width:900px}
.table th,.table td{padding:12px 14px;text-align:left;border-bottom:1px solid #f3e9e2;vertical-align:middle;font-size:14px}
.table th{background:var(--muted);font-weight:700;color:var(--coffee-mid)}
.table tbody tr:hover{background:linear-gradient(90deg, rgba(168,130,116,0.02), transparent)}
.status-pill{padding:6px 10px;border-radius:999px;font-weight:700;font-size:13px;display:inline-block}
.status-Available{background:rgba(47,122,47,0.08);color:var(--success)}
.status-RunningLow{background:rgba(176,107,26,0.06);color:var(--coffee-warm)}
.status-OutOfStock{background:rgba(199,57,43,0.06);color:var(--danger)}
.actions button{margin-right:8px}
.notice{padding:10px;border-radius:8px;margin-top:12px;background:linear-gradient(90deg,#eef7ef,#f7fdf7);border:1px solid #e6f2e8;color:var(--success);display:inline-block}
.modal-back{position:fixed;inset:0;background:rgba(0,0,0,0.36);display:none;align-items:center;justify-content:center;z-index:9999;animation:fadin .18s ease}
@keyframes fadin{from{opacity:0}to{opacity:1}}
.modal{background:#fff;border-radius:12px;padding:18px;width:calc(100% - 40px);max-width:720px;box-shadow:0 24px 80px rgba(0,0,0,0.18);transform:translateY(-8px);animation:pop .18s ease}
@keyframes pop{from{opacity:0;transform:translateY(-12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.modal h3{margin:0 0 12px 0;color:var(--coffee-mid)}
.modal .form-row{display:flex;gap:10px;flex-wrap:wrap}
.modal .form-row input,.modal .form-row select{flex:1;padding:10px;border-radius:8px;border:1px solid #eee}
.modal .actions{display:flex;justify-content:flex-end;gap:12px;margin-top:12px}
.small{font-size:13px;color:#7a6a64}
@media(max-width:1000px){.controls{flex-direction:column;align-items:stretch}.header-card h1{text-align:left}}
@media(max-width:700px){.table{min-width:700px}.quick-panel .row{flex-direction:column}}
</style>
</head>
<body>
<div class="nav"><div class="brand">CafeKantina</div></div>

<div class="container">
  <!-- Header / search -->
  <div class="header-card" role="region" aria-label="Inventory header">
    <h1>Inventory Management (Unified)</h1>
    <div class="controls" style="width:100%">
      <select id="filterType" aria-label="Filter type">
        <option value="">All types</option>
        <option value="product">Product</option>
        <option value="ingredient">Ingredient</option>
        <option value="supply">Supply</option>
      </select>

      <div class="search-wrap">
        <input id="searchQ" placeholder="Search by name or category..." aria-label="Search inventory">
        <button id="btnSearch" class="btn secondary" aria-label="Search">Search</button>
      </div>

      <div style="margin-left:auto;display:flex;gap:8px">
        <button id="openAddModal" class="btn" aria-label="Add item">+ Add Item</button>
      </div>
    </div>
  </div>

  <!-- Quick Add -->
  <div class="quick-panel" role="region" aria-label="Quick add ingredient">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <strong>Quick Add Ingredient</strong>
      <small class="small">This quick form defaults to ingredient type</small>
    </div>
    <div class="row">
      <input id="qa_name" placeholder="Ingredient name" />
      <input id="qa_category" placeholder="Category" />
      <input id="qa_opening" type="number" min="0" placeholder="Opening count" />
      <input id="qa_unit" placeholder="Unit (e.g. g, pcs)" />
      <button id="qa_add" class="btn" style="min-width:140px">Add Ingredient</button>
    </div>
  </div>

  <!-- Main table -->
  <div class="main-panel" role="main">
    <div id="notice" aria-live="polite"></div>
    <div id="itemsPanel" class="table-wrap" aria-live="polite">
      <!-- table loaded by JS -->
      <div class="small">Loading inventory…</div>
    </div>
  </div>
</div>

<!-- modal -->
<div class="modal-back" id="modalBack" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal" id="modalBox" role="document">
    <div id="modalContent"></div>
  </div>
</div>

<script>
/* Minimal helpers */
async function getJSON(url){ const r = await fetch(url); return r.json(); }
async function postJSON(url, data){ const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)}); return r.json(); }
function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

/* DOM refs */
const itemsPanel = document.getElementById('itemsPanel');
const filterType = document.getElementById('filterType');
const searchQ = document.getElementById('searchQ');
const btnSearch = document.getElementById('btnSearch');
const notice = document.getElementById('notice');
const modalBack = document.getElementById('modalBack');
const modalContent = document.getElementById('modalContent');

let cachedItems = []; // small client cache for editing convenience

/* Load and render items */
async function loadItems(){
  const type = filterType.value;
  const q = searchQ.value.trim();
  itemsPanel.innerHTML = '<div class="small">Loading inventory…</div>';
  try {
    const url = 'inventory.php?action=get_items&type='+encodeURIComponent(type)+'&q='+encodeURIComponent(q);
    const res = await getJSON(url);
    if (!res.ok) { itemsPanel.innerHTML = '<div class="small">Failed to load data.</div>'; return; }
    const rows = res.items || [];
    cachedItems = rows; // cache
    if (!rows.length) { itemsPanel.innerHTML = '<div class="small">No items found.</div>'; return; }

    // Build table
    let html = '<table class="table" role="table"><thead><tr><th>Item</th><th>Type</th><th>Category</th><th>Opening</th><th>Available</th><th>Closing</th><th>No. Utilized</th><th>Unit</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>';
    rows.forEach(r=>{
      // status normalized for CSS class
      const norm = String(r.status || '').replace(/\s+/g,'');
      let statusClass = '';
      if (norm.toLowerCase().includes('available')) statusClass = 'status-Available';
      else if (norm.toLowerCase().includes('low')) statusClass = 'status-RunningLow';
      else statusClass = 'status-OutOfStock';
        html += '<tr>' +
        '<td>'+escapeHtml(r.name)+'</td>' +
        '<td>'+escapeHtml(r.type)+'</td>' +
        '<td>'+escapeHtml(r.category || '')+'</td>' +
        '<td>'+Number(r.opening_count)+'</td>' +
        '<td>'+Number(r.available_in_hand)+'</td>' +
        '<td>'+Number(r.closing_count)+'</td>' +
        '<td>'+Number(r.no_utilized)+'</td>' +
        '<td>'+escapeHtml(r.unit || '')+'</td>' +
        '<td><span class="status-pill '+statusClass+'">'+escapeHtml(r.status || '')+'</span></td>' +
        '<td>'+escapeHtml(r.updated_at || '')+'</td>' +
        '<td class="actions">' +
          '<button class="btn secondary edit-btn" data-id="'+r.id+'">Edit</button>' +
          '<button class="btn delete-btn" style="background:var(--danger)" data-id="'+r.id+'" data-name="'+escapeHtml(r.name)+'">Delete</button>' +
        '</td>' +
      '</tr>';
    });
    html += '</tbody></table>';
    itemsPanel.innerHTML = html;
    // attach delete handlers (and edit handlers) after DOM insertion to avoid inline onclick quoting issues
    document.querySelectorAll('.delete-btn').forEach(btn=>{
      btn.addEventListener('click', (e)=>{
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name') || '';
        confirmDelete(Number(id), name);
      });
    });
    document.querySelectorAll('.edit-btn').forEach(btn=>{
      btn.addEventListener('click', (e)=>{
        const id = btn.getAttribute('data-id');
        openEdit(Number(id));
      });
    });
  } catch (err) {
    itemsPanel.innerHTML = '<div class="small">Error loading inventory.</div>';
    console.error(err);
  }
}

/* Notice */
function showNotice(type, txt){
  const el = document.createElement('div');
  el.className = 'notice';
  el.textContent = txt;
  notice.innerHTML = '';
  notice.appendChild(el);
  setTimeout(()=>{ if (notice.contains(el)) notice.removeChild(el); }, 4000);
}

/* Modal utilities */
function showModal(title, innerHtml){
  modalContent.innerHTML = '<h3>'+escapeHtml(title)+'</h3>' + innerHtml;
  modalBack.style.display = 'flex';
  modalBack.setAttribute('aria-hidden','false');
}
function closeModal(){ modalBack.style.display = 'none'; modalBack.setAttribute('aria-hidden','true'); }

/* Add item modal (FULL version - kept per your choice A) */
document.getElementById('openAddModal').addEventListener('click', ()=>{
  const html = `
    <div class="form-row">
      <select id="m_type"><option value="product">Product</option><option value="ingredient" selected>Ingredient</option><option value="supply">Supply</option></select>
      <input id="m_name" placeholder="Name" />
    </div>
    <div class="form-row" style="margin-top:8px">
      <input id="m_category" placeholder="Category" />
      <input id="m_opening" type="number" min="0" placeholder="Opening count" />
      <input id="m_available" type="number" min="0" placeholder="Available in hand" />
      <input id="m_closing" type="number" min="0" placeholder="Closing count" />
      <input id="m_utilized" type="number" min="0" placeholder="No. Utilized" />
      <input id="m_unit" placeholder="Unit (pcs,g,ml)" />
    </div>
    <div class="actions" style="margin-top:12px">
      <button class="btn secondary" onclick="closeModal()">Cancel</button>
      <button class="btn" id="m_save">Save</button>
    </div>
  `;
  showModal('Add inventory item', html);

  // Attach listener once
  const mSaveBtn = document.getElementById('m_save');
  if (mSaveBtn) {
    mSaveBtn.addEventListener('click', async function handler(){
      mSaveBtn.disabled = true;
      try {
        const payload = {
          name: document.getElementById('m_name').value.trim(),
          type: document.getElementById('m_type').value,
          category: document.getElementById('m_category').value.trim(),
          opening_count: parseInt(document.getElementById('m_opening').value || 0),
          available_in_hand: parseInt(document.getElementById('m_available').value || 0),
          closing_count: parseInt(document.getElementById('m_closing').value || 0),
          no_utilized: parseInt(document.getElementById('m_utilized').value || 0),
          unit: document.getElementById('m_unit').value.trim() || 'pcs'
        };
        if (!payload.name) { alert('Name required'); return; }
        const res = await postJSON('inventory.php?action=add_item', payload);
        if (!res.ok) { alert(res.msg || 'Add failed'); return; }
        closeModal();
        loadItems();
        showNotice('success', res.msg || 'Added');
      } catch (err) {
        showNotice('error', err.message || 'Add failed');
      } finally {
        mSaveBtn.disabled = false;
        // remove handler to avoid duplicates if modal reopened
        try { mSaveBtn.removeEventListener('click', handler); } catch(e){}
      }
    }, { once: true });
  }
});

// --- Frontend: robust JS actions and error handling ---
// Quick Add
const qaAddBtn = document.getElementById('qa_add');
qaAddBtn.addEventListener('click', async (e)=>{
  e.preventDefault();
  qaAddBtn.disabled = true;
  try {
    const payload = {
      name: document.getElementById('qa_name').value.trim(),
      type: 'ingredient',
      category: document.getElementById('qa_category').value.trim(),
      opening_count: parseInt(document.getElementById('qa_opening').value || 0),
      unit: document.getElementById('qa_unit').value.trim() || 'pcs'
    };
    if (!payload.name) throw new Error('Name required');
    const res = await postJSON('inventory.php?action=add_item', payload);
    if (!res.ok) throw new Error(res.msg || 'Add failed');
    document.getElementById('qa_name').value=''; document.getElementById('qa_category').value=''; document.getElementById('qa_opening').value=''; document.getElementById('qa_unit').value='';
    loadItems();
    showNotice('success', res.msg || 'Added');
  } catch (err) {
    showNotice('error', err.message);
  } finally {
    qaAddBtn.disabled = false;
  }
});

/* Edit flow: find in cachedItems */
async function openEdit(id){
  const item = cachedItems.find(x => Number(x.id) === Number(id));
  if (!item) {
    const data = await getJSON('inventory.php?action=get_items');
    const found = (data.items||[]).find(x=>Number(x.id)===Number(id));
    if (!found) { showNotice('error', 'Item not found'); return; }
    return openEditWithItem(found);
  }
  openEditWithItem(item);
}
function openEditWithItem(item){
  const html = `
    <div class="form-row">
      <input id="e_name" value="${escapeHtml(item.name)}" />
      <select id="e_type">
        <option value="product"${item.type==='product'?' selected':''}>Product</option>
        <option value="ingredient"${item.type==='ingredient'?' selected':''}>Ingredient</option>
        <option value="supply"${item.type==='supply'?' selected':''}>Supply</option>
      </select>
    </div>
    <div class="form-row" style="margin-top:8px">
      <input id="e_category" value="${escapeHtml(item.category || '')}" />
      <input id="e_opening" type="number" min="0" value="${Number(item.opening_count)||0}" placeholder="Opening count" />
      <input id="e_available" type="number" min="0" value="${Number(item.available_in_hand)||0}" placeholder="Available in hand" />
      <input id="e_closing" type="number" min="0" value="${Number(item.closing_count)||0}" placeholder="Closing count" />
      <input id="e_utilized" type="number" min="0" value="${Number(item.no_utilized)||0}" placeholder="No. Utilized" />
      <input id="e_unit" value="${escapeHtml(item.unit || '')}" />
    </div>
    <div class="actions" style="margin-top:12px">
      <button class="btn secondary" onclick="closeModal()">Cancel</button>
      <button class="btn" id="e_save">Save</button>
    </div>
  `;
  showModal('Edit item', html);
  const eSaveBtn = document.getElementById('e_save');
  if (eSaveBtn) {
    eSaveBtn.addEventListener('click', async function handler(){
      eSaveBtn.disabled = true;
      try {
        const payload = {
          id: item.id,
          name: document.getElementById('e_name').value.trim(),
          type: document.getElementById('e_type').value,
          category: document.getElementById('e_category').value.trim(),
          opening_count: parseInt(document.getElementById('e_opening').value || 0),
          available_in_hand: parseInt(document.getElementById('e_available').value || 0),
          closing_count: parseInt(document.getElementById('e_closing').value || 0),
          no_utilized: parseInt(document.getElementById('e_utilized').value || 0),
          unit: document.getElementById('e_unit').value.trim() || 'pcs'
        };
        const res = await postJSON('inventory.php?action=edit_item', payload);
        if (!res.ok) throw new Error(res.msg || 'Update failed');
        closeModal();
        loadItems();
        showNotice('success', res.msg || 'Updated');
      } catch (err) {
        showNotice('error', err.message);
      } finally {
        eSaveBtn.disabled = false;
        try { eSaveBtn.removeEventListener('click', handler); } catch(e){}
      }
    }, { once: true });
  }
}

// Delete
function confirmDelete(id, nameEscaped){
  const html = `<p>Are you sure you want to delete <strong>${nameEscaped}</strong>? This cannot be undone.</p>
    <div class="actions">
      <button class="btn secondary" onclick="closeModal()">Cancel</button>
      <button class="btn" id="confirmDel">Delete</button>
    </div>`;
  showModal('Confirm delete', html);

  // ensure element exists
  const delBtn = document.getElementById('confirmDel');
  if (!delBtn) return;

  // remove prior handlers and attach new one
  delBtn.replaceWith(delBtn.cloneNode(true));
  const newDel = document.getElementById('confirmDel') || document.querySelector('#modalContent #confirmDel');
  // The replace approach above ensures no double listeners; re-query new element
  const finalDel = document.getElementById('confirmDel') || newDel;
  if (!finalDel) return;

  finalDel.addEventListener('click', async function handler(e){
    finalDel.disabled = true;
    try {
      const res = await postJSON('inventory.php?action=delete_item', {id});
      if (!res.ok) throw new Error(res.msg || 'Delete failed');
      closeModal();
      loadItems();
      showNotice('success', res.msg || 'Deleted');
    } catch (err) {
      showNotice('error', err.message);
    } finally {
      finalDel.disabled = false;
      try { finalDel.removeEventListener('click', handler); } catch(e){}
    }
  }, { once: true });
}

/* bind search/filter events */
btnSearch.addEventListener('click', ()=>loadItems());
filterType.addEventListener('change', ()=>loadItems());
searchQ.addEventListener('keyup', (e)=>{ if (e.key==='Enter') loadItems(); });

/* close modal by clicking backdrop */
modalBack.addEventListener('click', (ev)=>{ if (ev.target === modalBack) closeModal(); });

/* initial load */
loadItems();
</script>
</body>
</html>
