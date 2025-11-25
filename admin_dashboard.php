<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$username = $user['username'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>CafeKantina — Dashboard</title>

<!-- icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<!-- Google font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
  :root{
    --bg-cream: #fff8f3;
    --coffee-dark: #3e2f2a;
    --coffee-mid: #6d4c41;
    --coffee-warm: #a98274;
    --muted: #f3e9e2;
    --glass: rgba(255,255,255,0.6);
    --accent: #c88f62;
    --shadow: 0 10px 30px rgba(62,47,42,0.08);
    --card-radius: 16px;
  }
  /* dark mode variables */
  .dark { --bg-cream: #262323; --coffee-dark:#f6efe9; --coffee-mid:#b98d70; --coffee-warm:#d7b59a; --muted:#312a29; --glass: rgba(0,0,0,0.32); --accent:#f5d6b7; }

  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Arial;
    color:var(--coffee-dark);
    background:
      linear-gradient(180deg, rgba(255,255,255,0.9), rgba(255,250,248,0.95)),
      url('sandbox:/mnt/data/e339f013-03ef-4c19-9c30-5a98ad21fc84.png') center/1200px repeat; /* local asset path */
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    transition:background 0.4s ease, color .3s ease;
  }

  /* NAVBAR */
  .navbar {
    position:sticky; top:0; z-index:1000;
    display:flex; align-items:center; justify-content:space-between;
    gap:20px; padding:14px 22px;
    background: linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));
    color:var(--bg-cream);
    box-shadow:0 6px 24px rgba(62,47,42,0.12);
    backdrop-filter: blur(6px);
  }
  .brand { display:flex;align-items:center;gap:14px; cursor:pointer; }
  .brand .logo {
    width:46px; height:46px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center;
    background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.12));
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
    font-size:20px; font-weight:700;
  }
  .brand h1 { font-size:20px; margin:0; font-weight:700; letter-spacing:1px; color:var(--bg-cream); }
  .nav-right { display:flex; gap:12px; align-items:center; }

  /* profile dropdown */
  .profile {
    position:relative;
  }
  .profile-btn {
    display:flex; align-items:center; gap:10px; cursor:pointer;
    background: rgba(255,255,255,0.06); padding:8px 12px; border-radius:10px; color:var(--bg-cream);
    border: 1px solid rgba(255,255,255,0.06);
  }
  .profile-btn img{ width:36px; height:36px; border-radius:10px; object-fit:cover; }
  .dropdown {
    position:absolute; right:0; top:56px; width:220px; background:var(--bg-cream); color:var(--coffee-dark);
    border-radius:12px; box-shadow:var(--shadow); padding:10px; display:none;
  }
  .dropdown.show { display:block; animation:pop .18s ease; }
  .dropdown a{ display:flex; gap:10px; align-items:center; padding:8px 10px; text-decoration:none; color:inherit; border-radius:8px;}
  .dropdown a:hover{ background:var(--muted); }

  /* greeting & header area */
  .header {
    max-width:1200px; margin:28px auto 6px; padding:0 20px; display:flex; justify-content:space-between; align-items:center;
  }
  .greeting { font-size:20px; color:var(--coffee-dark); font-weight:600; }
  .datetime { color: #6b564d; font-size:14px; display:flex; gap:10px; align-items:center; }

  /* main cards grid */
  .main {
    max-width:1200px; margin:12px auto; padding: 0 20px 40px; width:100%;
    display:grid; grid-template-columns: 1fr 360px; gap:20px;
  }
  @media (max-width:1000px){ .main{ grid-template-columns: 1fr; } }

  .left {
    display:flex; flex-direction:column; gap:20px;
  }
  .cards {
    display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:18px;
  }
  .card {
    background: linear-gradient(180deg,var(--bg-cream), #fff8f3);
    border-radius: var(--card-radius);
    padding:22px;
    border:1px solid rgba(0,0,0,0.04);
    box-shadow: 0 8px 30px rgba(62,47,42,0.06);
    transition: transform .35s cubic-bezier(.2,.9,.3,1), box-shadow .35s, border-color .35s;
    cursor:pointer;
  }
  .card:hover { transform:translateY(-8px) scale(1.02); box-shadow: 0 18px 44px rgba(62,47,42,0.12); border-color:var(--accent); }
  .card .icon { font-size:34px; color:var(--coffee-mid); margin-bottom:10px; transition:transform .25s, color .25s; }
  .card .meta{ display:flex; justify-content:space-between; align-items:center; }
  .card .title { font-weight:700; font-size:16px; color:var(--coffee-dark); }
  .card .sub { color:#7c5b4f; font-size:13px; margin-top:6px; }

  /* right column: stats & quick actions */
  .right {
    display:flex; flex-direction:column; gap:18px;
    position:relative;
  }
  .panel {
    background: linear-gradient(180deg, rgba(255,255,255,0.9), var(--bg-cream));
    border-radius: 14px; padding:16px; box-shadow: var(--shadow); border:1px solid rgba(0,0,0,0.04);
  }
  .stats { display:flex; gap:12px; }
  .stat {
    flex:1; padding:14px; border-radius:12px; background:linear-gradient(180deg,#fff,#fff8f3); text-align:center;
    box-shadow: 0 6px 18px rgba(62,47,42,0.04);
  }
  .stat .num { font-size:22px; font-weight:800; color:var(--coffee-dark); }
  .stat .label { font-size:13px; color:#7e5c4a; margin-top:6px; }

  /* toggle */
  .controls { display:flex; gap:8px; align-items:center; justify-content:flex-end; }
  .toggle {
    width:44px; height:24px; background:var(--muted); border-radius:24px; position:relative; cursor:pointer; border:1px solid rgba(0,0,0,0.04);
  }
  .toggle .dot { width:18px;height:18px;border-radius:50%;background:var(--coffee-mid);position:absolute;left:3px;top:3px;transition:all .22s ease; }
  .toggle.on { background:linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm)); }
  .toggle.on .dot { left:23px; background:#fff8f3; }

  /* footer */
  footer{ text-align:center;padding:14px;font-size:14px;color:var(--coffee-dark); opacity:0.9; margin-top:auto; }

  /* animations */
  @keyframes pop { from { transform: translateY(6px) scale(.98); opacity:0 } to { transform:none; opacity:1 } }
  @media (max-width:600px){ .greeting{font-size:18px} .navbar h1{font-size:18px} .brand .logo{width:40px;height:40px} .profile-btn span{display:none} }

  /* minimal button styles used in quick actions */
  .btn{ background: linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm)); color: #fff; padding:10px 12px; border-radius:10px; border:none; cursor:pointer; font-weight:700 }
  .btn.secondary{ background: #fff; color: var(--coffee-dark); border:1px solid rgba(0,0,0,0.06) }
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
  <div class="brand" onclick="location.href='dashboard.php'">
    <div class="logo"><i class="fas fa-mug-hot"></i></div>
    <div>
      <h1>CafeKantina</h1>
      <div style="font-size:12px;color:rgba(255,255,255,0.9);margin-top:2px">Admin Dashboard</div>
    </div>
  </div>


  <div class="nav-right">
    <div class="controls">
    </div>
  </div>

    <div class="profile" id="profileRoot">
      <div class="profile-btn" onclick="toggleProfile()">
        <div style="text-align:left">
          <div style="font-weight:700"><?php echo htmlspecialchars($username); ?></div>
          <div style="font-size:12px;color:rgba(255,255,255,0.85)">Admin</div>
        </div>
        <i class="fas fa-caret-down" style="margin-left:8px;color:rgba(255,255,255,0.9)"></i>
      </div>
      <div class="dropdown" id="profileDropdown" aria-hidden="true">
        <a href="#" onclick="showLogoutModal(); return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
</div>

<!-- header with greeting and clock -->
<div class="header" role="banner">
  <div>
    <div class="greeting" id="greeting">Welcome, <?php echo htmlspecialchars($username); ?> — Admin Dashboard</div>
    <div class="small datetime" id="datetime">
      <span id="clock">--:--</span>
      <span>•</span>
      <span id="date">Loading date...</span>
    </div>
  </div>
  <div style="display:flex;gap:12px;align-items:center">
    <div style="text-align:right;color:#6b564d">
    </div>
    <div style="width:12px"></div>
  </div>
</div>

<!-- main content -->
<div class="main">
  <div class="left">
    <div class="cards" aria-live="polite">
      <div class="card" onclick="location.href='report.php'">
        <div class="icon"><i class="fas fa-file-alt"></i></div>
        <div class="meta"><div class="title">Reports</div><div class="sub">View sales reports</div></div>
      </div>
      <div class="card" onclick="location.href='inventory.php'">
        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="meta"><div class="title">Inventory</div><div class="sub">Manage stock & supplies</div></div>
      </div>
      <div class="card" onclick="location.href='forecasting.php'">
        <div class="icon"><i class="fas fa-chart-line"></i></div>
        <div class="meta"><div class="title">Forecast</div><div class="sub">Stock forecasting</div></div>
      </div>
    </div>

    <!-- Quick stat cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:18px">
      <div class="card" id="kpiOrders">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div><div style="font-weight:700;font-size:18px" id="kpiOrdersNum">0</div><div style="font-size:13px;color:#7e5c4a">Orders today</div></div>
          <div style="font-size:28px;color:var(--coffee-mid)"><i class="fas fa-receipt"></i></div>
        </div>
      </div>
      <div class="card" id="kpiItems">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div><div style="font-weight:700;font-size:18px" id="kpiLowStock">0</div><div style="font-size:13px;color:#7e5c4a">Low stock items</div></div>
          <div style="font-size:28px;color:var(--coffee-mid)"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
      </div>
    </div>
  </div>

  <aside class="right">
    <div class="panel">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:700">Live Stats</div>
        <div style="font-size:13px;color:#7e5c4a">Auto-updates</div>
      </div>
      <div style="margin-top:8px;text-align:right"><div id="todaySales" style="font-weight:800">₱0.00</div></div>
      <div style="margin-top:12px" class="stats">
        <div class="stat">
          <div class="num" id="statSales">₱0.00</div>
          <div class="label">Sales (Today)</div>
        </div>
        <div class="stat">
          <div class="num" id="statOrders">0</div>
          <div class="label">Orders</div>
        </div>
        <div class="stat">
          <div class="num" id="statItems">0</div>
          <div class="label">Low Stock</div>
        </div>
      </div>
    </div>



<!-- logout modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);z-index:2000">
  <div style="background:var(--bg-cream);padding:22px;border-radius:12px;box-shadow:var(--shadow);max-width:420px;margin:0 20px;text-align:center">
    <div style="font-size:18px;font-weight:700;color:var(--coffee-dark);margin-bottom:8px">Confirm logout</div>
    <div style="margin-bottom:18px;color:#7e5c4a">Are you sure you want to sign out?</div>
    <div style="display:flex;gap:12px;justify-content:center">
      <button class="btn secondary" onclick="hideLogoutModal()">Cancel</button>
      <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>
  </div>
</div>



<script>
  // small helpers
  const username = <?php echo json_encode($username); ?>;
  const profileDropdown = document.getElementById('profileDropdown');
  function toggleProfile(){ profileDropdown.classList.toggle('show'); profileDropdown.setAttribute('aria-hidden', !profileDropdown.classList.contains('show')); }

  // profile close when clicking outside
  document.addEventListener('click', (e)=>{
    if (!document.getElementById('profileRoot').contains(e.target)) {
      profileDropdown.classList.remove('show');
    }
  });

  // logout modal
  function showLogoutModal(){ document.getElementById('logoutModal').style.display='flex'; }
  function hideLogoutModal(){ document.getElementById('logoutModal').style.display='none'; }

  // clock & greeting
  function updateClock(){
    const now = new Date();
    const time = now.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    const date = now.toLocaleDateString([], {weekday:'long', month:'short', day:'numeric'});
    document.getElementById('clock').textContent = time;
    document.getElementById('date').textContent = date;

    const h = now.getHours();
    let greet = 'Hello';
    if (h < 12) greet = 'Good morning';
    else if (h < 18) greet = 'Good afternoon';
    else greet = 'Good evening';
    document.getElementById('greeting').textContent = `${greet}, ${username} — ready to serve?`;
  }
  updateClock();
  setInterval(updateClock, 1000);

  // Dark mode removed; UI remains in default theme

  // Live stats (AJAX polling)
  // Replace '/api/stats.php' with your real endpoint that returns JSON:
  // { ok:1, sales_today: 1234.56, orders_today: 12, low_stock_count: 3 }
  async function fetchStats(){
    try {
      const res = await fetch('/api/stats.php'); // implement this endpoint to return stats
      if (!res.ok) throw new Error('Network');
      const data = await res.json();
      if (!data.ok) throw new Error(data.msg || 'No data');
      document.getElementById('statSales').textContent = '₱' + Number(data.sales_today || 0).toFixed(2);
      document.getElementById('statOrders').textContent = Number(data.orders_today || 0);
      document.getElementById('statItems').textContent = Number(data.low_stock_count || 0);
      document.getElementById('todaySales').textContent = '₱' + Number(data.sales_today || 0).toFixed(2);
      document.getElementById('kpiOrdersNum').textContent = Number(data.orders_today || 0);
      document.getElementById('kpiLowStock').textContent = Number(data.low_stock_count || 0);
    } catch (err) {
      // fallback / simulated values (if API not implemented)
      // Remove this block once /api/stats.php is available
      const simSales = Math.random()*4000 + 200;
      const simOrders = Math.floor(Math.random()*50) + 2;
      const simLow = Math.floor(Math.random()*8);
      document.getElementById('statSales').textContent = '₱' + simSales.toFixed(2);
      document.getElementById('statOrders').textContent = simOrders;
      document.getElementById('statItems').textContent = simLow;
      document.getElementById('todaySales').textContent = '₱' + simSales.toFixed(2);
      document.getElementById('kpiOrdersNum').textContent = simOrders;
      document.getElementById('kpiLowStock').textContent = simLow;
    }
  }
  fetchStats();
  setInterval(fetchStats, 15000); // refresh every 15s

  // keyboard accessibility: press 'o' to open Orders quickly
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'o' || e.key === 'O') location.href='order.php';
  });

  // small entrance animation for cards
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.card').forEach((el,i)=>{
      el.style.opacity = 0;
      el.style.transform = 'translateY(10px) scale(.99)';
      setTimeout(()=>{ el.style.transition = 'all .45s cubic-bezier(.2,.9,.3,1)'; el.style.opacity = 1; el.style.transform='none'; }, 80 + i*60);
    });
  });
</script>
</body>
</html>
