<?php
// Central action dispatcher. Every mutating button posts here with a `do` field;
// we mutate the session store, set a flash message, then redirect back (PRG).
require_once __DIR__ . '/includes/init.php';

$do = $_POST['do'] ?? $_GET['do'] ?? '';
$user = current_user();

switch ($do) {

  /* ------------------------------- customer ------------------------------- */
  case 'checkout':
    if (!$user) {
      set_flash('Please sign in to place your order.', 'error');
      header('Location: login.php?role=customer');
      exit;
    }
    if (!profile_complete($user)) {
      set_flash('Please add your delivery address before checking out.', 'error');
      header('Location: profile.php');
      exit;
    }
    $allowed = ['Cash on Delivery', 'UPI', 'Card', 'Net Banking', 'Wallet'];
    $payment = in_array($_POST['payment'] ?? '', $allowed, true) ? $_POST['payment'] : 'Cash on Delivery';

    $deliveryOption = trim((string) ($_POST['delivery_option'] ?? ''));
    if (!in_array($deliveryOption, ['Self Pickup', 'Home Delivery'], true)) {
      $deliveryOption = 'Self Pickup';
    }

    // Home Delivery must name a real, active delivery partner.
    $partner = null;
    if ($deliveryOption === 'Home Delivery') {
      $pid = (int) ($_POST['delivery_partner_id'] ?? 0);
      $partner = $pid ? db_row("SELECT * FROM delivery_partners WHERE id = :id", [':id' => $pid]) : null;
      if (!$partner) {
        set_flash('Please choose a delivery partner for Home Delivery.', 'error');
        header('Location: checkout.php');
        exit;
      }
    }

    // Apply a selected offer (re-validated here — never trust the amount from the form).
    $subtotal = cart_total_price();
    $discount = 0; $offerCode = '';
    $offerId  = (int) ($_POST['offer_id'] ?? 0);
    if ($offerId) {
      $offer     = offer_find($offerId);
      $isActive  = $offer && in_array((int) $offer['id'], array_map('intval', array_column(active_offers(), 'id')), true);
      $amount    = ($offer && $isActive) ? offer_discount_amount($offer, $subtotal) : 0;
      if ($amount > 0) {
        $discount  = $amount;
        $offerCode = trim($offer['code'] ?? '') !== '' ? $offer['code'] : ($offer['title'] ?? '');
      } elseif ($offer) {
        set_flash('That offer can\'t be applied to this order. Please review your cart.', 'error');
        header('Location: checkout.php');
        exit;
      }
    }

    $num = place_order($user, $payment, $deliveryOption, $discount, $offerCode, $partner);
    if ($num) {
      $savedMsg = $discount > 0 ? " You saved ₹$discount." : '';
      set_flash("Order $num confirmed! Payment: $payment. $deliveryOption.$savedMsg You can track it here.", 'success');
      header('Location: orders.php');
    } else {
      set_flash('Your cart is empty.', 'error');
      header('Location: cart.php');
    }
    exit;

  case 'order_cancel':
    if (!$user || ($user['role'] ?? '') !== 'customer') { header('Location: login.php'); exit; }
    $ok = order_cancel($_POST['id'] ?? '', $user['email'] ?? '');
    set_flash($ok ? ('Order ' . ($_POST['id'] ?? '') . ' cancelled.') : 'This order can no longer be cancelled.', 'error');
    header('Location: orders.php');
    exit;

  case 'return_request':
    if (!$user || ($user['role'] ?? '') !== 'customer') { header('Location: login.php'); exit; }
    $orderId = $_POST['id'] ?? '';
    $order = db_row("SELECT * FROM orders WHERE id = :id", [':id' => $orderId]);
    // The order must exist, belong to this customer, and be delivered.
    if (!$order || strtolower($order['customerEmail'] ?? '') !== strtolower($user['email'] ?? '')) {
      set_flash('Order not found.', 'error');
      header('Location: orders.php'); exit;
    }
    if (($order['status'] ?? '') !== 'Delivered') {
      set_flash('Returns can only be requested for delivered orders.', 'error');
      header('Location: orders.php'); exit;
    }
    $item   = trim($_POST['item'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    // If the order has itemised lines, the chosen item must be one of them.
    // Older orders may have no recorded line items — accept a free-text item then.
    $valid  = array_column(order_items($orderId), 'name');
    if ($item === '' || $reason === '' || ($valid && !in_array($item, $valid, true))) {
      set_flash('Please choose an item and tell us why you want to return it.', 'error');
      header('Location: orders.php'); exit;
    }
    return_request_create([
      'orderId'       => $orderId,
      'customerName'  => $user['name'] ?? '',
      'customerEmail' => $user['email'] ?? '',
      'item'          => $item,
      'quantity'      => max(1, (int) ($_POST['quantity'] ?? 1)),
      'reason'        => $reason,
    ]);
    set_flash('Return request submitted for ' . $orderId . '. We will review it shortly.');
    header('Location: orders.php');
    exit;

  case 'profile_save':
    if (!$user || ($user['role'] ?? '') !== 'customer') { header('Location: login.php'); exit; }
    account_update_profile($user['email'] ?? '', $_POST);
    foreach (['name', 'phone', 'address', 'city', 'pincode'] as $k) {
      $_SESSION['user'][$k] = trim($_POST[$k] ?? ($user[$k] ?? ''));
    }
    set_flash('Profile saved.');
    header('Location: ' . safe_redirect('profile.php'));
    exit;

  case 'toggle_favorite':
    if (!$user || ($user['role'] ?? '') !== 'customer') { header('Location: login.php?role=customer'); exit; }
    $vid = (int) ($_POST['vendor_id'] ?? 0);
    $nowFav = favorite_store_toggle($user['email'] ?? '', $vid);
    set_flash($nowFav ? 'Added to your Regular / Family Stores.' : 'Removed from your Regular / Family Stores.',
              $nowFav ? 'success' : 'error');
    header('Location: ' . safe_redirect('index.php#stores'));
    exit;

  case 'notifications_read':
    if (!$user) { header('Location: login.php'); exit; }
    notifications_mark_all_read($user['role'] ?? '', $user['email'] ?? '');
    header('Location: ' . safe_redirect('index.php'));
    exit;

  /* -------------------------------- vendor -------------------------------- */
  case 'product_add':
    if (!$user || ($user['role'] ?? '') !== 'vendor') { header('Location: login.php'); exit; }
    $data = $_POST;
    $sourceId = (int) ($_POST['source_id'] ?? 0);
    if ($sourceId) {
      $source = find_by_id(catalog_products(), $sourceId);
      if ($source) {
        $data = array_merge([
          'name'     => $source['name'],
          'category' => $source['category'],
          'price'    => $source['price'],
          'unit'     => $source['unit'],
          'image'    => $source['image'],
        ], $data);
      }
    }
    $data['vendor'] = $user['shopName'] ?? '';
    $img  = save_uploaded_image('image_file');
    if ($img) $data['image'] = $img;
    product_add($data);
    $warn = upload_attempted('image_file') && !$img;
    set_flash('Product "' . ($_POST['name'] ?: 'New Product') . '" added.' . ($warn ? ' Image skipped — use a JPG/PNG/WebP/GIF under 3 MB.' : ''), $warn ? 'error' : 'success');
    header('Location: ' . safe_redirect('vendor.php?tab=products'));
    exit;

  case 'product_update':
    $data = $_POST;
    $img  = save_uploaded_image('image_file');
    if ($img) $data['image'] = $img;
    product_update((int)$_POST['id'], $data);
    $warn = upload_attempted('image_file') && !$img;
    set_flash('Product updated.' . ($warn ? ' New image skipped — use a JPG/PNG/WebP/GIF under 3 MB.' : ''), $warn ? 'error' : 'success');
    header('Location: ' . safe_redirect('vendor.php?tab=products'));
    exit;

  case 'product_delete':
    product_delete((int)$_POST['id']);
    set_flash('Product deleted.', 'error');
    header('Location: ' . safe_redirect('vendor.php?tab=products'));
    exit;

  case 'order_status':
    order_set_status($_POST['id'], $_POST['status']);
    set_flash('Order ' . $_POST['id'] . ' marked ' . $_POST['status'] . '.');
    header('Location: ' . safe_redirect('vendor.php?tab=orders'));
    exit;

  case 'payment_status':
    if (!$user || !in_array($user['role'] ?? '', ['superadmin', 'admin'], true)) { header('Location: login.php'); exit; }
    order_set_payment_status($_POST['id'] ?? '', $_POST['status'] ?? 'Unpaid');
    set_flash('Order ' . ($_POST['id'] ?? '') . ' marked ' . ($_POST['status'] ?? '') . '.');
    header('Location: ' . safe_redirect('superadmin.php?tab=orders'));
    exit;

  case 'return_status':
    if (!$user || !in_array($user['role'] ?? '', ['superadmin', 'admin'], true)) { header('Location: login.php'); exit; }
    $status = $_POST['status'] ?? '';
    return_request_set_status((int) ($_POST['id'] ?? 0), $status);
    set_flash('Return request marked ' . $status . '.', $status === 'Rejected' ? 'error' : 'success');
    header('Location: ' . safe_redirect('superadmin.php?tab=returns'));
    exit;

  case 'settings_save':
    if ($user && ($user['role'] ?? '') === 'vendor') {
      $name     = $_POST['name']  ?: $user['name'];
      $phone    = $_POST['phone'] ?? ($user['phone'] ?? '');
      $shopName = $_POST['shopName'] ?: ($user['shopName'] ?? '');
      vendor_settings_update($user['email'] ?? '', $name, $phone, $shopName);
      // Reflect immediately in the current session.
      $_SESSION['user']['name']     = $name;
      $_SESSION['user']['phone']    = $phone;
      $_SESSION['user']['shopName'] = $shopName;
    }
    set_flash('Settings saved.');
    header('Location: ' . safe_redirect('vendor.php?tab=settings'));
    exit;

  /* --------------------------------- admin -------------------------------- */
  case 'approval':
    approval_resolve((int)$_POST['id']);
    set_flash(($_POST['decision'] === 'approve' ? 'Approved: ' : 'Rejected: ') . $_POST['name'],
              $_POST['decision'] === 'approve' ? 'success' : 'error');
    header('Location: ' . safe_redirect('admin.php'));
    exit;

  case 'vendor_add':
    vendor_add($_POST);
    $msg = 'Vendor "' . ($_POST['name'] ?: 'New Vendor') . '" added.';
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
      $msg .= ' They can now log in on the Vendor tab with ' . $_POST['email'] . '.';
    }
    set_flash($msg);
    header('Location: ' . safe_redirect('admin.php?tab=vendors'));
    exit;

  case 'vendor_update':
    vendor_update((int)$_POST['id'], $_POST);
    set_flash(!empty($_POST['email']) && !empty($_POST['password'])
      ? 'Vendor updated. Login set for ' . $_POST['email'] . '.'
      : 'Vendor updated.');
    header('Location: ' . safe_redirect('admin.php?tab=vendors'));
    exit;

  case 'vendor_delete':
    vendor_delete((int)$_POST['id']);
    set_flash('Vendor removed.', 'error');
    header('Location: ' . safe_redirect('admin.php?tab=vendors'));
    exit;

  case 'shop_settings_save':
    if (!$user || ($user['role'] ?? '') !== 'admin') { header('Location: login.php'); exit; }
    shop_settings_save([
      'shopName' => trim($_POST['shopName'] ?? ''),
      'tagline'  => trim($_POST['tagline'] ?? ''),
      'address'  => trim($_POST['address'] ?? ''),
      'phone'    => trim($_POST['phone'] ?? ''),
      'email'    => trim($_POST['email'] ?? ''),
    ]);
    set_flash('Shop settings saved.');
    header('Location: ' . safe_redirect('admin.php?tab=setup'));
    exit;

  case 'admin_product_add':
    if (!$user || ($user['role'] ?? '') !== 'admin') { header('Location: login.php'); exit; }
    $data = $_POST; // vendor comes from the selected vendor in the form
    $img  = save_uploaded_image('image_file');
    if ($img) $data['image'] = $img;
    product_add($data);
    set_flash('Product "' . ($_POST['name'] ?: 'New Product') . '" added.');
    header('Location: ' . safe_redirect('admin.php?tab=setup'));
    exit;

  case 'offer_add':
    if (!$user || !in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) { header('Location: login.php'); exit; }
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    if ($start === '' || $end === '' || $end < $start) {
      set_flash('Please choose a valid offer date range.', 'error');
      header('Location: ' . safe_redirect($_POST['redirect'] ?? 'index.php'));
      exit;
    }
    if ((int) ($_POST['discount_value'] ?? 0) <= 0) {
      set_flash('Please enter a discount amount greater than zero.', 'error');
      header('Location: ' . safe_redirect($_POST['redirect'] ?? 'index.php'));
      exit;
    }
    offer_add($_POST + ['created_by' => $user['name'] ?? $user['email'] ?? '']);
    set_flash('Offer published — customers can now apply it at checkout.');
    header('Location: ' . safe_redirect($_POST['redirect'] ?? 'index.php'));
    exit;

  case 'toggle_hygiene':
    if (!$user || !in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) { header('Location: login.php'); exit; }
    $type = $_POST['type'] ?? '';
    $hid  = (int) ($_POST['id'] ?? 0);
    if ($type === 'vendor')      { $on = vendor_toggle_hygiene($hid);  $label = 'Store'; }
    elseif ($type === 'product') { $on = product_toggle_hygiene($hid); $label = 'Product'; }
    else { set_flash('Unknown item.', 'error'); header('Location: ' . safe_redirect('admin.php')); exit; }
    set_flash($label . ($on ? ' marked Hygiene Certified.' : ' hygiene certification removed.'), $on ? 'success' : 'error');
    header('Location: ' . safe_redirect($_POST['redirect'] ?? 'admin.php'));
    exit;

  case 'account_revoke':
    if (!$user || ($user['role'] ?? '') !== 'admin') { header('Location: login.php'); exit; }
    $target = strtolower(trim($_POST['email'] ?? ''));
    if ($target !== '' && strtolower($user['email'] ?? '') === $target) {
      set_flash("You can't revoke your own login while signed in.", 'error');
    } else {
      account_delete($target);
      set_flash('Login access revoked for ' . $target . '.', 'error');
    }
    header('Location: ' . safe_redirect('admin.php?tab=users'));
    exit;

  /* ------------------------------- supplier ------------------------------- */
  case 'supplier_request':
    supplier_request_set_status((int)$_POST['id'], $_POST['status']);
    set_flash('Request ' . $_POST['status'] . '.', $_POST['status'] === 'Approved' ? 'success' : 'error');
    header('Location: ' . safe_redirect('supplier.php'));
    exit;

  case 'sup_product_add':
    supplier_product_add($_POST + ['supplier' => $user['email'] ?? '']);
    set_flash('Product "' . ($_POST['name'] ?: 'New Product') . '" added.');
    header('Location: ' . safe_redirect('supplier.php?tab=products'));
    exit;

  case 'sup_product_update':
    supplier_product_update((int)$_POST['id'], $_POST);
    set_flash('Product updated.');
    header('Location: ' . safe_redirect('supplier.php?tab=products'));
    exit;

  case 'sup_product_delete':
    supplier_product_delete((int)$_POST['id']);
    set_flash('Product deleted.', 'error');
    header('Location: ' . safe_redirect('supplier.php?tab=products'));
    exit;

  /* ------------------------------- delivery ------------------------------- */
  case 'delivery_advance':
    delivery_advance($_POST['id']);
    set_flash('Delivery ' . $_POST['id'] . ' updated.');
    header('Location: ' . safe_redirect('delivery.php'));
    exit;

  default:
    set_flash('Unknown action.', 'error');
    header('Location: ' . safe_redirect('index.php'));
    exit;
}
