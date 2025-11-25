<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$user = current_user();
if (($user['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Ensure table exists (non-destructive)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      action ENUM('login','logout','password_change') NOT NULL,
      ip VARCHAR(45) DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_id), INDEX(action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // ignore creation errors; table may already exist or migrations handled elsewhere
}

// Input handling (filters & pagination)
$q = trim((string)($_GET['q'] ?? ''));
$actionFilter = $_GET['action'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.username LIKE :q OR u.email LIKE :q)';
    $params[':q'] = "%{$q}%";
}
if (in_array($actionFilter, ['login', 'logout', 'password_change'], true)) {
    $where[] = 'll.action = :action';
    $params[':action'] = $actionFilter;
}
if ($from) {
    $where[] = 'll.created_at >= :from';
    $params[':from'] = $from . ' 00:00:00';
}
if ($to) {
    $where[] = 'll.created_at <= :to';
    $params[':to'] = $to . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// total count
$countSql = "SELECT COUNT(*) FROM login_logs ll LEFT JOIN users u ON u.id = ll.user_id {$whereSql}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// fetch page
$sql = "SELECT ll.*, u.username, u.email, u.role FROM login_logs ll LEFT JOIN users u ON u.id = ll.user_id {$whereSql} ORDER BY ll.created_at DESC LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// fetch pending password reset requests for admin review
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      username VARCHAR(255) NULL,
      email VARCHAR(255) NULL,
      message TEXT NULL,
      status ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending',
      admin_id INT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      processed_at DATETIME NULL,
      INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pr = $pdo->query('SELECT * FROM password_reset_requests WHERE status = "pending" ORDER BY created_at DESC');
    $pending_requests = $pr->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending_requests = [];
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Activity Logs - CafeKantina</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--bg:#f3e9e2;--accent:#5d4037;--accent-2:#a98274;--muted:#7d6d63;--card:#fff8f3;--danger:#d32f2f}
    *{box-sizing:border-box}
    body{margin:0;font-family:'Poppins',Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,'Helvetica Neue',Arial;background:linear-gradient(135deg,var(--bg),#fffdf9);color:#4a2c2a}
    .navbar{background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#fff;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 8px 28px rgba(93,64,55,0.12)}
    .navbar h1{font-size:22px;margin:0;font-weight:700;letter-spacing:1px;text-shadow:0 1px 0 #f3e9e2}
    .container{max-width:1100px;margin:32px auto 0 auto;padding:0 18px}
    .card{background:var(--card);border-radius:14px;padding:22px 18px;margin-bottom:22px;box-shadow:0 10px 32px rgba(93,64,55,0.08)}
    .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:14px}
    label{font-size:13px;color:var(--muted);display:block;margin-bottom:6px}
    input[type=text],input[type=date],select,input[type=number]{width:100%;padding:10px 12px;border:1.5px solid #e6d6d2;border-radius:9px;background:#fff8f3}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:0;cursor:pointer;font-weight:700;font-size:15px}
    .btn-primary{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:#fff}
    .btn-secondary{background:#f0f0f0;color:#333}
    .btn-danger{background:linear-gradient(135deg,#e55353,#d32f2f);color:#fff}
    .stats{display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap}
    .stat{flex:1;min-width:140px;padding:14px 10px;border-radius:10px;background:linear-gradient(180deg,#fff,#fff8f3);border-left:5px solid var(--accent-2)}
    table{width:100%;border-collapse:collapse;background:#fff8f3;border-radius:10px;overflow:hidden}
    th,td{padding:12px 10px;border-bottom:1px solid #f1f1f1;text-align:left}
    th{font-size:13px;color:var(--accent);text-transform:uppercase;background:#f3e9e2}
    .badge{display:inline-block;padding:6px 12px;border-radius:999px;font-weight:700;font-size:13px}
    .badge.login{background:#e8f5e9;color:#2e7d32}
    .badge.logout{background:#ffebee;color:#c62828}
    .badge.password_change{background:#e3f2fd;color:#1565c0}
    .controls{display:flex;gap:10px;align-items:center}
    .pagination{display:flex;gap:8px;flex-wrap:wrap}
    a.page{padding:7px 12px;border-radius:7px;border:1.5px solid #e6d6d2;text-decoration:none;color:var(--accent);background:#fff8f3}
    a.page.current{background:var(--accent);color:#fff;border-color:var(--accent)}
    footer{padding:14px;text-align:center;color:#fff;background:linear-gradient(90deg,var(--accent),var(--accent-2));border-top:1px solid rgba(0,0,0,0.04)}
    @media(max-width:600px){.filters{grid-template-columns:1fr}.controls{flex-direction:column;align-items:stretch}}
    </style>
  </head>
  <link rel="stylesheet" href="assets/css/theme.css">
  <body>
  <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="container">
    <div class="card">
      <h2 style="margin:0 0 6px 0;color:var(--accent);">Staff Activity Logs</h2>
      <p style="margin:0 0 12px 0;color:var(--muted);">Filter and review login/logout/password change events.</p>

      <form method="GET" style="margin-bottom:8px">
        <div class="filters">
          <div>
            <label>Search (username or email)</label>
            <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Search...">
          </div>
          <div>
            <label>Action</label>
            <select name="action">
              <option value="">All Actions</option>
              <option value="login" <?php if ($actionFilter === 'login') echo 'selected'; ?>>Login</option>
              <option value="logout" <?php if ($actionFilter === 'logout') echo 'selected'; ?>>Logout</option>
              <option value="password_change" <?php if ($actionFilter === 'password_change') echo 'selected'; ?>>Password Change</option>
            </select>
          </div>
          <div>
            <label>From</label>
            <input type="date" name="from" value="<?php echo h($from); ?>">
          </div>
          <div>
            <label>To</label>
            <input type="date" name="to" value="<?php echo h($to); ?>">
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Apply</button>
          <a class="btn btn-secondary" href="admin_staff_logs.php">Reset</a>
        </div>
      </form>

      <div class="stats">
        <div class="stat"><div style="font-size:20px;font-weight:700"><?php echo h($total); ?></div><div style="font-size:12px;color:var(--muted)">Total Entries</div></div>
        <div class="stat"><div style="font-size:20px;font-weight:700"><?php echo h(count($logs)); ?></div><div style="font-size:12px;color:var(--muted)">Showing this page</div></div>
        <div class="stat"><div style="font-size:20px;font-weight:700"><?php echo h($page); ?>/<?php echo h($totalPages); ?></div><div style="font-size:12px;color:var(--muted)">Page</div></div>
      </div>

      <?php if (!empty($pending_requests)): ?>
      <div class="card" style="margin-bottom:12px">
        <h3 style="margin:0 0 8px;color:var(--accent);">Pending Password Reset Requests</h3>
        <p style="margin:0 0 12px;color:var(--muted);">Staff who cannot login may submit a request. Approve to set a temporary password or cancel.</p>
        <div style="overflow-x:auto">
          <table>
            <thead>
              <tr><th>When</th><th>Username</th><th>Email</th><th>Message</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach($pending_requests as $pr): ?>
              <tr id="pr_row_<?php echo (int)$pr['id']; ?>">
                <td><?php echo h($pr['created_at']); ?></td>
                <td><?php echo h($pr['username'] ?? '—'); ?></td>
                <td><?php echo h($pr['email'] ?? '—'); ?></td>
                <td><?php echo h($pr['message'] ?? ''); ?></td>
                <td>
                  <button class="btn btn-primary" onclick="handleRequest(<?php echo (int)$pr['id']; ?>, 'approve', this)">Approve</button>
                  <button class="btn btn-secondary" onclick="handleRequest(<?php echo (int)$pr['id']; ?>, 'cancel', this)">Cancel</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Action</th>
              <th>IP</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:18px">No records found</td></tr>
          <?php else: foreach ($logs as $l): ?>
            <tr>
              <td><?php echo h($l['created_at']); ?></td>
              <td><?php echo h($l['username'] ?? 'Unknown'); ?></td>
              <td><?php echo h($l['email'] ?? '—'); ?></td>
              <td><?php echo h($l['role'] ?? '—'); ?></td>
              <td>
                <?php $a = $l['action']; $cls = 'badge ' . str_replace('_','',$a); ?>
                <span class="<?php echo h($cls); ?>"><?php echo h(str_replace('_',' ',ucfirst($a))); ?></span>
              </td>
              <td><?php echo h($l['ip'] ?? '—'); ?></td>
              <td>
                <?php if (!empty($l['user_id'])): ?>
                  <button class="btn btn-secondary" onclick="openEditModal(<?php echo (int)$l['user_id']; ?>, '<?php echo h($l['username'] ?? ''); ?>', '<?php echo h($l['role'] ?? ''); ?>')"><i class="fas fa-user-cog"></i> Edit</button>
                <?php else: ?>
                  <span style="color:var(--muted)">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap">
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a class="page" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>1])); ?>">First</a>
              <a class="page" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
              <?php if ($p == $page): ?><a class="page current" href="#"><?php echo $p; ?></a>
              <?php else: ?><a class="page" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>"><?php echo $p; ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
              <a class="page" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Next</a>
              <a class="page" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$totalPages])); ?>">Last</a>
            <?php endif; ?>
          </div>

          <div style="min-width:220px">
            <form method="POST" action="Scripts/purge_login_logs.php" onsubmit="return confirm('This will permanently delete records older than the specified number of days. Proceed?');" style="display:flex;gap:8px;align-items:center">
              <label style="margin:0;color:var(--muted);font-size:13px">Purge older than</label>
              <input type="number" name="days" value="90" min="1" max="365" style="width:90px;padding:8px;border-radius:6px;border:1px solid #e6e6e6">
              <button class="btn btn-danger" type="submit">Purge</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <a href="admin_dashboard.php" style="display:inline-block;margin-bottom:30px;color:var(--accent);text-decoration:none">← Back to Admin Dashboard</a>
  </div>

  
  <!-- Logout Modal -->
  <div id="logoutModal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:999">
    <div style="background:#fff;padding:22px;border-radius:10px;width:320px;text-align:center">
      <h3 style="margin:0 0 8px">Confirm Logout</h3>
      <p style="color:var(--muted);margin:0 0 16px">Are you sure you want to log out?</p>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-block" onclick="hideLogoutModal()">Cancel</button>
        <a class="btn btn-danger btn-block" href="logout.php">Logout</a>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="editUserModal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:1000">
    <div style="background:#fff;padding:18px;border-radius:10px;width:420px">
      <h3 style="margin:0 0 8px">Edit User</h3>
      <p id="editUserLabel" style="color:var(--muted);margin:0 0 8px"></p>
      <form id="editUserForm" method="POST" action="admin_reset_user.php">
        <input type="hidden" name="user_id" id="edit_user_id">
        <label style="display:block;margin-top:8px">New password (leave blank to keep current)</label>
        <input type="password" name="new_pw" id="edit_new_pw" style="width:100%;padding:8px;border:1px solid #e6e6e6;border-radius:6px">
        <label style="display:block;margin-top:8px">Confirm password</label>
        <input type="password" name="confirm_pw" id="edit_confirm_pw" style="width:100%;padding:8px;border:1px solid #e6e6e6;border-radius:6px">
        <label style="display:block;margin-top:8px">Role</label>
        <select name="role" id="edit_role" style="width:100%;padding:8px;border:1px solid #e6e6e6;border-radius:6px">
          <option value="">— keep current —</option>
          <option value="admin">Admin</option>
          <option value="staff">Staff</option>
          <option value="member">Member</option>
        </select>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button type="button" class="btn btn-secondary btn-block" onclick="hideEditModal()">Cancel</button>
          <button type="submit" class="btn btn-primary btn-block">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function showLogoutModal(){document.getElementById('logoutModal').style.display='flex'}
    function hideLogoutModal(){document.getElementById('logoutModal').style.display='none'}
    function openEditModal(id, username, role){
      document.getElementById('edit_user_id').value = id || '';
      document.getElementById('editUserLabel').textContent = (username || 'User') + (role ? (' — current role: ' + role) : '');
      var sel = document.getElementById('edit_role');
      if(role){ for(var i=0;i<sel.options.length;i++){ sel.options[i].selected = (sel.options[i].value === role); } }
      else sel.value = '';
      document.getElementById('edit_new_pw').value = '';
      document.getElementById('edit_confirm_pw').value = '';
      document.getElementById('editUserModal').style.display='flex';
    }
    function hideEditModal(){document.getElementById('editUserModal').style.display='none'}
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ hideEditModal(); hideLogoutModal(); } });
  </script>

  <!-- Temp password modal -->
  <div id="tempPwModal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:1100">
    <div style="background:#fff;padding:18px;border-radius:10px;width:420px;text-align:left">
      <h3 style="margin:0 0 8px">Temporary Password</h3>
      <p style="color:var(--muted);margin:0 0 10px">Provide this temporary password to the user and advise them to change it after logging in.</p>
      <div id="tempPwBox" style="background:#f6f6f6;padding:12px;border-radius:8px;font-family:monospace;font-size:16px;word-break:break-all"></div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <button class="btn btn-secondary btn-block" onclick="document.getElementById('tempPwModal').style.display='none'">Close</button>
        <button class="btn btn-primary btn-block" id="copyTempBtn" onclick="copyTemp()">Copy</button>
      </div>
    </div>
  </div>

  <script>
    async function handleRequest(id, action, btn){
      if(!confirm('Are you sure you want to ' + action + ' this request?')) return;
      btn.disabled = true;
      try{
        const resp = await fetch('api/admin_handle_reset_request.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: action })
        });
        const data = await resp.json();
        if(!data.success){ alert(data.message || 'Action failed'); btn.disabled = false; return; }
        if(action === 'approve' && data.temp_password){
          document.getElementById('tempPwBox').textContent = data.temp_password;
          document.getElementById('tempPwModal').style.display = 'flex';
        }
        // remove row from UI
        const row = document.getElementById('pr_row_' + id);
        if(row) row.parentNode.removeChild(row);
      } catch(err){ alert('Network error'); }
      btn.disabled = false;
    }
    function copyTemp(){
      const t = document.getElementById('tempPwBox').textContent;
      if(!t) return;
      navigator.clipboard?.writeText(t).then(()=>{ alert('Copied to clipboard'); }).catch(()=>{ alert('Copy failed'); });
    }
  </script>

</body>
</html>
