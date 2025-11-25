<?php
// order.php
// Single-file Order Terminal with AJAX, MySQLi prepared statements, modal notifications
// Uses only inventory table (inventory.available_in_hand, inventory.unit) for stock management.
// Products store recipe JSON in products.recipe (created automatically if missing).

session_start();

// ---------- CONFIG ----------
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

// make mysqli throw exceptions on errors to help debugging and make transactions safer
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Helper to bind params by reference for statements with dynamic parameter lists (eg. IN lists)
 * $types is the type string (e.g. 'iiis'), $params is an array of variables to bind
 */
function bind_params_by_ref($stmt, $types, array &$params) {
  $refs = [];
  $refs[] = & $types;
  foreach ($params as $i => &$p) $refs[] = & $p;
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}

function json_res($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* Ensure products.recipe JSON column exists (simple migration) */
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'recipe'");
if ($check && $check->num_rows === 0) {
    // Add JSON column (nullable)
    $conn->query("ALTER TABLE products ADD COLUMN recipe JSON NULL");
}

/* ---------- AJAX endpoints ---------- */
$action = $_GET['action'] ?? null;

/* GET PRODUCTS */
if ($action === 'get_products') {
    $cat = $_GET['category'] ?? '';
    if ($cat === '' || $cat === 'All') {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.price, c.name AS category, COALESCE(p.recipe,'[]') AS recipe FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.name, p.name");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.price, c.name AS category, COALESCE(p.recipe,'[]') AS recipe FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.name = ? ORDER BY p.name");
        $stmt->bind_param('s', $cat);
        $stmt->execute();
    }
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        // decode recipe JSON for client convenience
        $r['recipe'] = json_decode($r['recipe'], true) ?: [];
        $out[] = $r;
    }
    $stmt->close();
    json_res(['ok'=>1,'products'=>$out]);
}

  /* GET CATEGORIES */
  if ($action === 'get_categories') {
    $cats = [];
    $res = $conn->query("SELECT DISTINCT c.name AS category FROM categories c JOIN products p ON c.id = p.category_id ORDER BY c.name");
    while ($r = $res->fetch_assoc()) $cats[] = $r['category'];
    $res->close();
    json_res(['ok'=>1,'categories'=>$cats]);
  }

/* ADD TO CART */
if ($action === 'add_to_cart') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $pid = intval($payload['product_id'] ?? 0);
    $qty = max(1,intval($payload['quantity'] ?? 1));
    if (!$pid) json_res(['ok'=>0,'msg'=>'Invalid product.']);

    // get product recipe from DB
    $stmt = $conn->prepare("SELECT id, name, price, COALESCE(recipe,'[]') AS recipe FROM products WHERE id = ?");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    $prod = $res->fetch_assoc();
    $stmt->close();
    if (!$prod) json_res(['ok'=>0,'msg'=>'Product not found.']);

    $recipe = json_decode($prod['recipe'], true) ?: [];
    $shortages = [];
    foreach ($recipe as $r) {
      $iid = intval($r['inventory_id'] ?? 0);
      $amt_per = floatval($r['amount'] ?? 0);
      if ($iid <= 0 || $amt_per <= 0) continue;
      $need = $amt_per * $qty;
      $s = $conn->prepare("SELECT id,name,available_in_hand,unit FROM inventory WHERE id = ?");
      $s->bind_param('i',$iid);
      $s->execute();
      $s->bind_result($inv_id,$inv_name,$inv_avail,$inv_unit);
      $s->fetch();
      $s->close();
      if (!isset($inv_id)) {
        $shortages[] = "Missing inventory item #{$iid}";
        continue;
      }
      if ($inv_avail < $need) {
        $shortages[] = "{$inv_name} (need {$need}{$inv_unit}, have {$inv_avail}{$inv_unit})";
      }
    }
    if (!empty($shortages)) {
      json_res(['ok'=>0,'msg'=>'Not enough stock: '.implode('; ', $shortages)]);
    }
    // add to session cart only if product is valid and no shortages
    $cart = $_SESSION['cart'] ?? [];
    $found = false;
    foreach ($cart as &$c) {
      if ($c['product_id'] == $pid) { $c['quantity'] += $qty; $found = true; break; }
    }
    if (!$found) $cart[] = ['product_id'=>$pid,'quantity'=>$qty];
    $_SESSION['cart'] = $cart;
    json_res(['ok'=>1,'msg'=>'Added to cart.','cart_count'=>count($_SESSION['cart'])]);
}

