<?php
// SQLite database layer. The whole database is a single file: data/localkirana.sqlite
// (portable — copy the file to back up or move the app). Tables are created on first use.

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dir = __DIR__ . '/../data';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);

  $pdo = new PDO('sqlite:' . $dir . '/localkirana.sqlite');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec('PRAGMA foreign_keys = ON');

  db_init($pdo);
  return $pdo;
}

function db_init(PDO $pdo): void {
  // Column names match the array keys the app already uses, so rows map 1:1.
  $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
    email TEXT PRIMARY KEY, role TEXT, name TEXT, shopName TEXT, phone TEXT,
    vendorId INTEGER, password_hash TEXT, created_at TEXT,
    address TEXT, city TEXT, pincode TEXT
  )");
  // Migrations for databases created before these columns existed.
  foreach (['address', 'city', 'pincode'] as $col) {
    try { $pdo->exec("ALTER TABLE accounts ADD COLUMN $col TEXT"); } catch (Throwable $e) {}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (skey TEXT PRIMARY KEY, svalue TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, owner TEXT, email TEXT, address TEXT,
    city TEXT, phone TEXT, rating REAL, distance TEXT, verified INTEGER, image TEXT,
    deliveryTime TEXT, totalProducts INTEGER, totalOrders INTEGER
  )");
  // Migration: a separate "hygiene certified" flag (admin-verified), shown to customers.
  try { $pdo->exec("ALTER TABLE vendors ADD COLUMN hygiene_verified INTEGER"); } catch (Throwable $e) {}
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, category TEXT, price INTEGER, stock INTEGER,
    image TEXT, vendor TEXT, rating REAL, unit TEXT
  )");
  // Migrations: richer product details (brand, expiry) + a hygiene/quality flag.
  foreach (['brand TEXT', 'expiry TEXT', 'hygiene_verified INTEGER'] as $col) {
    try { $pdo->exec("ALTER TABLE products ADD COLUMN $col"); } catch (Throwable $e) {}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, description TEXT, discount TEXT,
    code TEXT, start_date TEXT, end_date TEXT, created_by TEXT, created_at TEXT
  )");
  // Migrations: structured discounts so offers can actually be applied at checkout.
  foreach (['discount_type TEXT', 'discount_value INTEGER', 'min_order INTEGER'] as $col) {
    try { $pdo->exec("ALTER TABLE offers ADD COLUMN $col"); } catch (Throwable $e) {}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS product_catalog (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, category TEXT, image TEXT, unit TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id TEXT PRIMARY KEY, customerId INTEGER, customerName TEXT, customerEmail TEXT, vendor TEXT, vendorId INTEGER,
    items INTEGER, total INTEGER, status TEXT, date TEXT, paymentMethod TEXT, paymentStatus TEXT, deliveryAddress TEXT,
    deliveryOption TEXT
  )");
  // Migrations for databases created before these columns existed.
  try { $pdo->exec("ALTER TABLE orders ADD COLUMN customerEmail TEXT"); } catch (Throwable $e) {}
  try { $pdo->exec("ALTER TABLE orders ADD COLUMN paymentStatus TEXT"); } catch (Throwable $e) {}
  try { $pdo->exec("ALTER TABLE orders ADD COLUMN deliveryOption TEXT"); } catch (Throwable $e) {}
  // Offers applied + delivery-partner assignment captured on the order.
  foreach (['discount INTEGER', 'offerCode TEXT', 'deliveryPartnerId INTEGER', 'deliveryPartner TEXT'] as $col) {
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN $col"); } catch (Throwable $e) {}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, contact TEXT, email TEXT, phone TEXT,
    productsSupplied INTEGER, totalVendors INTEGER, rating REAL, verified INTEGER
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_partners (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, phone TEXT, vehicle TEXT,
    totalDeliveries INTEGER, rating REAL, status TEXT, earnings INTEGER
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, category TEXT, price INTEGER, stock INTEGER, supplier TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, vendor TEXT, product TEXT, quantity INTEGER, amount INTEGER, status TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS deliveries (
    id TEXT PRIMARY KEY, customer TEXT, address TEXT, amount INTEGER, distance TEXT, otp TEXT, status TEXT
  )");
  // Migration: which delivery partner a delivery is assigned to.
  try { $pdo->exec("ALTER TABLE deliveries ADD COLUMN partnerId INTEGER"); } catch (Throwable $e) {}
  $pdo->exec("CREATE TABLE IF NOT EXISTS completed_deliveries (
    cid INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT, customer TEXT, amount INTEGER, earnings INTEGER, time TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, type TEXT, action TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
    oiid INTEGER PRIMARY KEY AUTOINCREMENT, orderId TEXT, name TEXT, price INTEGER,
    quantity INTEGER, unit TEXT, vendor TEXT
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS return_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, orderId TEXT, customerName TEXT, customerEmail TEXT,
    item TEXT, quantity INTEGER, reason TEXT, status TEXT, date TEXT
  )");
  // Stores a customer marks as their Regular / Family Store.
  $pdo->exec("CREATE TABLE IF NOT EXISTS favorite_stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT, customerEmail TEXT, vendorId INTEGER, created_at TEXT
  )");
  try { $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_fav_unique ON favorite_stores (customerEmail, vendorId)"); } catch (Throwable $e) {}
  // In-app notifications. recipient = the person's email; for platform-wide
  // admin/superadmin alerts recipient is '' and we match on role instead.
  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, role TEXT, recipient TEXT, title TEXT, body TEXT,
    link TEXT, is_read INTEGER, created_at TEXT
  )");
  try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notif_inbox ON notifications (role, recipient, is_read)"); } catch (Throwable $e) {}
}

/* --------------------------- tiny query helpers --------------------------- */

function db_all(string $sql, array $params = []): array {
  $st = db()->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

function db_row(string $sql, array $params = []): ?array {
  $st = db()->prepare($sql);
  $st->execute($params);
  $row = $st->fetch();
  return $row ?: null;
}

function db_exec(string $sql, array $params = []): void {
  db()->prepare($sql)->execute($params);
}

function db_insert_id(): int {
  return (int) db()->lastInsertId();
}
