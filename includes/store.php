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

/* --------------------------------- getters --------------------------------- */
function products(): array             { return db_all("SELECT * FROM products ORDER BY id"); }
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
function completed_deliveries(): array { return db_all("SELECT id, customer, amount, earnings, time FROM completed_deliveries ORDER BY cid"); }
function approvals(): array            { return db_all("SELECT * FROM approvals ORDER BY id"); }

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
  db_exec("INSERT INTO products (name, category, price, stock, image, vendor, rating, unit)
           VALUES (:name, :category, :price, :stock, :image, :vendor, 0, :unit)", [
    ':name'     => $data['name'] ?: 'New Product',
    ':category' => $data['category'] ?: 'Groceries',
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':image'    => $data['image'] ?: 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=400',
    ':vendor'   => $data['vendor'] ?? '',
    ':unit'     => $data['unit'] ?: '1 unit',
  ]);
}

function product_update(int $id, array $data): void {
  $p = db_row("SELECT * FROM products WHERE id = :id", [':id' => $id]);
  if (!$p) return;
  db_exec("UPDATE products SET name = :name, category = :category, price = :price, stock = :stock, unit = :unit, image = :image WHERE id = :id", [
    ':name'     => $data['name'] ?: $p['name'],
    ':category' => $data['category'] ?: $p['category'],
    ':price'    => (int) ($data['price'] ?? 0),
    ':stock'    => (int) ($data['stock'] ?? 0),
    ':unit'     => !empty($data['unit']) ? $data['unit'] : $p['unit'],
    // Keep the existing image unless a new one was provided (uploaded).
    ':image'    => !empty($data['image']) ? $data['image'] : $p['image'],
    ':id'       => $id,
  ]);
}

function product_delete(int $id): void {
  db_exec("DELETE FROM products WHERE id = :id", [':id' => $id]);
}

/* -------------------------------- orders ----------------------------------- */

function order_set_status(string $id, string $status): void {
  db_exec("UPDATE orders SET status = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
}

function order_set_payment_status(string $id, string $status): void {
  $status = $status === 'Paid' ? 'Paid' : 'Unpaid';
  db_exec("UPDATE orders SET paymentStatus = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
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

/** Create an order from the current cart, then empty the cart. */
function place_order(?array $user = null, string $payment = 'Cash on Delivery', string $deliveryOption = 'Walk & Collect'): string {
  $items = cart_items();
  if (!$items) return '';
  $max = (int) (db_row("SELECT MAX(CAST(SUBSTR(id, 5) AS INTEGER)) AS m FROM orders")['m'] ?? 0);
  $num = '#ORD' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
  $first = $items[array_key_first($items)];
  $vendorName = $first['vendor'] ?? '';
  $vendor = db_row("SELECT id FROM vendors WHERE name = :n", [':n' => $vendorName]);
  // Build a full delivery address from the customer's saved profile.
  $addr = format_address($user['address'] ?? '', $user['city'] ?? '', $user['pincode'] ?? '');
  // Prepaid methods are Paid at checkout; Cash on Delivery is Unpaid until collected.
  $payStatus = ($payment === 'Cash on Delivery') ? 'Unpaid' : 'Paid';
  $deliveryOption = in_array($deliveryOption, ['Walk & Collect', 'Choose Delivery Partner', 'Choose a Delivery Partner'], true)
    ? $deliveryOption
    : 'Walk & Collect';
  db_exec("INSERT INTO orders (id, customerId, customerName, customerEmail, vendor, vendorId, items, total, status, date, paymentMethod, paymentStatus, deliveryAddress, deliveryOption)
           VALUES (:id, :cid, :cn, :cemail, :v, :vid, :items, :total, 'Pending', :date, :pay, :pstatus, :addr, :dopt)", [
    ':id'        => $num,
    ':cid'       => $user['id'] ?? 0,
    ':cn'        => $user['name'] ?? 'Guest',
    ':cemail'    => $user['email'] ?? '',
    ':v'         => $vendorName,
    ':vid'       => $vendor['id'] ?? 0,
    ':items'     => cart_total_items(),
    ':total'     => cart_total_price(),
    ':date'      => date('Y-m-d'),
    ':pay'       => $payment,
    ':pstatus'   => $payStatus,
    ':addr'      => $addr,
    ':dopt'      => $deliveryOption,
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
  cart_clear();
  return $num;
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
  return db_insert_id();
}

/** Update a return request's status (Requested | Approved | Rejected | Refunded). */
function return_request_set_status(int $id, string $status): void {
  $allowed = ['Requested', 'Approved', 'Rejected', 'Refunded'];
  if (!in_array($status, $allowed, true)) return;
  db_exec("UPDATE return_requests SET status = :s WHERE id = :id", [':s' => $status, ':id' => $id]);
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

function delivery_advance(string $id): void {
  $d = db_row("SELECT * FROM deliveries WHERE id = :id", [':id' => $id]);
  if (!$d) return;
  if (($d['status'] ?? '') === 'Assigned') {
    db_exec("UPDATE deliveries SET status = 'Picked Up' WHERE id = :id", [':id' => $id]);
  } else {
    db_exec("INSERT INTO completed_deliveries (id, customer, amount, earnings, time)
             VALUES (:id, :c, :a, :e, :t)", [
      ':id' => $d['id'], ':c' => $d['customer'], ':a' => (int) $d['amount'],
      ':e'  => (int) round(((int) $d['amount']) * 0.08), ':t' => date('h:i A'),
    ]);
    db_exec("DELETE FROM deliveries WHERE id = :id", [':id' => $id]);
  }
}
