<?php
// Data access layer backed by SQLite (see db.php). Public function names/shapes are
// unchanged, so pages and actions keep working exactly as before — only the storage
// underneath moved from JSON files to a real database.

require_once __DIR__ . '/db.php';

/* ----------------------------- shop settings ----------------------------- */

function shop_settings(): array {
  $out = [];
  foreach (db_all("SELECT skey, svalue FROM settings") as $r) $out[$r['skey']] = $r['svalue'];
  return $out;
}

function shop_settings_save(array $settings): void {
  foreach ($settings as $k => $v) {
    db_exec("INSERT INTO settings (skey, svalue) VALUES (:k, :v)
             ON CONFLICT(skey) DO UPDATE SET svalue = :v", [':k' => $k, ':v' => $v]);
  }
}

function brand_name(): string {
  $name = trim(shop_settings()['shopName'] ?? '');
  return $name !== '' ? $name : 'Local Kirana Connect';
}

function brand_tagline(): string {
  $t = trim(shop_settings()['tagline'] ?? '');
  return $t !== '' ? $t : 'Order groceries from your neighborhood stores.';
}

/** The Centralized Pickup collection point (admin-configured in Shop Setup).
 *  name/address describe where to collect; fee is a flat handling charge (₹). */
function pickup_center(): array {
  $s = shop_settings();
  $name = trim($s['pickupCenterName'] ?? '');
  return [
    'name'    => $name !== '' ? $name : 'LocalKirana Collection Center',
    'address' => trim($s['pickupCenterAddress'] ?? ''),
    'fee'     => max(0, (int) ($s['pickupCenterFee'] ?? 0)),
  ];
}

/** A one-line label for a collection point, e.g. "Center Name — Address". */
function pickup_center_label(array $c): string {
  return trim($c['name'] . ($c['address'] !== '' ? ' — ' . $c['address'] : ''));
}

/* --------------------------------- getters --------------------------------- */
function products(): array             { return db_all("SELECT * FROM products ORDER BY id"); }
function offers(): array               { return db_all("SELECT * FROM offers ORDER BY id DESC"); }
function active_offers(): array {
  $today = date('Y-m-d');
  return db_all("SELECT * FROM offers WHERE start_date <= :today AND end_date >= :today ORDER BY id DESC", [':today' => $today]);
}
function offer_find(int $id): ?array {
  return db_row("SELECT * FROM offers WHERE id = :id", [':id' => $id]);
}
/** Human-readable label for an offer's discount, e.g. "20% OFF" or "₹50 OFF". */
function offer_discount_text(string $type, int $value): string {
  return $type === 'percent' ? ($value . '% OFF') : ('₹' . $value . ' OFF');
}
/** The rupee discount an offer gives on a given subtotal (0 if it doesn't apply). */
function offer_discount_amount(array $offer, int $subtotal): int {
  $min = (int) ($offer['min_order'] ?? 0);
  if ($subtotal < $min) return 0;
  $type  = $offer['discount_type'] ?? '';
  $value = (int) ($offer['discount_value'] ?? 0);
  if ($value <= 0) return 0;
  $amount = $type === 'percent' ? (int) floor($subtotal * $value / 100) : $value;
  return max(0, min($amount, $subtotal)); // never discount below ₹0
}
/** Active offers a customer can actually use on this subtotal, best saving first. */
function eligible_offers(int $subtotal): array {
  $out = [];
  foreach (active_offers() as $o) {
    $o['_amount'] = offer_discount_amount($o, $subtotal);
    if ($o['_amount'] > 0) $out[] = $o;
  }
  usort($out, fn($a, $b) => $b['_amount'] <=> $a['_amount']);
  return $out;
}
function offer_add(array $data): void {
  $start = trim($data['start_date'] ?? '') ?: date('Y-m-d');
  $end = trim($data['end_date'] ?? '') ?: date('Y-m-d', strtotime('+30 days'));
  $type  = ($data['discount_type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
  $value = max(0, (int) ($data['discount_value'] ?? 0));
  // Keep a friendly text label (falls back to any free text the admin typed).
  $text  = trim($data['discount'] ?? '');
  if ($text === '' && $value > 0) $text = offer_discount_text($type, $value);
  db_exec("INSERT INTO offers (title, description, discount, discount_type, discount_value, min_order, code, start_date, end_date, created_by, created_at)
           VALUES (:title, :description, :discount, :dtype, :dvalue, :minorder, :code, :start_date, :end_date, :created_by, :created_at)", [
    ':title' => trim($data['title'] ?? '') ?: 'Special Offer',
    ':description' => trim($data['description'] ?? ''),
    ':discount' => $text,
    ':dtype' => $type,
    ':dvalue' => $value,
    ':minorder' => max(0, (int) ($data['min_order'] ?? 0)),
    ':code' => strtoupper(trim($data['code'] ?? '')),
    ':start_date' => $start,
    ':end_date' => $end,
    ':created_by' => trim($data['created_by'] ?? ''),
    ':created_at' => date('Y-m-d H:i:s'),
  ]);
}
function catalog_products(): array {
  $rows = db_all("SELECT * FROM product_catalog ORDER BY id");
  if ($rows) return $rows;

  $catalog = [
    ['Potato', 'Vegetables', 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=600', '1kg'],
    ['Onion', 'Vegetables', 'https://images.unsplash.com/photo-1508747703725-719777637510?w=600', '1kg'],
    ['Tomato', 'Vegetables', 'https://images.unsplash.com/photo-1546094096-0df4bcaaa337?w=600', '1kg'],
    ['Beetroot', 'Vegetables', 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=600', '1kg'],
    ['Carrot', 'Vegetables', 'https://images.unsplash.com/photo-1445282768818-728615cc910a?w=600', '1kg'],
    ['Spinach', 'Vegetables', 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=600', '1 bunch'],
    ['Cauliflower', 'Vegetables', 'https://images.unsplash.com/photo-1568584711075-3d021a7c3ca3?w=600', '1 piece'],
    ['Cabbage', 'Vegetables', 'https://images.unsplash.com/photo-1594282486552-05b4d80fbb9f?w=600', '1 piece'],
    ['Brinjal', 'Vegetables', 'https://images.unsplash.com/photo-1615484477778-ca3b77940c25?w=600', '1kg'],
    ['Lady Finger', 'Vegetables', 'https://images.unsplash.com/photo-1425543103986-22abb7d7ea1c?w=600', '1kg'],
    ['Green Peas', 'Vegetables', 'https://images.unsplash.com/photo-1587735243615-c03f25aaff15?w=600', '500g'],
    ['Cucumber', 'Vegetables', 'https://images.unsplash.com/photo-1449300079323-02e209d9d3a6?w=600', '1kg'],
    ['Apple', 'Fruits', 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=600', '1kg'],
    ['Banana', 'Fruits', 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=600', '1 dozen'],
    ['Orange', 'Fruits', 'https://images.unsplash.com/photo-1547514701-42782101795e?w=600', '1kg'],
    ['Mango', 'Fruits', 'https://images.unsplash.com/photo-1553279768-865429fa0078?w=600', '1kg'],
    ['Grapes', 'Fruits', 'https://images.unsplash.com/photo-1537640538966-79f369143f8f?w=600', '500g'],
    ['Pomegranate', 'Fruits', 'https://images.unsplash.com/photo-1541344999736-83eca272f6fc?w=600', '1kg'],
    ['Papaya', 'Fruits', 'https://images.unsplash.com/photo-1526318472351-c75fcf070305?w=600', '1 piece'],
    ['Watermelon', 'Fruits', 'https://images.unsplash.com/photo-1563114773-84221bd62daa?w=600', '1 piece'],
    ['Guava', 'Fruits', 'https://images.unsplash.com/photo-1536511132770-e5058c7e8c46?w=600', '1kg'],
    ['Pineapple', 'Fruits', 'https://images.unsplash.com/photo-1550258987-190a2d41a8ba?w=600', '1 piece'],
    ['Rice', 'Groceries', 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=600', '5kg'],
    ['Wheat Flour', 'Groceries', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600', '5kg'],
    ['Toor Dal', 'Groceries', 'https://images.unsplash.com/photo-1515543904379-3d757afe72e4?w=600', '1kg'],
    ['Chana Dal', 'Groceries', 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=600', '1kg'],
    ['Moong Dal', 'Groceries', 'https://images.unsplash.com/photo-1612257999756-6f32a4f0a6f8?w=600', '1kg'],
    ['Sugar', 'Groceries', 'https://images.unsplash.com/photo-1581441363689-1f3c3c414635?w=600', '1kg'],
    ['Salt', 'Groceries', 'https://images.unsplash.com/photo-1518110925495-5fe2eae8dfd4?w=600', '1kg'],
    ['Turmeric Powder', 'Groceries', 'https://images.unsplash.com/photo-1615485500704-8e990f9900f7?w=600', '200g'],
    ['Red Chili Powder', 'Groceries', 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=600', '200g'],
    ['Cooking Oil', 'Groceries', 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=600', '1 litre'],
    ['Milk', 'Dairy', 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=600', '1 litre'],
    ['Curd', 'Dairy', 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=600', '500g'],
    ['Paneer', 'Dairy', 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=600', '200g'],
    ['Butter', 'Dairy', 'https://images.unsplash.com/photo-1589985270958-16d90f5d7a9c?w=600', '100g'],
    ['Cheese', 'Dairy', 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=600', '200g'],
    ['Eggs', 'Dairy', 'https://images.unsplash.com/photo-1506976785307-8732e854ad03?w=600', '12 pieces'],
    ['Tea', 'Beverages', 'https://images.unsplash.com/photo-1594631252845-29fc4cc8cde9?w=600', '250g'],
    ['Coffee', 'Beverages', 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=600', '200g'],
    ['Fruit Juice', 'Beverages', 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=600', '1 litre'],
    ['Coconut Water', 'Beverages', 'https://images.unsplash.com/photo-1580984969071-a8da8c6b5c1d?w=600', '1 bottle'],
    ['Biscuits', 'Snacks', 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=600', '1 pack'],
    ['Potato Chips', 'Snacks', 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=600', '1 pack'],
    ['Namkeen', 'Snacks', 'https://images.unsplash.com/photo-1621939514649-280e2aa9f2a4?w=600', '200g'],
    ['Bread', 'Snacks', 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?w=600', '1 loaf'],
  ];
  foreach ($catalog as $item) {
    db_exec("INSERT INTO product_catalog (name, category, image, unit) VALUES (:name, :category, :image, :unit)", [
      ':name' => $item[0], ':category' => $item[1], ':image' => $item[2], ':unit' => $item[3],
    ]);
  }
  return db_all("SELECT * FROM product_catalog ORDER BY id");
}
function orders(): array               { return db_all("SELECT * FROM orders ORDER BY id"); }
function vendors_list(): array         { return db_all("SELECT * FROM vendors ORDER BY id"); }
function suppliers_list(): array       { return db_all("SELECT * FROM suppliers ORDER BY id"); }
function delivery_partners(): array    { return db_all("SELECT * FROM delivery_partners ORDER BY id"); }
function supplier_products(): array    { return db_all("SELECT * FROM supplier_products ORDER BY id"); }
function supplier_requests(): array    { return db_all("SELECT * FROM supplier_requests ORDER BY id"); }
function deliveries(): array           { return db_all("SELECT * FROM deliveries ORDER BY id"); }
/** Active deliveries assigned to one partner (legacy unassigned rows show to nobody).
 *  Joined to the order so the panel knows the payment method / amount to collect. */
function deliveries_for_partner(int $pid): array {
  return db_all("SELECT d.*, o.paymentMethod AS paymentMethod, o.total AS orderTotal
                 FROM deliveries d LEFT JOIN orders o ON o.id = d.id
                 WHERE d.partnerId = :pid ORDER BY d.id", [':pid' => $pid]);
}
/** The live delivery job for an order (null once completed/delivered). Used by the
 *  customer's live tracking map to show the Assigned / Picked Up sub-status. */
function delivery_for_order(string $orderId): ?array {
  return db_row("SELECT * FROM deliveries WHERE id = :id", [':id' => $orderId]);
}
/** The delivery_partners row for a signed-in partner, matched by login email. */
function delivery_partner_by_email(string $email): ?array {
  return db_row("SELECT * FROM delivery_partners WHERE lower(email) = lower(:e)", [':e' => trim($email)]);
}
function completed_deliveries(): array { return db_all("SELECT id, customer, amount, earnings, time FROM completed_deliveries ORDER BY cid"); }
function approvals(): array            { return db_all("SELECT * FROM approvals ORDER BY id"); }

/* ------------------------------- economics -------------------------------- */
// One shared money model, used by the accounting views and the per-order
// breakdown so every screen shows the same numbers. The rates are the business
// model: what the platform keeps and what the delivery partner is paid.
const PLATFORM_COMMISSION_RATE = 0.10; // platform's cut of each order, taken from the store's share
const DELIVERY_PARTNER_RATE    = 0.08; // paid to the delivery partner, as a share of the order value

/** Split one order's value into who-gets-what (whole rupees). */
function order_economics(int $total): array {
  $commission  = (int) round($total * PLATFORM_COMMISSION_RATE);
  $deliveryPay = (int) round($total * DELIVERY_PARTNER_RATE);
  return [
    'total'       => $total,
    'commission'  => $commission,   // platform commission
    'deliveryPay' => $deliveryPay,  // delivery-partner payout
    'vendorNet'   => $total - $commission,
    'platformNet' => $commission - $deliveryPay,
  ];
}

/** Add up the money model across many orders (for the accounting summary). */
function orders_economics(array $orders): array {
  $sum = ['total' => 0, 'commission' => 0, 'deliveryPay' => 0, 'vendorNet' => 0, 'platformNet' => 0];
  foreach ($orders as $o) {
    if (($o['status'] ?? '') === 'Cancelled') continue;
    foreach (order_economics((int) ($o['total'] ?? 0)) as $k => $v) $sum[$k] += $v;
  }
  return $sum;
}

/** Look up a record (from an already-fetched array) by id. */
function find_by_id(array $rows, $id): ?array {
  foreach ($rows as $r) { if ((string) ($r['id'] ?? '') === (string) $id) return $r; }
  return null;
}

/* -------------------------- profiles (from sign-up) ------------------------- */

function vendor_profile_create(array $data): array {
  db_exec("INSERT INTO vendors (name, owner, email, address, city, phone, rating, distance, verified, image, deliveryTime, totalProducts, totalOrders)
           VALUES (:name, :owner, :email, :address, :city, :phone, 0, '', :verified, :image, '', 0, 0)", [
    ':name'     => $data['name'] ?: 'New Store',
    ':owner'    => $data['owner'] ?: 'Owner',
    ':email'    => trim($data['email'] ?? ''),
    ':address'  => $data['address'] ?? '',
    ':city'     => $data['city'] ?? '',
    ':phone'    => $data['phone'] ?? '',
    ':verified' => !empty($data['verified']) ? 1 : 0,
    ':image'    => $data['image'] ?: 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=400',
  ]);
  return db_row("SELECT * FROM vendors WHERE id = :id", [':id' => db_insert_id()]);
}

function supplier_profile_create(array $data): array {
  db_exec("INSERT INTO suppliers (name, contact, email, phone, productsSupplied, totalVendors, rating, verified)
           VALUES (:name, :contact, :email, :phone, 0, 0, 0, 0)", [
    ':name'    => $data['name'] ?: 'New Supplier',
    ':contact' => $data['contact'] ?: 'Contact',
    ':email'   => trim($data['email'] ?? ''),
    ':phone'   => $data['phone'] ?? '',
  ]);
  return db_row("SELECT * FROM suppliers WHERE id = :id", [':id' => db_insert_id()]);
}

function delivery_profile_create(array $data): array {
  db_exec("INSERT INTO delivery_partners (name, email, phone, vehicle, totalDeliveries, rating, status, earnings)
           VALUES (:name, :email, :phone, :vehicle, 0, 0, 'Active', 0)", [
    ':name'    => $data['name'] ?: 'New Partner',
    ':email'   => trim($data['email'] ?? ''),
    ':phone'   => $data['phone'] ?? '',
    ':vehicle' => $data['vehicle'] ?: 'Bike',
  ]);
  return db_row("SELECT * FROM delivery_partners WHERE id = :id", [':id' => db_insert_id()]);
}

/* --------------------------- vendor CRUD (admin) --------------------------- */

function vendor_add(array $data): void {
  $vendor = vendor_profile_create($data + ['verified' => true]);
  if (!empty($data['email']) && !empty($data['password'])) {
    account_register([
      'email'    => trim($data['email']), 'role' => 'vendor',
      'name'     => $vendor['owner'], 'shopName' => $vendor['name'],
      'phone'    => $vendor['phone'], 'vendorId' => $vendor['id'],
    ], $data['password']);
  }
}

function vendor_update(int $id, array $data): void {
  $v = db_row("SELECT * FROM vendors WHERE id = :id", [':id' => $id]);
  if (!$v) return;
  db_exec("UPDATE vendors SET name = :name, owner = :owner, address = :address, city = :city, phone = :phone, email = :email WHERE id = :id", [
    ':name'    => $data['name']    ?: $v['name'],
    ':owner'   => $data['owner']   ?: $v['owner'],
    ':address' => $data['address'] ?: $v['address'],
    ':city'    => $data['city']    ?: $v['city'],
    ':phone'   => $data['phone']   ?: $v['phone'],
    ':email'   => (isset($data['email']) && trim($data['email']) !== '') ? trim($data['email']) : $v['email'],
    ':id'      => $id,
  ]);
  if (!empty($data['email']) && !empty($data['password'])) {
    $u = db_row("SELECT * FROM vendors WHERE id = :id", [':id' => $id]);
    account_register([
      'email'    => trim($data['email']), 'role' => 'vendor',
      'name'     => $u['owner'], 'shopName' => $u['name'],
      'phone'    => $u['phone'], 'vendorId' => $u['id'],
    ], $data['password']);
  }
}

function vendor_delete(int $id): void {
  $v = db_row("SELECT email FROM vendors WHERE id = :id", [':id' => $id]);
  db_exec("DELETE FROM vendors WHERE id = :id", [':id' => $id]);
  if ($v && !empty($v['email'])) account_delete($v['email']);
}

/* ------------------------------ product CRUD ------------------------------- */

/** True if the browser actually submitted a file for this field. */
function upload_attempted(string $field): bool {
  return !empty($_FILES[$field]) && (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
}

/**
 * Move an uploaded image into the web-accessible uploads/ folder and return its
 * relative path (e.g. "uploads/p_ab12.jpg"), or null if nothing valid was uploaded.
 * Accepts JPG/PNG/WebP/GIF up to 3 MB.
 */
function save_uploaded_image(string $field = 'image_file'): ?string {
  if (!upload_attempted($field)) return null;
  $f = $_FILES[$field];
  if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
  if (($f['size'] ?? 0) <= 0 || $f['size'] > 3 * 1024 * 1024) return null;

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $f['tmp_name']);
  finfo_close($finfo);
  $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
  if ($ext === null) return null;

  $dir = __DIR__ . '/../uploads';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
  if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return null;
  return 'uploads/' . $name;
}

function product_add(array $data): void {
  db_exec("INSERT INTO products (name, category, price, stock, image, vendor, rating, unit, brand, expiry, hygiene_verified)
           VALUES (:name, :category, :price, :stock, :image, :vendor, 0, :unit, :brand, :expiry, :hygiene)", [
    ':name'     => $data['name'] ?: 'New Product',
    ':category' => $data['category'] ?: 'Groceries',
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':image'    => $data['image'] ?: 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=400',
    ':vendor'   => $data['vendor'] ?? '',
    ':unit'     => $data['unit'] ?: '1 unit',
    ':brand'    => trim($data['brand'] ?? ''),
    ':expiry'   => trim($data['expiry'] ?? ''),
    ':hygiene'  => !empty($data['hygiene_verified']) ? 1 : 0,
  ]);
}

function product_update(int $id, array $data): void {
  $p = db_row("SELECT * FROM products WHERE id = :id", [':id' => $id]);
  if (!$p) return;
  db_exec("UPDATE products SET name = :name, category = :category, price = :price, stock = :stock, unit = :unit, image = :image, brand = :brand, expiry = :expiry, hygiene_verified = :hygiene WHERE id = :id", [
    ':name'     => $data['name'] ?: $p['name'],
    ':category' => $data['category'] ?: $p['category'],
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':unit'     => !empty($data['unit']) ? $data['unit'] : $p['unit'],
    // Keep the existing image unless a new one was provided (uploaded).
    ':image'    => !empty($data['image']) ? $data['image'] : $p['image'],
    ':brand'    => trim($data['brand'] ?? ''),
    ':expiry'   => trim($data['expiry'] ?? ''),
    ':hygiene'  => !empty($data['hygiene_verified']) ? 1 : 0,
    ':id'       => $id,
  ]);
}

/** Toggle the admin "hygiene certified" flag on a product; returns the new state. */
function product_toggle_hygiene(int $id): bool {
  $p = db_row("SELECT hygiene_verified FROM products WHERE id = :id", [':id' => $id]);
  if (!$p) return false;
  $new = empty($p['hygiene_verified']) ? 1 : 0;
  db_exec("UPDATE products SET hygiene_verified = :h WHERE id = :id", [':h' => $new, ':id' => $id]);
  return (bool) $new;
}

/** Toggle a vendor's hygiene-certified flag; returns the new state. */
function vendor_toggle_hygiene(int $id): bool {
  $v = db_row("SELECT hygiene_verified FROM vendors WHERE id = :id", [':id' => $id]);
  if (!$v) return false;
  $new = empty($v['hygiene_verified']) ? 1 : 0;
  db_exec("UPDATE vendors SET hygiene_verified = :h WHERE id = :id", [':h' => $new, ':id' => $id]);
  return (bool) $new;
}

/** The vendor record for a given shop name (used to reach the owner's login email). */
function vendor_by_name(string $name): ?array {
  return db_row("SELECT * FROM vendors WHERE name = :n", [':n' => $name]);
}

function product_delete(int $id): void {
  db_exec("DELETE FROM products WHERE id = :id", [':id' => $id]);
}

/* -------------------------------- orders ----------------------------------- */

function order_set_status(string $id, string $status): void {
  db_exec("UPDATE orders SET status = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
  // Once delivered, any Cash-on-Delivery amount has now been collected — settle the
  // payment history (prepaid rows are already Paid, so this is a no-op for them).
  if ($status === 'Delivered') payment_mark_paid($id);
  // Let the customer know their order moved along.
  $o = db_row("SELECT customerEmail FROM orders WHERE id = :id", [':id' => $id]);
  if ($o && !empty($o['customerEmail'])) {
    notify('customer', $o['customerEmail'], "Order $id is now $status", "Track your order anytime.", 'orders.php');
  }
}

function order_set_payment_status(string $id, string $status): void {
  $status = $status === 'Paid' ? 'Paid' : 'Unpaid';
  db_exec("UPDATE orders SET paymentStatus = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
}

/** Record the Razorpay payment reference against a prepaid order. */
function order_set_payment_ref(string $id, string $ref): void {
  db_exec("UPDATE orders SET paymentRef = :r WHERE id = :id", [':r' => $ref, ':id' => $id]);
  payment_mark_paid($id, $ref); // keep the payment history row in sync
}

/* ---------------------------- payments (history) --------------------------- */

/** Record a payment / transaction for an order — the durable history row behind
 *  the customer receipt popup and the customer/vendor/admin payment history.
 *  Captures the money split (vendor share vs platform/admin commission). */
function payment_record(string $orderId, string $customerEmail, string $customerName, string $vendor,
                        int $amount, string $method, string $status, string $ref = '', ?int $goodsValue = null): void {
  // The commission / vendor split is on the goods value (which excludes any
  // Centralized Pickup handling fee); $amount is the grand total actually charged.
  $eco = order_economics(max(0, $goodsValue ?? $amount));
  db_exec("INSERT INTO payments (orderId, customerEmail, customerName, vendor, amount, method, status, ref, commission, vendorNet, created_at)
           VALUES (:oid, :ce, :cn, :v, :amt, :m, :st, :ref, :comm, :vnet, :ts)", [
    ':oid'  => $orderId,
    ':ce'   => strtolower(trim($customerEmail)),
    ':cn'   => $customerName,
    ':v'    => $vendor,
    ':amt'  => $amount,
    ':m'    => $method,
    ':st'   => $status,
    ':ref'  => $ref,
    ':comm' => $eco['commission'],
    ':vnet' => $eco['vendorNet'],
    ':ts'   => date('Y-m-d H:i:s'),
  ]);
}

/** Every payment, newest first (the admin's platform-wide transaction history). */
function payments_all(): array { return db_all("SELECT * FROM payments ORDER BY id DESC"); }

/** One customer's payment history, newest first. */
function payments_for_customer(string $email): array {
  return db_all("SELECT * FROM payments WHERE lower(customerEmail) = lower(:e) ORDER BY id DESC", [':e' => trim($email)]);
}

/** The payment row for a given order (null if none recorded). */
function payment_for_order(string $orderId): ?array {
  return db_row("SELECT * FROM payments WHERE orderId = :id ORDER BY id DESC LIMIT 1", [':id' => $orderId]);
}

/** Mark an order's payment as collected (Paid) — prepaid verified, or COD delivered. */
function payment_mark_paid(string $orderId, string $ref = ''): void {
  if ($ref !== '') {
    db_exec("UPDATE payments SET status = 'Paid', ref = :r WHERE orderId = :id", [':r' => $ref, ':id' => $orderId]);
  } else {
    db_exec("UPDATE payments SET status = 'Paid' WHERE orderId = :id", [':id' => $orderId]);
  }
}

/** The delivery partner confirms they collected the Cash-on-Delivery amount:
 *  mark the payment Paid and record who collected it and when. */
function payment_collect_cash(string $orderId, string $collectedBy): void {
  db_exec("UPDATE payments SET status = 'Paid', collectedBy = :by, collectedAt = :ts WHERE orderId = :id",
    [':by' => trim($collectedBy), ':ts' => date('Y-m-d H:i:s'), ':id' => $orderId]);
}

/** Notify every store involved in an order (handles multi-store orders). */
function notify_order_vendors(array $order, string $title, string $body = '', string $link = ''): void {
  $id = (string) ($order['id'] ?? '');
  $vendors = array_unique(array_filter(array_map(fn($r) => trim((string) ($r['vendor'] ?? '')), order_items($id))));
  if (!$vendors) {
    $vn = trim((string) ($order['vendor'] ?? ''));
    if ($vn !== '' && $vn !== 'Multiple Stores') $vendors = [$vn];
  }
  foreach ($vendors as $vn) {
    $v = vendor_by_name($vn);
    if ($v && !empty($v['email'])) notify('vendor', $v['email'], $title, $body, $link);
  }
}

/** Resolved payment status for an order (derives one for older rows). */
function order_payment_status(array $o): string {
  $s = trim($o['paymentStatus'] ?? '');
  if ($s !== '') return $s;
  return (($o['paymentMethod'] ?? '') === 'Cash on Delivery') ? 'Unpaid' : 'Paid';
}

/** True if the order was paid online (prepaid) rather than Cash on Delivery. */
function order_is_prepaid(array $o): bool {
  return ($o['paymentMethod'] ?? '') !== 'Cash on Delivery' && ($o['paymentMethod'] ?? '') !== '';
}

/** Grand total actually charged: the goods total plus any pickup handling fee. */
function order_grand_total(array $o): int {
  return (int) ($o['total'] ?? 0) + (int) ($o['pickupFee'] ?? 0);
}

/* --------------------------- centralized pickup ---------------------------- */

/** Distinct shops that have items in an order (the stores that must prepare). */
function pickup_shops_for_order(string $orderId): array {
  return array_values(array_unique(array_filter(array_map(
    fn($r) => trim((string) ($r['vendor'] ?? '')),
    order_items($orderId)
  ))));
}

/** Shops that have marked their items ready for the collection centre. */
function pickup_ready_shops(string $orderId): array {
  return array_map(fn($r) => $r['vendor'], db_all("SELECT vendor FROM pickup_readiness WHERE orderId = :id", [':id' => $orderId]));
}

/** Has this shop marked its part of the order ready? */
function pickup_is_shop_ready(string $orderId, string $vendor): bool {
  return in_array(trim($vendor), pickup_ready_shops($orderId), true);
}

/** Readiness progress: ['ready'=>x, 'total'=>y, 'shops'=>[], 'readyShops'=>[]]. */
function pickup_progress(string $orderId): array {
  $shops = pickup_shops_for_order($orderId);
  $ready = array_values(array_intersect($shops, pickup_ready_shops($orderId)));
  return ['ready' => count($ready), 'total' => count($shops), 'shops' => $shops, 'readyShops' => $ready];
}

/** Are all shops in the order ready (and there is at least one)? */
function pickup_all_ready(string $orderId): bool {
  $p = pickup_progress($orderId);
  return $p['total'] > 0 && $p['ready'] >= $p['total'];
}

/** A shop confirms its items are ready at the collection centre. When every shop
 *  in the order is ready, the customer (and the centre/admin) are notified. */
function pickup_mark_ready(string $orderId, string $vendor): void {
  $vendor = trim($vendor);
  if ($orderId === '' || $vendor === '') return;
  db_exec("INSERT OR IGNORE INTO pickup_readiness (orderId, vendor, ready_at) VALUES (:id, :v, :ts)",
    [':id' => $orderId, ':v' => $vendor, ':ts' => date('Y-m-d H:i:s')]);
  if (pickup_all_ready($orderId)) {
    $o = db_row("SELECT * FROM orders WHERE id = :id", [':id' => $orderId]);
    $centre = pickup_center()['name'];
    if ($o && !empty($o['customerEmail'])) {
      notify('customer', $o['customerEmail'], "Order $orderId is ready for pickup",
             "All items are ready at $centre. Collect your complete order anytime.", 'orders.php');
    }
    notify('admin', '', "Pickup order $orderId fully ready", "All shops have delivered to $centre.", 'admin.php');
  }
}

/**
 * Create an order from the current cart, then empty the cart.
 * $discount   – rupee discount already validated by the caller (from an offer).
 * $offerCode  – the code/label of the applied offer (for the record).
 * $partner    – delivery_partners row when the customer chose Home Delivery.
 * $pickupFee  – flat handling fee (₹) added on top for Centralized Pickup orders.
 */
function place_order(?array $user = null, string $payment = 'Cash on Delivery', string $deliveryOption = 'Self Pickup',
                     int $discount = 0, string $offerCode = '', ?array $partner = null, int $pickupFee = 0): string {
  $items = cart_items();
  if (!$items) return '';
  $max = (int) (db_row("SELECT MAX(CAST(SUBSTR(id, 5) AS INTEGER)) AS m FROM orders")['m'] ?? 0);
  $num = '#ORD' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);

  // One cart can hold items from several stores. Record each distinct store;
  // when there is more than one we label the order "Multiple Stores".
  $storeNames = [];
  foreach ($items as $it) { $s = trim($it['vendor'] ?? ''); if ($s !== '') $storeNames[$s] = true; }
  $storeNames = array_keys($storeNames);
  $vendorName = count($storeNames) === 1 ? $storeNames[0] : (count($storeNames) > 1 ? 'Multiple Stores' : '');
  $vendor = count($storeNames) === 1 ? db_row("SELECT id FROM vendors WHERE name = :n", [':n' => $vendorName]) : null;

  // Build a full delivery address from the customer's saved profile.
  $addr = format_address($user['address'] ?? '', $user['city'] ?? '', $user['pincode'] ?? '');
  // Prepaid methods are Paid at checkout; Cash on Delivery is Unpaid until collected.
  $payStatus = ($payment === 'Cash on Delivery') ? 'Unpaid' : 'Paid';
  $deliveryOption = in_array($deliveryOption, ['Self Pickup', 'Home Delivery', 'Centralized Pickup'], true) ? $deliveryOption : 'Self Pickup';

  // Centralized Pickup: a flat handling fee on top, and a snapshot of the centre
  // so the customer always sees where to collect (even if settings change later).
  $isCentralized  = $deliveryOption === 'Centralized Pickup';
  $pickupFee      = $isCentralized ? max(0, $pickupFee) : 0;
  $pickupLocation = $isCentralized ? pickup_center_label(pickup_center()) : '';

  $subtotal = cart_total_price();
  $discount = max(0, min($discount, $subtotal));
  $total    = $subtotal - $discount;   // goods value (the fee is tracked separately)
  $grand    = $total + $pickupFee;      // amount actually charged

  db_exec("INSERT INTO orders (id, customerId, customerName, customerEmail, vendor, vendorId, items, total, status, date, paymentMethod, paymentStatus, deliveryAddress, deliveryOption, discount, offerCode, deliveryPartnerId, deliveryPartner, pickupFee, pickupLocation)
           VALUES (:id, :cid, :cn, :cemail, :v, :vid, :items, :total, 'Pending', :date, :pay, :pstatus, :addr, :dopt, :disc, :ocode, :pid, :pname, :pfee, :ploc)", [
    ':id'        => $num,
    ':cid'       => $user['id'] ?? 0,
    ':cn'        => $user['name'] ?? 'Guest',
    ':cemail'    => $user['email'] ?? '',
    ':v'         => $vendorName,
    ':vid'       => $vendor['id'] ?? 0,
    ':items'     => cart_total_items(),
    ':total'     => $total,
    ':date'      => date('Y-m-d'),
    ':pay'       => $payment,
    ':pstatus'   => $payStatus,
    ':addr'      => $addr,
    ':dopt'      => $deliveryOption,
    ':disc'      => $discount,
    ':ocode'     => $offerCode,
    ':pid'       => $partner['id'] ?? 0,
    ':pname'     => $partner['name'] ?? '',
    ':pfee'      => $pickupFee,
    ':ploc'      => $pickupLocation,
  ]);
  // Save the actual products in this order.
  foreach ($items as $it) {
    db_exec("INSERT INTO order_items (orderId, name, price, quantity, unit, vendor)
             VALUES (:oid, :name, :price, :qty, :unit, :vendor)", [
      ':oid'    => $num,
      ':name'   => $it['name'],
      ':price'  => (int) $it['price'],
      ':qty'    => (int) $it['quantity'],
      ':unit'   => $it['unit'] ?? '',
      ':vendor' => $it['vendor'] ?? '',
    ]);
  }

  // For Home Delivery, create a delivery job assigned to the chosen partner.
  if ($deliveryOption === 'Home Delivery' && $partner) {
    db_exec("INSERT INTO deliveries (id, customer, address, amount, distance, otp, status, partnerId)
             VALUES (:id, :c, :a, :amt, '', :otp, 'Assigned', :pid)", [
      ':id'  => $num,
      ':c'   => $user['name'] ?? 'Customer',
      ':a'   => $addr,
      ':amt' => $total,
      ':otp' => (string) random_int(1000, 9999),
      ':pid' => $partner['id'],
    ]);
  }

  // Record the payment in the history. Prepaid is Paid now; Cash on Delivery is
  // Pending until collected. The charged amount is the grand total (incl. the
  // pickup fee); the vendor/commission split stays on the goods value.
  payment_record($num, $user['email'] ?? '', $user['name'] ?? 'Guest', $vendorName ?: 'Local store',
                 (int) $grand, $payment, $payStatus === 'Paid' ? 'Paid' : 'Pending', '', (int) $total);

  // ---- Notifications to everyone involved ----
  $cust = $user['email'] ?? '';
  notify('customer', $cust, "Order $num placed", "Your order of ₹$total is confirmed. We'll keep you posted.", 'orders.php');
  foreach ($storeNames as $sn) {
    $v = vendor_by_name($sn);
    if ($v && !empty($v['email'])) {
      notify('vendor', $v['email'], "New order $num", "You have a new order from " . ($user['name'] ?? 'a customer') . ".", 'vendor.php?tab=orders');
    }
  }
  notify('admin', '', "New order $num", "₹$total · " . ($vendorName ?: 'store') . " · " . ($user['name'] ?? 'Customer'), 'admin.php');
  notify('superadmin', '', "New order $num", "₹$total · payment: $payment", 'superadmin.php?tab=orders');
  if ($deliveryOption === 'Home Delivery' && $partner && !empty($partner['email'])) {
    notify('delivery', $partner['email'], "New delivery assigned: $num", "Deliver to " . ($user['name'] ?? 'customer') . ($addr ? " · $addr" : ''), 'delivery.php?tab=active');
  }
  if ($isCentralized) {
    $shopCount = count($storeNames);
    notify('customer', $cust, "Collect order $num at the centre", "We'll let you know when all " . ($shopCount > 1 ? "$shopCount shops have" : 'items are') . " ready at " . pickup_center()['name'] . ".", 'orders.php');
    notify('admin', '', "Centralized pickup order $num", "Organise " . ($shopCount > 1 ? "$shopCount shops" : 'this order') . " at " . pickup_center()['name'] . " for " . ($user['name'] ?? 'a customer') . ".", 'admin.php');
  }

  cart_clear();
  return $num;
}

/**
 * Orders that concern a given store — either placed wholly with it, or containing
 * at least one of its line items (multi-store orders). Each row's `items`/`total`
 * are recomputed to just that store's share so the vendor sees their own figures.
 */
function vendor_orders(string $shop): array {
  $rows = db_all(
    "SELECT DISTINCT o.* FROM orders o
     LEFT JOIN order_items oi ON oi.orderId = o.id
     WHERE o.vendor = :s OR oi.vendor = :s
     ORDER BY o.id DESC",
    [':s' => $shop]
  );
  foreach ($rows as &$o) {
    $lines = db_all("SELECT price, quantity FROM order_items WHERE orderId = :id AND vendor = :s",
      [':id' => $o['id'], ':s' => $shop]);
    if ($lines) {
      $items = 0; $total = 0;
      foreach ($lines as $l) { $items += (int) $l['quantity']; $total += (int) $l['price'] * (int) $l['quantity']; }
      $o['items'] = $items;
      $o['total'] = $total;
      $o['multiStore'] = ($o['vendor'] === 'Multiple Stores');
    }
  }
  unset($o);
  return $rows;
}

/** Line items of an order that belong to one store. */
function order_items_for_vendor(string $orderId, string $shop): array {
  return db_all("SELECT * FROM order_items WHERE orderId = :id AND vendor = :s ORDER BY oiid",
    [':id' => $orderId, ':s' => $shop]);
}

/** Line items for an order. */
function order_items(string $orderId): array {
  return db_all("SELECT * FROM order_items WHERE orderId = :id ORDER BY oiid", [':id' => $orderId]);
}

/** Cancel an order (only allowed while Pending), scoped to its owner email. */
function order_cancel(string $orderId, string $customerEmail): bool {
  $o = db_row("SELECT status, customerEmail FROM orders WHERE id = :id", [':id' => $orderId]);
  if (!$o) return false;
  if (strtolower($o['customerEmail'] ?? '') !== strtolower($customerEmail)) return false;
  if (($o['status'] ?? '') !== 'Pending') return false;
  db_exec("UPDATE orders SET status = 'Cancelled' WHERE id = :id", [':id' => $orderId]);
  return true;
}

/** Orders placed by a given customer email, newest first. */
function customer_orders(string $email): array {
  return db_all("SELECT * FROM orders WHERE lower(customerEmail) = lower(:e) ORDER BY id DESC", [':e' => trim($email)]);
}

/** Combine address parts into one line: "address, city - pincode". */
function format_address($address, $city = '', $pincode = ''): string {
  $a = trim((string) $address);
  if (trim((string) $city) !== '')    $a = trim($a . ', ' . $city, ', ');
  if (trim((string) $pincode) !== '') $a = trim($a . ' - ' . $pincode);
  return $a;
}

/** The delivery address for an order — the one stored at checkout, or (fallback)
 *  the customer's current saved profile address. */
function order_delivery_address(array $order): string {
  $stored = trim($order['deliveryAddress'] ?? '');
  if ($stored !== '') return $stored;
  $email = trim($order['customerEmail'] ?? '');
  if ($email !== '') {
    $acct = account_find($email);
    if ($acct) return format_address($acct['address'] ?? '', $acct['city'] ?? '', $acct['pincode'] ?? '');
  }
  return '';
}

/* ----------------------- regular / family stores --------------------------- */

/** Vendor ids this customer has marked as a Regular / Family Store. */
function favorite_store_ids(string $email): array {
  $email = strtolower(trim($email));
  if ($email === '') return [];
  return array_map('intval', array_column(
    db_all("SELECT vendorId FROM favorite_stores WHERE lower(customerEmail) = :e", [':e' => $email]),
    'vendorId'
  ));
}

/** Is this store a Regular / Family Store for this customer? */
function is_favorite_store(string $email, int $vendorId): bool {
  return in_array($vendorId, favorite_store_ids($email), true);
}

/** Toggle a store as the customer's Regular / Family Store; returns the new state. */
function favorite_store_toggle(string $email, int $vendorId): bool {
  $email = strtolower(trim($email));
  if ($email === '' || $vendorId <= 0) return false;
  if (is_favorite_store($email, $vendorId)) {
    db_exec("DELETE FROM favorite_stores WHERE lower(customerEmail) = :e AND vendorId = :v", [':e' => $email, ':v' => $vendorId]);
    return false;
  }
  db_exec("INSERT OR IGNORE INTO favorite_stores (customerEmail, vendorId, created_at) VALUES (:e, :v, :c)",
    [':e' => $email, ':v' => $vendorId, ':c' => date('Y-m-d H:i:s')]);
  return true;
}

/** The customer's Regular / Family Stores as full vendor rows. */
function favorite_stores_list(string $email): array {
  $ids = favorite_store_ids($email);
  if (!$ids) return [];
  $in = implode(',', array_fill(0, count($ids), '?'));
  return db_all("SELECT * FROM vendors WHERE id IN ($in) ORDER BY name", $ids);
}

/* ------------------------------ notifications ------------------------------ */

/** Raise an in-app notification. For admin/superadmin pass $recipient = '' (role-wide). */
function notify(string $role, string $recipient, string $title, string $body = '', string $link = ''): void {
  db_exec("INSERT INTO notifications (role, recipient, title, body, link, is_read, created_at)
           VALUES (:role, :recipient, :title, :body, :link, 0, :created_at)", [
    ':role' => $role,
    ':recipient' => strtolower(trim($recipient)),
    ':title' => $title,
    ':body' => $body,
    ':link' => $link,
    ':created_at' => date('Y-m-d H:i:s'),
  ]);
}

/** Notifications for a person (by role + email), newest first. Admin/superadmin see role-wide ones too. */
function notifications_for(string $role, string $email, int $limit = 30): array {
  $email = strtolower(trim($email));
  return db_all("SELECT * FROM notifications WHERE role = :role AND (recipient = :email OR recipient = '')
                 ORDER BY id DESC LIMIT $limit", [':role' => $role, ':email' => $email]);
}

function notifications_unread_count(string $role, string $email): int {
  $email = strtolower(trim($email));
  return (int) (db_row("SELECT COUNT(*) c FROM notifications WHERE role = :role AND (recipient = :email OR recipient = '') AND is_read = 0",
    [':role' => $role, ':email' => $email])['c'] ?? 0);
}

function notifications_mark_all_read(string $role, string $email): void {
  $email = strtolower(trim($email));
  db_exec("UPDATE notifications SET is_read = 1 WHERE role = :role AND (recipient = :email OR recipient = '')",
    [':role' => $role, ':email' => $email]);
}

/* ------------------------------ return requests ---------------------------- */

/** All return requests, newest first. */
function return_requests_all(): array {
  return db_all("SELECT * FROM return_requests ORDER BY id DESC");
}

/** Return requests raised against a specific order. */
function return_requests_for_order(string $orderId): array {
  return db_all("SELECT * FROM return_requests WHERE orderId = :id ORDER BY id DESC", [':id' => $orderId]);
}

/** How many return requests are still awaiting a decision (for badges). */
function return_requests_pending_count(): int {
  return (int) (db_row("SELECT COUNT(*) AS c FROM return_requests WHERE status = 'Requested'")['c'] ?? 0);
}

/** Create a customer return request. Returns the new request id. */
function return_request_create(array $data): int {
  db_exec("INSERT INTO return_requests (orderId, customerName, customerEmail, item, quantity, reason, status, date)
           VALUES (:oid, :cn, :ce, :item, :qty, :reason, 'Requested', :date)", [
    ':oid'    => $data['orderId'] ?? '',
    ':cn'     => $data['customerName'] ?? '',
    ':ce'     => strtolower(trim($data['customerEmail'] ?? '')),
    ':item'   => $data['item'] ?? '',
    ':qty'    => (int) ($data['quantity'] ?? 1),
    ':reason' => trim($data['reason'] ?? ''),
    ':date'   => date('Y-m-d'),
  ]);
  $newId = db_insert_id();
  // Alert admins that there's a return to review.
  $what = trim(($data['item'] ?? '') . ' · ' . ($data['orderId'] ?? ''), ' ·');
  notify('admin', '', 'New return request', $what, 'admin.php');
  notify('superadmin', '', 'New return request', $what, 'superadmin.php?tab=returns');
  return $newId;
}

/** Update a return request's status (Requested | Approved | Rejected | Refunded). */
function return_request_set_status(int $id, string $status): void {
  $allowed = ['Requested', 'Approved', 'Rejected', 'Refunded'];
  if (!in_array($status, $allowed, true)) return;
  $r = db_row("SELECT * FROM return_requests WHERE id = :id", [':id' => $id]);
  db_exec("UPDATE return_requests SET status = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
  if ($r && !empty($r['customerEmail'])) {
    notify('customer', $r['customerEmail'], "Return $status", ($r['item'] ?? '') . ' for order ' . ($r['orderId'] ?? ''), 'orders.php');
  }
}

/** Persist a vendor's own settings (from the Settings tab) to the database. */
function vendor_settings_update(string $email, string $name, string $phone, string $shopName): void {
  $email = strtolower(trim($email));
  $acct = account_find($email);
  $oldShop = $acct['shopName'] ?? '';
  db_exec("UPDATE accounts SET name = :n, phone = :p, shopName = :s WHERE lower(email) = lower(:e)",
    [':n' => $name, ':p' => $phone, ':s' => $shopName, ':e' => $email]);
  db_exec("UPDATE vendors SET owner = :n, phone = :p, name = :s WHERE lower(email) = lower(:e)",
    [':n' => $name, ':p' => $phone, ':s' => $shopName, ':e' => $email]);
  // Keep products/orders linked if the shop was renamed.
  if ($shopName !== '' && $oldShop !== '' && $shopName !== $oldShop) {
    db_exec("UPDATE products SET vendor = :new WHERE vendor = :old", [':new' => $shopName, ':old' => $oldShop]);
    db_exec("UPDATE orders   SET vendor = :new WHERE vendor = :old", [':new' => $shopName, ':old' => $oldShop]);
  }
}

/* --------------------------- supplier operations --------------------------- */

/** A vendor asks a supplier to restock a product. Shows up on the supplier's
 *  dashboard as a Pending request they can approve or reject. */
function supplier_request_add(string $vendor, string $product, int $quantity, int $amount): void {
  db_exec("INSERT INTO supplier_requests (vendor, product, quantity, amount, status)
           VALUES (:v, :p, :q, :a, 'Pending')",
          [':v' => $vendor, ':p' => $product, ':q' => $quantity, ':a' => $amount]);
  notify('supplier', '', 'New restock request',
         "$vendor requested $quantity units of $product.", 'supplier.php?tab=orders');
}

function supplier_request_set_status(int $id, string $status): void {
  db_exec("UPDATE supplier_requests SET status = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
}

function supplier_product_add(array $data): void {
  db_exec("INSERT INTO supplier_products (name, category, price, stock, supplier)
           VALUES (:name, :category, :price, :stock, :supplier)", [
    ':name'     => $data['name'] ?: 'New Product',
    ':category' => $data['category'] ?: 'Groceries',
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':supplier' => $data['supplier'] ?? '',
  ]);
}

function supplier_product_update(int $id, array $data): void {
  $p = db_row("SELECT * FROM supplier_products WHERE id = :id", [':id' => $id]);
  if (!$p) return;
  db_exec("UPDATE supplier_products SET name = :name, category = :category, price = :price, stock = :stock WHERE id = :id", [
    ':name'     => $data['name'] ?: $p['name'],
    ':category' => $data['category'] ?: $p['category'],
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':id'       => $id,
  ]);
}

function supplier_product_delete(int $id): void {
  db_exec("DELETE FROM supplier_products WHERE id = :id", [':id' => $id]);
}

/* --------------------------------- admin ----------------------------------- */

function approval_resolve(int $id): void {
  db_exec("DELETE FROM approvals WHERE id = :id", [':id' => $id]);
}

/* -------------------------------- delivery --------------------------------- */

function delivery_advance(string $id, string $collectedBy = ''): void {
  $d = db_row("SELECT * FROM deliveries WHERE id = :id", [':id' => $id]);
  if (!$d) return;
  if (($d['status'] ?? '') === 'Assigned') {
    db_exec("UPDATE deliveries SET status = 'Picked Up' WHERE id = :id", [':id' => $id]);
    $o = db_row("SELECT customerEmail FROM orders WHERE id = :id", [':id' => $id]);
    if ($o && !empty($o['customerEmail'])) {
      notify('customer', $o['customerEmail'], "Order $id picked up", "Your delivery partner has your order and is on the way.", 'orders.php');
    }
    return;
  }

  // Completing the delivery.
  $o = db_row("SELECT * FROM orders WHERE id = :id", [':id' => $id]);
  db_exec("INSERT INTO completed_deliveries (id, customer, amount, earnings, time)
           VALUES (:id, :c, :a, :e, :t)", [
    ':id' => $d['id'], ':c' => $d['customer'], ':a' => (int) $d['amount'],
    ':e'  => (int) round(((int) $d['amount']) * DELIVERY_PARTNER_RATE), ':t' => date('h:i A'),
  ]);
  db_exec("DELETE FROM deliveries WHERE id = :id", [':id' => $id]);
  // Mark the order delivered and tell the customer (also settles payment as a fallback).
  order_set_status($id, 'Delivered');

  // Confirm the money to the store. For Cash on Delivery the partner has just
  // collected the cash — record who/when and tell the vendor it was received.
  $amount = (int) ($o['total'] ?? $d['amount']);
  if ($o && order_is_prepaid($o)) {
    notify_order_vendors($o, "Order $id delivered", "₹$amount was prepaid online — no cash to collect.", 'vendor.php?tab=payments');
  } else {
    $by = $collectedBy !== '' ? $collectedBy : (trim((string) ($o['deliveryPartner'] ?? '')) ?: 'Delivery partner');
    payment_collect_cash($id, $by);
    notify_order_vendors($o, "Payment received for $id", "₹$amount cash collected on delivery by $by.", 'vendor.php?tab=payments');
  }
}
