<?php
require_once __DIR__ . '/includes/init.php';

$user = require_role('customer');

$items = cart_items();
if (!$items) {
  set_flash('Your cart is empty.', 'error');
  header('Location: cart.php');
  exit;
}
if (!profile_complete($user)) {
  set_flash('Please add your delivery address before checking out.', 'error');
  header('Location: profile.php');
  exit;
}

$total   = cart_total_price();
$address = format_address($user['address'] ?? '', $user['city'] ?? '', $user['pincode'] ?? '');

$methods = [
  ['id' => 'Cash on Delivery', 'icon' => 'wallet',      'desc' => 'Pay with cash when your order arrives'],
  ['id' => 'UPI',              'icon' => 'smartphone',   'desc' => 'Google Pay, PhonePe, Paytm & more'],
  ['id' => 'Card',             'icon' => 'credit-card',  'desc' => 'Credit or debit card'],
  ['id' => 'Net Banking',      'icon' => 'building-2',   'desc' => 'All major banks supported'],
  ['id' => 'Wallet',           'icon' => 'wallet',       'desc' => 'Paytm, PhonePe, Amazon Pay'],
];

$brand = brand_name();
$page_title = 'Checkout · ' . $brand;
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gray-50">
  <header class="bg-white border-b sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-3 md:py-4 flex items-center justify-between gap-2">
      <a href="index.php" class="flex items-center gap-2 min-w-0 shrink">
        <i data-lucide="shopping-cart" class="w-7 h-7 md:w-8 md:h-8 text-green-600 shrink-0"></i>
        <h1 class="text-lg md:text-2xl font-bold text-green-600 truncate"><?= e($brand) ?></h1>
      </a>
      <a href="cart.php" class="px-2.5 md:px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 shrink-0 text-sm md:text-base">← Back to cart</a>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 py-10">
    <h2 class="text-3xl font-bold mb-8">Checkout</h2>
    <form method="post" action="actions.php" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <input type="hidden" name="do" value="checkout" />

      <!-- Left: address + payment -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Delivery address -->
        <div class="bg-white rounded-xl border p-6">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-lg flex items-center gap-2"><i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i>Delivery address</h3>
            <a href="profile.php" class="text-sm text-green-600 hover:underline">Change</a>
          </div>
          <p class="font-medium"><?= e($user['name']) ?><?= !empty($user['phone']) ? ' · ' . e($user['phone']) : '' ?></p>
          <p class="text-gray-600 text-sm mt-1"><?= e($address) ?></p>
        </div>

        <!-- Delivery option -->
        <div class="bg-white rounded-xl border p-6">
          <h3 class="font-semibold text-lg mb-1">Choose Your Delivery Option</h3>
          <p class="text-gray-600 mb-4">How would you like to receive your order?</p>
          <div class="grid gap-3 md:grid-cols-2">
            <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
              <input type="radio" name="delivery_option" value="Walk & Collect" class="mt-1 accent-green-600" checked />
              <div class="flex-1">
                <div class="flex items-center gap-2 font-semibold text-base">
                  <span class="text-xl">🚶</span>
                  <span>Walk & Collect</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">Walk to the store and collect your order yourself.</p>
              </div>
            </label>

            <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
              <input type="radio" name="delivery_option" value="Choose Delivery Partner" class="mt-1 accent-green-600" />
              <div class="flex-1">
                <div class="flex items-center gap-2 font-semibold text-base">
                  <span class="text-xl">🚚</span>
                  <span>Choose Delivery Partner</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">Select a delivery partner to have your order delivered to your location.</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Payment method -->
        <div class="bg-white rounded-xl border p-6">
          <h3 class="font-semibold text-lg mb-4 flex items-center gap-2"><i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i>Payment method</h3>
          <div class="space-y-3">
            <?php foreach ($methods as $i => $m): ?>
              <label class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                <input type="radio" name="payment" value="<?= e($m['id']) ?>" class="accent-green-600 pay-radio" data-method="<?= e($m['id']) ?>" <?= $i === 0 ? 'checked' : '' ?> />
                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 text-gray-600"></i>
                <div class="flex-1">
                  <p class="font-medium text-sm"><?= e($m['id']) ?></p>
                  <p class="text-xs text-gray-500"><?= e($m['desc']) ?></p>
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <!-- Conditional detail panels (demo only, not charged) -->
          <div class="mt-4 space-y-4">
            <div class="pay-panel hidden" data-method="UPI">
              <label class="block text-sm font-medium mb-1">UPI ID</label>
              <input name="upi_id" placeholder="yourname@upi" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div class="pay-panel hidden" data-method="Card">
              <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2"><label class="block text-sm font-medium mb-1">Card number</label><input name="card_no" placeholder="1234 5678 9012 3456" maxlength="19" class="w-full border rounded-lg px-3 py-2" /></div>
                <div><label class="block text-sm font-medium mb-1">Expiry</label><input name="card_exp" placeholder="MM/YY" maxlength="5" class="w-full border rounded-lg px-3 py-2" /></div>
                <div><label class="block text-sm font-medium mb-1">CVV</label><input name="card_cvv" type="password" placeholder="•••" maxlength="4" class="w-full border rounded-lg px-3 py-2" /></div>
              </div>
            </div>
            <div class="pay-panel hidden" data-method="Net Banking">
              <label class="block text-sm font-medium mb-1">Select bank</label>
              <select name="bank" class="w-full border rounded-lg px-3 py-2"><option>State Bank of India</option><option>HDFC Bank</option><option>ICICI Bank</option><option>Axis Bank</option><option>Kotak Mahindra</option><option>Punjab National Bank</option></select>
            </div>
            <div class="pay-panel hidden" data-method="Wallet">
              <label class="block text-sm font-medium mb-1">Select wallet</label>
              <select name="wallet" class="w-full border rounded-lg px-3 py-2"><option>Paytm</option><option>PhonePe</option><option>Amazon Pay</option><option>Mobikwik</option></select>
            </div>
            <p class="pay-panel hidden text-xs text-gray-500" data-method="Cash on Delivery">You'll pay ₹<?= e($total) ?> in cash when the order is delivered.</p>
          </div>
        </div>
      </div>

      <!-- Right: order summary -->
      <div class="space-y-6">
        <div class="bg-white rounded-xl border p-6">
          <h3 class="font-semibold text-lg mb-4">Order summary</h3>
          <div class="space-y-3 mb-4">
            <?php foreach ($items as $it): ?>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600"><?= e($it['name']) ?> <span class="text-gray-400">× <?= e($it['quantity']) ?></span></span>
                <span class="font-medium">₹<?= e($it['price'] * $it['quantity']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="flex justify-between text-sm border-t pt-3"><span class="text-gray-600">Subtotal</span><span>₹<?= e($total) ?></span></div>
          <div class="flex justify-between text-sm mt-1"><span class="text-gray-600">Delivery</span><span class="text-green-600">Free</span></div>
          <div class="flex justify-between font-bold text-lg border-t mt-3 pt-3"><span>Total</span><span class="text-green-600">₹<?= e($total) ?></span></div>
          <button class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg mt-5 font-medium">Place Order</button>
          <p class="text-xs text-gray-400 text-center mt-3">Demo checkout — no real payment is processed.</p>
        </div>
      </div>
    </form>
  </main>
</div>

<script>
  function syncPayPanels() {
    var sel = document.querySelector('.pay-radio:checked');
    var method = sel ? sel.getAttribute('data-method') : '';
    document.querySelectorAll('.pay-panel').forEach(function (p) {
      p.classList.toggle('hidden', p.getAttribute('data-method') !== method);
    });
  }
  document.querySelectorAll('.pay-radio').forEach(function (r) { r.addEventListener('change', syncPayPanels); });
  syncPayPanels();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