/* GET CART */
if ($action === 'get_cart') {
    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    $total = 0.0;
    if (!empty($cart)) {
        $ids = array_column($cart,'product_id');
        // Build placeholders and bind dynamically
        $placeholders = implode(',', array_fill(0,count($ids),'?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT id,name,price FROM products WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $params = $ids;
        bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) $map[$r['id']] = $r;
        $stmt->close();
        foreach ($cart as $c) {
            $p = $map[$c['product_id']];
            $line_total = $p['price'] * $c['quantity'];
            $total += $line_total;
            $items[] = ['product_id'=>$c['product_id'],'name'=>$p['name'],'qty'=>$c['quantity'],'price'=>$p['price'],'line_total'=>$line_total];
        }
    }
    json_res(['ok'=>1,'items'=>$items,'total'=>$total]);
}

/* UPDATE CART */
if ($action === 'update_cart') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $prod = intval($payload['product_id'] ?? 0);
    $qty = intval($payload['quantity'] ?? 0);
    $cart = $_SESSION['cart'] ?? [];
    foreach ($cart as $k=>$c) {
        if ($c['product_id'] == $prod) {
            if ($qty <= 0) unset($cart[$k]);
            else $cart[$k]['quantity'] = max(1,$qty);
            break;
        }
    }
    $_SESSION['cart'] = array_values($cart);
    json_res(['ok'=>1,'msg'=>'Cart updated.']);
}

/* CLEAR CART */
if ($action === 'clear_cart') {
    $_SESSION['cart'] = [];
    json_res(['ok'=>1,'msg'=>'Cart cleared.']);
}

/* CHECKOUT */
if ($action === 'checkout') {
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) json_res(['ok'=>0,'msg'=>'Cart is empty.']);

    $conn->begin_transaction();
    try {
        $total = 0;
        // build price map
        $prod_ids = array_column($cart,'product_id');
        $placeholders = implode(',', array_fill(0,count($prod_ids),'?'));
        $types = str_repeat('i', count($prod_ids));
        $stmt = $conn->prepare("SELECT id, price, COALESCE(recipe,'[]') AS recipe FROM products WHERE id IN ($placeholders) FOR UPDATE");
        $params = $prod_ids;
        bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $price_map = [];
        $recipe_map = []; // product_id => recipe array
        while ($r = $res->fetch_assoc()) { $price_map[$r['id']] = $r['price']; $recipe_map[$r['id']] = json_decode($r['recipe'], true) ?: []; }
        $stmt->close();

        // calculate total and required inventory across cart
        $need_inv = []; // inv_id => total_needed
        foreach ($cart as $c) {
            $pid = $c['product_id']; $qty = $c['quantity'];
            $total += ($price_map[$pid] ?? 0) * $qty;
            $recipe = $recipe_map[$pid] ?? [];
            foreach ($recipe as $r) {
                $iid = intval($r['inventory_id'] ?? 0);
                $amt = floatval($r['amount'] ?? 0);
                if ($iid <= 0 || $amt <= 0) continue;
                $need_inv[$iid] = ($need_inv[$iid] ?? 0) + ($amt * $qty);
            }
        }

        // lock and check inventory
        foreach ($need_inv as $iid => $needed) {
            $stmt = $conn->prepare("SELECT id,name,available_in_hand,unit FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i',$iid); $stmt->execute(); $stmt->bind_result($id,$name,$avail,$unit); $stmt->fetch(); $stmt->close();
            if (!isset($id)) throw new Exception("Inventory item {$iid} not found.");
            if ($avail < $needed) throw new Exception("Not enough {$name} ({$avail}{$unit} available, need {$needed}{$unit}).");
        }

        // create order
        // Determine correct user/staff ID for foreign key
        $session_user_id = $_SESSION['user_id'] ?? null;
        $session_username = $_SESSION['username'] ?? null;
        if (!$session_user_id && !$session_username) throw new Exception('No user is logged in.');

        // Check which column exists: staff_id or user_id in orders table
        $orderCol = 'user_id';
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'staff_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
          $orderCol = 'staff_id';
        }

        // If orders use staff_id (FK to staff table), try to find the matching staff.id
        if ($orderCol === 'staff_id') {
          $staff_id = null;
          // If session user id is numeric, check if same id exists in staff table
          if ($session_user_id !== null && is_numeric($session_user_id)) {
            $tmp = (int)$session_user_id;
            $s = $conn->prepare("SELECT id FROM staff WHERE id = ? LIMIT 1");
            $s->bind_param('i', $tmp);
            $s->execute(); $s->store_result();
            if ($s->num_rows > 0) $staff_id = $tmp;
            $s->close();
          }
          // If still null, try to find staff by username
          if ($staff_id === null && $session_username) {
            $s = $conn->prepare("SELECT id FROM staff WHERE username = ? LIMIT 1");
            $s->bind_param('s', $session_username);
            $s->execute(); $s->bind_result($found_staff_id); $s->fetch(); $s->close();
            if (!empty($found_staff_id)) $staff_id = (int)$found_staff_id;
          }
          // If we did not find a staff entry, insert without staff_id (NULL) — orders.staff_id is nullable so this is safe
          if ($staff_id === null) {
            $stmt = $conn->prepare("INSERT INTO orders (total_amount) VALUES (?)");
            $stmt->bind_param('d', $total);
          } else {
            $stmt = $conn->prepare("INSERT INTO orders (total_amount, staff_id) VALUES (?, ?)");
            $stmt->bind_param('di', $total, $staff_id);
          }
        } else {
          // orders.user_id referencing users table — ensure numeric ID
          $user_id = null;
          if ($session_user_id !== null && is_numeric($session_user_id)) {
            $user_id = (int)$session_user_id;
          } else {
            // Attempt to resolve by username
            if ($session_username) {
              $s = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
              $s->bind_param('s', $session_username);
              $s->execute(); $s->bind_result($found_uid); $s->fetch(); $s->close();
              if (!empty($found_uid)) $user_id = (int)$found_uid;
            }
          }
          // If still null, fallback to 1 if it exists — else leave NULL and insert without user reference
          if ($user_id === null) {
            $s = $conn->prepare("SELECT id FROM users LIMIT 1");
            $s->execute(); $s->store_result();
            if ($s->num_rows > 0) {
              $s->close();
              // default to first user (could be admin)
              $r = $conn->query("SELECT id FROM users LIMIT 1")->fetch_assoc();
              $user_id = (int)$r['id'];
            } else {
              $s->close();
            }
          }
          if ($user_id !== null) {
            $stmt = $conn->prepare("INSERT INTO orders (total_amount, user_id) VALUES (?, ?)");
            $stmt->bind_param('di', $total, $user_id);
          } else {
            $stmt = $conn->prepare("INSERT INTO orders (total_amount) VALUES (?)");
            $stmt->bind_param('d', $total);
          }
        }
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // insert order_items and deduct inventory
        foreach ($cart as $c) {
            $pid = $c['product_id']; $qty = $c['quantity'];
            $price = $price_map[$pid] ?? 0.0;
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiid', $order_id, $pid, $qty, $price);
            $stmt->execute(); $stmt->close();

            // deduct inventory per recipe
            $recipe = $recipe_map[$pid] ?? [];
            foreach ($recipe as $r) {
                $iid = intval($r['inventory_id'] ?? 0);
                $amt = floatval($r['amount'] ?? 0);
                if ($iid <= 0 || $amt <= 0) continue;
                $need = $amt * $qty;
                // get current
                $s = $conn->prepare("SELECT opening_count, available_in_hand FROM inventory WHERE id = ? FOR UPDATE");
                $s->bind_param('i', $iid);
                $s->execute();
                $s->bind_result($opening, $avail);
                $s->fetch();
                $s->close();
                $new_avail = max(0, $avail - $need);
                $closing = $new_avail;
                $no_utilized = max(0, $opening - $closing);
                if ($closing <= 0) $status = 'Out of Stock';
                else if ($opening > 0 && $closing < ($opening * 0.2)) $status = 'Running Low';
                else $status = 'Available';
                $u = $conn->prepare("UPDATE inventory SET available_in_hand=?, closing_count=?, no_utilized=?, status=?, updated_at=NOW() WHERE id=?");
                $u->bind_param('iiisi', $new_avail, $closing, $no_utilized, $status, $iid);
                $u->execute();
                $u->close();
            }
        }

        $conn->commit();
        $_SESSION['last_order'] = ['order_id'=>$order_id,'items'=>$cart,'total'=>$total];
        $_SESSION['cart'] = [];
        // Trigger live update for cash sales in cash_drawer.php
        // Remove localStorage update from PHP, handle in JS after AJAX success
        json_res(['ok'=>1,'msg'=>'Order placed successfully.','order_id'=>$order_id]);
    } catch (Exception $e) {
        $conn->rollback();
        json_res(['ok'=>0,'msg'=>'Checkout failed: '.$e->getMessage()]);
    }
}

