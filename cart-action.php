<?php
require_once __DIR__ . '/includes/init.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
// Add to Cart from the shop page is sent in the background (no page reload) and
// gets a JSON reply; without JavaScript it falls back to the normal redirect.
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

switch ($action) {
  case 'add':
    $p = db_row("SELECT * FROM products WHERE id = :id", [':id' => $id]);
    $ok = $p && (int) $p['stock'] > 0;
    if ($ok) {
      cart_add([
        'id'     => $p['id'],
        'name'   => $p['name'],
        'price'  => $p['price'],
        'image'  => $p['image'],
        'vendor' => $p['vendor'],
        'unit'   => $p['unit'],
      ]);
    }
    $msg = $ok ? $p['name'] . ' added to cart.' : 'Sorry, that product is out of stock.';
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['ok' => $ok, 'message' => $msg, 'count' => cart_total_items(),
                        'qty' => $ok ? (int) (cart_items()[$id]['quantity'] ?? 0) : 0]);
      exit;
    }
    set_flash($msg, $ok ? 'success' : 'error');
    break;
  case 'increment':
    $items = cart_items();
    if (isset($items[$id])) cart_set_quantity($id, $items[$id]['quantity'] + 1);
    break;
  case 'decrement':
    $items = cart_items();
    if (isset($items[$id])) cart_set_quantity($id, $items[$id]['quantity'] - 1);
    break;
  case 'remove':
    cart_remove($id);
    set_flash('Item removed from cart.', 'error');
    break;
  case 'clear':
    cart_clear();
    set_flash('Cart cleared.', 'error');
    break;
}

// Return to wherever the request came from (default: cart for edits, home for adds).
$back = safe_redirect($action === 'add' ? 'index.php#products' : 'cart.php');
header('Location: ' . $back);
exit;
