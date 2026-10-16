<?php
// ---------------------------------------------------------------------------
// Central configuration + secrets loader (Razorpay payment keys live here).
//
// IMPORTANT: secrets must NEVER be committed to git. This repo auto-deploys to
// the public live site, and the GitHub repo may be public, so a key pasted into
// a tracked file would be exposed to the world. Keys are therefore loaded, in
// priority order, from:
//   1) Environment variables      RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET
//   2) includes/config.local.php  (git-ignored; made locally, or written by the
//                                  deploy workflow from GitHub Actions secrets)
//
// If NEITHER is set, the site still works: Cash on Delivery is always available,
// and online methods fall back to the previous demo behaviour until keys are added.
// See includes/config.sample.php for the file format.
// ---------------------------------------------------------------------------

/** Load the optional, git-ignored local config file once. */
function app_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $cfg = [];
  $local = __DIR__ . '/config.local.php';
  if (is_file($local)) {
    $loaded = include $local;
    if (is_array($loaded)) $cfg = $loaded;
  }
  return $cfg;
}

/** Read one config value: environment variable first, then config.local.php. */
function config_value(string $key, string $envKey = ''): string {
  if ($envKey !== '') {
    $env = getenv($envKey);
    if ($env !== false && trim($env) !== '') return trim($env);
  }
  $cfg = app_config();
  return isset($cfg[$key]) ? trim((string) $cfg[$key]) : '';
}

/* ------------------------------- Razorpay -------------------------------- */

function razorpay_key_id(): string {
  return config_value('razorpay_key_id', 'RAZORPAY_KEY_ID');
}

function razorpay_key_secret(): string {
  return config_value('razorpay_key_secret', 'RAZORPAY_KEY_SECRET');
}

/** True only when BOTH Razorpay keys are configured. */
function razorpay_enabled(): bool {
  return razorpay_key_id() !== '' && razorpay_key_secret() !== '';
}

/** Online (prepaid) payment methods that go through the Razorpay window. */
function online_payment_methods(): array {
  return ['UPI', 'Card', 'Net Banking', 'Wallet'];
}

/** True if a chosen payment method is an online (prepaid) one. */
function is_online_payment(string $method): bool {
  return in_array($method, online_payment_methods(), true);
}

/**
 * Create a Razorpay Order via the REST API so the browser can pay against it.
 * $amountRupees is in whole rupees (converted to paise here, as Razorpay requires).
 * Returns the decoded order array (contains 'id', the rzp order id) or null on failure.
 */
function razorpay_create_order(int $amountRupees, string $receipt, array $notes = []): ?array {
  if (!razorpay_enabled() || $amountRupees <= 0) return null;

  $payload = json_encode([
    'amount'          => $amountRupees * 100, // paise
    'currency'        => 'INR',
    'receipt'         => substr($receipt, 0, 40),
    'notes'           => $notes,
    'payment_capture' => 1, // auto-capture on success
  ]);

  $ch = curl_init('https://api.razorpay.com/v1/orders');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_USERPWD        => razorpay_key_id() . ':' . razorpay_key_secret(),
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
  ]);
  $res  = curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($res === false || $code < 200 || $code >= 300) {
    error_log('Razorpay create order failed: HTTP ' . $code . ' ' . $err . ' ' . (is_string($res) ? $res : ''));
    return null;
  }
  $data = json_decode($res, true);
  return (is_array($data) && !empty($data['id'])) ? $data : null;
}

/**
 * Verify the payment signature Razorpay returns to the browser after a successful
 * payment. This is what proves the payment is real and untampered — never skip it.
 */
function razorpay_verify_signature(string $orderId, string $paymentId, string $signature): bool {
  if ($orderId === '' || $paymentId === '' || $signature === '') return false;
  if (!razorpay_enabled()) return false;
  $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, razorpay_key_secret());
  return hash_equals($expected, $signature);
}