/* DELETE PRODUCT */
if ($action === 'delete_product') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $pid = intval($payload['product_id'] ?? 0);
    if ($pid <= 0) json_res(['ok'=>0,'msg'=>'Invalid product ID']);
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param('i',$pid);
        $stmt->execute();
        $stmt->close();
        json_res(['ok'=>1,'msg'=>'Product deleted successfully']);
    } catch (Exception $e) {
        json_res(['ok'=>0,'msg'=>'Failed to delete: '.$e->getMessage()]);
    }
}

/* SERVER ADD PRODUCT (store recipe JSON) */
if ($action === 'server_add_product' && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $payload = json_decode(file_get_contents('php://input'), true);
    $name = trim($payload['name'] ?? '');
    $category = trim($payload['category'] ?? '');
    $price = floatval($payload['price'] ?? 0.0);
    $ingredients = $payload['ingredients'] ?? []; // array of inventory ids
    $amounts_ing = $payload['amounts_ing'] ?? [];
    $supplies = $payload['supplies'] ?? [];
    $amounts_sup = $payload['amounts_sup'] ?? [];

    if (!$name) json_res(['ok'=>0,'msg'=>'Product name required.']);
    // build recipe array from inputs (mix ingredients + supplies since both come from inventory table)
    $recipe = [];
    // ingredients
    for ($i=0;$i<count($ingredients);$i++){
        $iid = intval($ingredients[$i]);
        $amt = floatval($amounts_ing[$i] ?? 0);
        if ($iid > 0 && $amt > 0) $recipe[] = ['inventory_id'=>$iid,'amount'=>$amt];
    }
    // supplies
    for ($i=0;$i<count($supplies);$i++){
        $iid = intval($supplies[$i]);
        $amt = floatval($amounts_sup[$i] ?? 0);
        if ($iid > 0 && $amt > 0) $recipe[] = ['inventory_id'=>$iid,'amount'=>$amt];
    }

    try {
      // create product and deduct inventory atomically
      $conn->begin_transaction();

      // check stock for each recipe item first (lock rows)
      $shortages = [];
      foreach ($recipe as $r) {
        $iid = intval($r['inventory_id'] ?? 0);
        $amt = floatval($r['amount'] ?? 0);
        if ($iid <= 0 || $amt <= 0) continue;
        $s = $conn->prepare("SELECT id,name,available_in_hand,opening_count,unit FROM inventory WHERE id = ? FOR UPDATE");
        $s->bind_param('i', $iid);
        $s->execute();
        $s->bind_result($inv_id, $inv_name, $inv_avail, $inv_opening, $inv_unit);
        $s->fetch();
        $s->close();
        if (!isset($inv_id)) {
          $shortages[] = "Missing inventory item #{$iid}";
          continue;
        }
        if ($inv_avail < $amt) {
          $shortages[] = "{$inv_name} (need {$amt}{$inv_unit}, have {$inv_avail}{$inv_unit})";
        }
      }

      if (!empty($shortages)) {
        $conn->rollback();
        json_res(['ok'=>0,'msg'=>'Not enough stock for product creation: '.implode('; ', $shortages)]);
      }

      // resolve category name → category_id (create category if missing)
      $category_id = null;
      if ($category !== '') {
        $sc = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $sc->bind_param('s', $category);
        $sc->execute();
        $cres = $sc->get_result();
        $crow = $cres->fetch_assoc();
        $sc->close();
        if ($crow && isset($crow['id'])) {
          $category_id = (int)$crow['id'];
        } else {
          $insc = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
          $insc->bind_param('s', $category);
          if (!$insc->execute()) {
            $err = $insc->error;
            $insc->close();
            $conn->rollback();
            json_res(['ok'=>0,'msg'=>'Create category failed: '.$err]);
          }
          $category_id = $insc->insert_id;
          $insc->close();
        }
      }

      // insert product (use category_id if available)
      $json_recipe = json_encode($recipe);
      if ($category_id) {
        $stmt = $conn->prepare("INSERT INTO products (name, price, category_id, recipe) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sdis', $name, $price, $category_id, $json_recipe);
      } else {
        $stmt = $conn->prepare("INSERT INTO products (name, price, recipe) VALUES (?, ?, ?)");
        $stmt->bind_param('sds', $name, $price, $json_recipe);
      }
      if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $conn->rollback();
        json_res(['ok'=>0,'msg'=>'Create failed: '.$err]);
      }
      $prod_id = $stmt->insert_id;
      $stmt->close();

      // deduct inventory now that product is created
      foreach ($recipe as $r) {
        $iid = intval($r['inventory_id'] ?? 0);
        $amt = floatval($r['amount'] ?? 0);
        if ($iid <= 0 || $amt <= 0) continue;
        // get current counts (already locked above during availability check)
        $s = $conn->prepare("SELECT opening_count, available_in_hand FROM inventory WHERE id = ? FOR UPDATE");
        $s->bind_param('i', $iid);
        $s->execute();
        $s->bind_result($opening, $avail);
        $s->fetch();
        $s->close();
        $new_avail = max(0, $avail - $amt);
        $closing = $new_avail;
        $no_utilized = max(0, $opening - $closing);
        if ($closing <= 0) $status = 'Out of Stock';
        else if ($opening > 0 && $closing < ($opening * 0.2)) $status = 'Running Low';
        else $status = 'Available';
                $u = $conn->prepare("UPDATE inventory SET available_in_hand=?, closing_count=?, no_utilized=?, status=?, updated_at=NOW() WHERE id=?");
        $u->bind_param('iiisi', $new_avail, $closing, $no_utilized, $status, $iid);
        $u->execute();
        $u->close();
      }

      $conn->commit();
      json_res(['ok'=>1,'msg'=>'Product created and inventory updated.','id'=>$prod_id]);
    } catch (Exception $e) {
      // rollback any active transaction
      try { $conn->rollback(); } catch (Exception $_) {}
      json_res(['ok'=>0,'msg'=>'Server error: '.$e->getMessage()]);
    }
}

/* ---------- PAGE render ---------- */
// categories
$categories = [];
$res = $conn->query("SELECT DISTINCT c.name FROM categories c JOIN products p ON c.id = p.category_id ORDER BY c.name");
while ($r = $res->fetch_assoc()) $categories[] = $r['name'];
$res->close();

// fetch inventory for product modal (use inventory table)
$inventory = [];
$res = $conn->query("SELECT id,name,unit,available_in_hand FROM inventory ORDER BY name");
while ($row = $res->fetch_assoc()) $inventory[] = $row;
$res->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CafeKantina — Order Terminal</title>
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --coffee-dark:#3e2f2a;
  --coffee-mid:#6d4c41;
  --coffee-warm:#a98274;
  --cream:#fff8f3;
  --accent:#c88f62;
  --muted:#f3e9e2;
}
*{box-sizing:border-box}
body{font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial; margin:0; background:linear-gradient(180deg,var(--cream),#fff); color:var(--coffee-dark); min-height:100vh;}
.navbar{position:fixed;top:0;left:0;right:0;height:64px;background:linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));display:flex;align-items:center;justify-content:center;color:var(--cream);box-shadow:0 3px 10px rgba(0,0,0,0.08);z-index:100}
.navbar h1{margin:0;font-size:20px;letter-spacing:1px}
.page{max-width:1200px;margin:90px auto;padding:18px}
.grid{display:grid;grid-template-columns:220px 1fr 360px;gap:20px;align-items:start}
@media(max-width:1000px){.grid{grid-template-columns:1fr;}}
.panel{background:#fff;padding:18px;border-radius:12px;box-shadow:0 8px 30px rgba(93,64,55,0.06)}
.category-list button{display:block;width:100%;text-align:left;padding:10px 12px;border-radius:8px;border:1px solid #f0e6e3;background:#fff;margin-bottom:8px;cursor:pointer}
.category-list button.active{background:linear-gradient(90deg,var(--muted),#fff);box-shadow:inset 0 1px 0 rgba(255,255,255,0.5)}
.products{display:flex;flex-wrap:wrap;gap:12px}
.tile{flex:0 1 calc(33.333% - 12px);min-width:160px;background:linear-gradient(180deg,#fff, #fff8f3);padding:12px;border-radius:10px;border:1px solid #f0e6e3;cursor:pointer;box-shadow:0 6px 18px rgba(93,64,55,0.06);display:flex;flex-direction:column;justify-content:space-between}
.tile .title{font-weight:600;margin-bottom:8px}
.tile .price{color:var(--coffee-warm);font-weight:700}
@media(max-width:900px){.tile{flex:0 1 calc(50% - 12px)}}
@media(max-width:600px){.tile{flex:0 1 100%}}
.cart{position:sticky;top:90px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #f3e9e2;text-align:left}
.table th{background:var(--muted);font-weight:700}
.btn{background:linear-gradient(90deg,var(--coffee-mid),var(--coffee-warm));color:var(--cream);padding:10px 14px;border:none;border-radius:10px;cursor:pointer;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
.btn.secondary{background:#fff;border:1px solid #f0e6e3;color:var(--coffee-dark)}
.small{font-size:13px;color:#666}
.total{font-weight:800;color:var(--coffee-dark)}
.notice{padding:12px;border-radius:8px;margin-bottom:12px}
.notice.success{background:#eaf7ea;color:#1b6e36}
.notice.error{background:#fee9e6;color:#8b1b1b}
.modal-back{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;z-index:9999}
.modal{background:#fff;border-radius:12px;padding:18px;width:720px;max-width:94%;box-shadow:0 20px 60px rgba(0,0,0,0.25)}
.modal h3{margin-top:0}
.modal .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
.close-x{position:absolute;right:16px;top:12px;cursor:pointer;color:#999;font-weight:700}
.form-row{display:flex;gap:8px;align-items:center;margin-bottom:10px}
.form-row label{min-width:120px;color:#444}
.select-small{padding:8px;border:1px solid #eee;border-radius:8px}
.badge-stock{font-size:12px;color:#555;background:#f7f7f7;padding:4px 8px;border-radius:8px;border:1px solid #eee;margin-left:8px}
input[type="text"],input[type="number"],select{padding:8px;border-radius:8px;border:1px solid #eee;background:#fff;width:100%}
.recipe-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.recipe-row .info{flex:1}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
</head>
<body>
<div class="navbar"><h1>CafeKantina</h1></div>

<div class="page">
  <div class="grid">
    <!-- LEFT: categories -->
    <aside class="panel category-list" id="categoriesPanel">
      <h3 style="margin-top:0">Categories</h3>
      <button data-cat="All" class="active">All</button>
      <?php foreach ($categories as $c): ?>
        <button data-cat="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></button>
      <?php endforeach; ?>
      <div style="margin-top:12px;">
        <button class="btn secondary" id="openNewProduct">+ Create product</button>
      </div>
    </aside>

    <!-- CENTER: products -->
    <main class="panel">
      <h2 style="margin-top:0">Products</h2>
      <div class="small" style="margin-bottom:10px">Tap a product to add to cart</div>
      <div id="products" class="products"></div>
    </main>

    <!-- RIGHT: cart -->
    <aside class="panel cart" style="min-height:180px">
      <h3 style="margin-top:0">Cart</h3>
      <div id="cartContent"><div class="small">No items yet</div></div>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn" id="openCheckout">Checkout</button>
        <button class="btn secondary" id="clearCartBtn">Clear Cart</button>
      </div>
     
    </aside>
  </div>
</div>

<!-- MODALS -->
<div class="modal-back" id="modalBack"><div class="modal" role="dialog" aria-modal="true"><div style="position:relative"><span class="close-x" id="modalClose">&times;</span><div id="modalBody"></div></div></div></div>

<!-- Create product template -->
<div id="newProductTemplate" style="display:none">
  <h3>Create Product</h3>
  <div class="form-row"><label>Product Name</label><input type="text" id="np_name" placeholder="e.g. Iced Latte"></div>
  <div class="form-row"><label>Category</label><input type="text" id="np_category" placeholder="e.g. Coffee"></div>
  <div class="form-row"><label>Price</label><input type="number" id="np_price" min="0" step="0.01" value="0.00"></div>

  <div style="margin-top:10px;font-weight:600">Select inventory items (set amount required)</div>
  <div id="np_inventory" style="max-height:260px;overflow:auto;padding:8px;border:1px solid #f3e9e2;border-radius:8px;margin-top:8px">
    <?php foreach($inventory as $inv): ?>
      <div class="recipe-row" data-inv-id="<?= (int)$inv['id'] ?>">
        <input type="checkbox" class="np_inv_cb" value="<?= (int)$inv['id'] ?>">
        <div class="info">
          <strong><?=htmlspecialchars($inv['name'])?></strong>
          <div class="small"><?= (int)$inv['available_in_hand'] ?> <?=htmlspecialchars($inv['unit'])?> available</div>
        </div>
        <input type="number" class="np_inv_amt" min="0" placeholder="amount" style="width:110px" data-unit="<?=htmlspecialchars($inv['unit'])?>">
      </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
    <button class="btn secondary" id="cancelNewProduct">Cancel</button>
    <button class="btn" id="saveNewProduct">Save product</button>
  </div>
</div>

<!-- Checkout template -->
<div id="checkoutTemplate" style="display:none">
  <h3>Confirm Order</h3>
  <div id="checkoutItems" style="max-height:260px;overflow:auto;margin-top:8px"></div>
  <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center">
    <div class="small">Total</div>
    <div class="total" id="checkoutTotal">₱0.00</div>
  </div>
  <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end">
    <button class="btn secondary" id="cancelCheckout">Cancel</button>
    <button class="btn" id="confirmCheckout">Confirm Order</button>
  </div>
</div>

<script>
async function postJSON(url,data){ const r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}); return r.json(); }
async function getJSON(url){ const r = await fetch(url); return r.json(); }
function escapeHtml(s){ if (s==null) return ''; return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

const productContainer = document.getElementById('products');
const modalBack = document.getElementById('modalBack');
const modalBody = document.getElementById('modalBody');
const modalClose = document.getElementById('modalClose');
let currentCategory = 'All';

// refresh categories panel from server and rebuild buttons
async function refreshCategories(selected='All'){
  const data = await getJSON('order.php?action=get_categories');
  if (!data.ok) return;
  const panel = document.getElementById('categoriesPanel');
  // build buttons: All + each category
  let html = '<h3 style="margin-top:0">Categories</h3>';
  html += '<button data-cat="All">All</button>';
  data.categories.forEach(c=>{ html += `<button data-cat="${escapeHtml(c)}">${escapeHtml(c)}</button>`; });
  html += '<div style="margin-top:12px;"><button class="btn secondary" id="openNewProduct">+ Create product</button></div>';
  panel.innerHTML = html;
  // attach handlers
  panel.querySelectorAll('button[data-cat]').forEach(b=>{
    b.addEventListener('click', ()=> {
      panel.querySelectorAll('button[data-cat]').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      const cat = b.getAttribute('data-cat');
      currentCategory = cat;
      loadProducts(cat);
    });
  });
  // reattach new product button
    const openBtn = panel.querySelector('#openNewProduct');
    if (openBtn) openBtn.addEventListener('click', openNewProductModal);
  // set active
  const sel = panel.querySelector(`button[data-cat="${selected}"]`);
  if (sel) { panel.querySelectorAll('button[data-cat]').forEach(x=>x.classList.remove('active')); sel.classList.add('active'); }
}

async function loadProducts(cat='All'){
  currentCategory = cat;
  const data = await getJSON('order.php?action=get_products&category='+encodeURIComponent(cat));
  if (!data.ok) return;
  productContainer.innerHTML = '';
  data.products.forEach(p=>{
    const tile = document.createElement('div');
    tile.className = 'tile';
    tile.innerHTML = `<div><div class="title">${escapeHtml(p.name)}</div><div class="small">${escapeHtml(p.category)}</div></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
      <div class="price">₱${Number(p.price).toFixed(2)}</div>
      <div style="display:flex;gap:6px">
        <button class="btn btn-add">Add</button>
        <button class="btn secondary btn-del">Del</button>
      </div>
    </div>`;
    const addBtn = tile.querySelector('.btn-add');
    const delBtn = tile.querySelector('.btn-del');
    addBtn.addEventListener('click', ()=> addToCart(p.id,1));
    delBtn.addEventListener('click', ()=> deleteProduct(p.id, p.name));
    productContainer.appendChild(tile);
  });
}

async function refreshCart(){
  const data = await getJSON('order.php?action=get_cart');
  if (!data.ok) return;
  const cartContent = document.getElementById('cartContent');
  const checkoutBtn = document.getElementById('openCheckout');
  if (data.items.length===0) {
    cartContent.innerHTML = '<div class="small">No items yet</div>';
    if (checkoutBtn) {
      checkoutBtn.disabled = true;
      checkoutBtn.textContent = 'Checkout';
      checkoutBtn.innerHTML = 'Checkout';
    }
    return;
  }
  if (checkoutBtn) {
    checkoutBtn.disabled = false;
    checkoutBtn.textContent = 'Checkout';
    checkoutBtn.innerHTML = 'Checkout';
  }
  let html = '<table class="table"><thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead><tbody>';
  data.items.forEach(it=>{
    html += `<tr><td>${escapeHtml(it.name)}</td><td><input type="number" min="1" value="${it.qty}" style="width:72px" onchange="updateQty(${it.product_id}, this.value)"></td><td>₱${Number(it.line_total).toFixed(2)}</td></tr>`;
  });
  html += `</tbody><tfoot><tr class="total-row"><td colspan="2">Total</td><td>₱${Number(data.total).toFixed(2)}</td></tr></tfoot></table>`;
  cartContent.innerHTML = html;
}

async function addToCart(product_id, qty){
  const addBtns = document.querySelectorAll('.btn-add');
  addBtns.forEach(b=>b.disabled=true);
  const res = await postJSON('order.php?action=add_to_cart',{product_id,quantity:qty});
  addBtns.forEach(b=>b.disabled=false);
  if (!res.ok) return showModal('Error', `<div class="notice error">${escapeHtml(res.msg)}</div>`);
  refreshCart();
  showModal('Added', `<div class="notice success">${escapeHtml(res.msg)}</div>`, ()=>{ loadProducts(currentCategory); refreshCart(); });
}

async function updateQty(product_id, qty){
  const res = await postJSON('order.php?action=update_cart',{product_id,quantity:parseInt(qty)});
  if (!res.ok) return showModal('Error', `<div class="notice error">${escapeHtml(res.msg)}</div>`);
  refreshCart();
}

document.getElementById('clearCartBtn').addEventListener('click', async ()=>{
  const res = await postJSON('order.php?action=clear_cart', {});
  if (res.ok) refreshCart();
});

document.getElementById('openCheckout').addEventListener('click', async ()=>{
  const checkoutBtn = document.getElementById('openCheckout');
  checkoutBtn.disabled = true;
  checkoutBtn.innerHTML = 'Complete';
  const data = await getJSON('order.php?action=get_cart');
  if (!data.ok || data.items.length===0) {
    checkoutBtn.disabled = false;
    checkoutBtn.textContent = 'Checkout';
    return showModal('Cart empty', '<div class="notice error">Please add items to cart before checkout.</div>');
  }
  let out = '<table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left">Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Line</th></tr></thead><tbody>';
  data.items.forEach(it => { out += `<tr><td>${escapeHtml(it.name)}</td><td style="text-align:right">${it.qty}</td><td style="text-align:right">₱${Number(it.line_total).toFixed(2)}</td></tr>`; });
  out += `</tbody></table><div style="margin-top:12px;display:flex;justify-content:space-between"><div class="small">Total</div><div class="total">₱${Number(data.total).toFixed(2)}</div></div>`;
  showModal('Confirm Order', out, async ()=>{
    const res = await postJSON('order.php?action=checkout', {});
    if (!res.ok) {
      checkoutBtn.innerHTML = '<span style="color:#a94442;font-weight:bold">Failed</span>';
      setTimeout(()=>{ checkoutBtn.disabled = false; checkoutBtn.textContent = 'Checkout'; }, 1200);
      return showModal('Error', `<div class="notice error">${escapeHtml(res.msg)}</div>`);
    }
    // Show 'Complete' immediately, then reset after 1.5s
    checkoutBtn.innerHTML = '<span style="color:#3c763d;font-weight:bold;font-size:1.2em">&#10004;</span> Complete';
    setTimeout(()=>{ checkoutBtn.disabled = false; checkoutBtn.textContent = 'Checkout'; }, 1500);
    // Use the latest sale amount from the server (fetch it for accuracy)
    fetch('cash_drawer.php?action=get_cash_sales').then(r=>r.json()).then(data=>{
      if(data.ok) {
        localStorage.setItem('lastCashSale', data.total_sales);
      }
      localStorage.setItem('refreshCashDrawer', Date.now());
    });
    let saleText = '';
    showModal('Order Placed', `<div class="notice success"><span style="font-size:2em;color:#3c763d">&#10004;</span><br>Order #${res.order_id} placed successfully.<br>${saleText}</div>`, async ()=>{
      // Clear cart after order is complete
      await postJSON('order.php?action=clear_cart', {});
      refreshCart();
      loadProducts(currentCategory);
      window.scrollTo({top:0,behavior:'smooth'});
    });
  }, true);
});

// modal helper
function showModal(title, htmlContent, confirmCallback=null, showConfirm=true){
  modalBody.innerHTML = `<h3>${escapeHtml(title)}</h3><div>${htmlContent}</div>`;
  modalBack.style.display = 'flex';
  const oldActions = modalBody.querySelector('.actions'); if (oldActions) oldActions.remove();
  const actions = document.createElement('div'); actions.className='actions';
  if (showConfirm && confirmCallback) {
    const cancel = document.createElement('button'); cancel.className='btn secondary'; cancel.textContent='Cancel'; cancel.setAttribute('aria-label','Cancel');
    const confirm = document.createElement('button'); confirm.className='btn'; confirm.textContent='Confirm'; confirm.setAttribute('aria-label','Confirm');
    actions.appendChild(cancel); actions.appendChild(confirm);
    modalBody.appendChild(actions);
    cancel.addEventListener('click', ()=> modalBack.style.display='none');
    confirm.addEventListener('click', ()=> { modalBack.style.display='none'; confirmCallback(); });
    confirm.focus();
  } else {
    const ok = document.createElement('button'); ok.className='btn'; ok.textContent='OK'; ok.setAttribute('aria-label','OK');
    actions.appendChild(ok); modalBody.appendChild(actions);
    ok.addEventListener('click', ()=>{ modalBack.style.display='none'; if (confirmCallback) confirmCallback(); });
    ok.focus();
  }
  // Add ARIA role for modal
  modalBack.setAttribute('role','dialog');
  modalBack.setAttribute('aria-modal','true');
  // Improve notification clarity
  setTimeout(()=>{
    const notices = modalBody.querySelectorAll('.notice');
    notices.forEach(n=>{
      if(n.classList.contains('error')) n.style.background='#ffeaea',n.style.color='#a94442';
      if(n.classList.contains('success')) n.style.background='#eaffea',n.style.color='#3c763d';
    });
  },10);
}
modalClose.addEventListener('click', ()=> modalBack.style.display='none');
modalBack.addEventListener('click', (e)=> { if (e.target === modalBack) modalBack.style.display='none'; });

// categories
// Initial category button handlers are now set in refreshCategories

// new product modal
function openNewProductModal(){
  const tpl = document.getElementById('newProductTemplate').innerHTML;
  showModal('New product', tpl, null, false);
  // Use modalBody for event attachment (since template is injected there)
  const cancelBtn = modalBody.querySelector('#cancelNewProduct');
  if (cancelBtn) cancelBtn.addEventListener('click', ()=> modalBack.style.display='none');
  const saveBtn = modalBody.querySelector('#saveNewProduct');
  if (saveBtn) saveBtn.addEventListener('click', async ()=>{
    const name = modalBody.querySelector('#np_name').value.trim();
    const category = modalBody.querySelector('#np_category').value.trim();
    const price = parseFloat(modalBody.querySelector('#np_price').value) || 0;
    const inv_cbs = Array.from(modalBody.querySelectorAll('.np_inv_cb'));
    const inv_amts = Array.from(modalBody.querySelectorAll('.np_inv_amt'));
    const inv_ids = [], inv_vals = [];
    inv_cbs.forEach((cb,i)=>{ if (cb.checked){ inv_ids.push(cb.value); inv_vals.push(inv_amts[i].value || 0); }});
    if (!name || !category) return showModal('Validation', '<div class="notice error">Please fill product name and category.</div>');
    const payload = {name, category, price, ingredients: inv_ids, amounts_ing: inv_vals, supplies: [], amounts_sup: []};
    const res = await postJSON('order.php?action=server_add_product', payload);
    if (!res.ok) return showModal('Error', `<div class="notice error">${escapeHtml(res.msg)}</div>`);
    modalBack.style.display = 'none';
    showModal('Success', `<div class="notice success">${escapeHtml(res.msg)}</div>`, async ()=>{
      // refresh categories and products so new product appears without a full page reload
      await refreshCategories(category || 'All');
      loadProducts(currentCategory);
    });
  });
}

async function deleteProduct(pid, name){
  showModal('Delete Product', `<div class="notice error">Are you sure you want to delete <strong>${escapeHtml(name)}</strong>? This cannot be undone.</div>`, async ()=>{
    const res = await postJSON('order.php?action=delete_product', {product_id: pid});
    if (!res.ok) return showModal('Error', `<div class="notice error">${escapeHtml(res.msg)}</div>`);
    showModal('Deleted', `<div class="notice success">${escapeHtml(res.msg)}</div>`, ()=> loadProducts(currentCategory));
  });
}

// init
refreshCategories('All').then(()=> loadProducts('All'));
refreshCart();
</script>
</body>
</html>
