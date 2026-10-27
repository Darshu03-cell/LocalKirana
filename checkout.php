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

// Returning from a cancelled/closed Razorpay window — reassure the customer.
if (!empty($_GET['cancelled'])) {
  set_flash('Payment cancelled — you have not been charged. Your cart is saved.', 'error');
}

$total   = cart_total_price();
$address = format_address($user['address'] ?? '', $user['city'] ?? '', $user['pincode'] ?? '');

// Active, ID-verified delivery partners the customer can pick for Home Delivery.
$partners = assignable_delivery_partners();
// Offers this cart qualifies for (best saving first).
$availableOffers = eligible_offers($total);
// Group the cart by store so a multi-store order is shown clearly.
$byStore = [];
foreach ($items as $it) { $byStore[$it['vendor'] ?: 'Local store'][] = $it; }
// Centralized Pickup collection centre (admin-configured) + its handling fee.
$center     = pickup_center();
$pickupFee  = (int) $center['fee'];
$multiStore = count($byStore) > 1;

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
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
              <input type="radio" name="delivery_option" value="Self Pickup" class="mt-1 accent-green-600 delivery-radio" data-option="Self Pickup" checked />
              <div class="flex-1">
                <div class="flex items-center gap-2 font-semibold text-base">
                  <span class="text-xl">🚶</span>
                  <span>Individual Pickup</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">Collect from each store yourself. No delivery partner needed.</p>
              </div>
            </label>

            <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
              <input type="radio" name="delivery_option" value="Centralized Pickup" class="mt-1 accent-green-600 delivery-radio" data-option="Centralized Pickup" />
              <div class="flex-1">
                <div class="flex items-center gap-2 font-semibold text-base">
                  <span class="text-xl">🏬</span>
                  <span>Centralized Pickup</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">Collect your whole order from one collection centre<?= $multiStore ? ' — ideal for multi-shop orders' : '' ?>.<?= $pickupFee > 0 ? ' Handling fee ₹' . $pickupFee . '.' : '' ?></p>
              </div>
            </label>

            <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
              <input type="radio" name="delivery_option" value="Home Delivery" class="mt-1 accent-green-600 delivery-radio" data-option="Home Delivery" <?= $partners ? '' : 'disabled' ?> />
              <div class="flex-1">
                <div class="flex items-center gap-2 font-semibold text-base">
                  <span class="text-xl">🚚</span>
                  <span>Home Delivery</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">A delivery partner brings your order to your address.</p>
                <?php if (!$partners): ?><p class="text-xs text-amber-700 mt-1">Not available right now: no verified delivery partner is free. Please choose a pickup option.</p><?php endif; ?>
              </div>
            </label>
          </div>

          <!-- Store addresses (shown only for Individual / Self Pickup) -->
          <div id="selfpickup-panel" class="mt-4 rounded-lg border bg-gray-50 p-4 space-y-3">
            <p class="text-sm font-medium flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-green-600"></i>Collect from <?= count($byStore) > 1 ? 'these stores' : 'this store' ?></p>
            <?php foreach (array_keys($byStore) as $storeName): $sv = vendor_by_name($storeName); $sAddr = $sv ? format_address($sv['address'] ?? '', $sv['city'] ?? '') : ''; ?>
              <div class="flex items-start justify-between gap-3 flex-wrap text-sm">
                <div><p class="font-medium"><?= e($storeName) ?></p><p class="text-gray-600"><?= e($sAddr ?: 'Address not added yet') ?><?= $sv && !empty($sv['phone']) ? ' · ' . e($sv['phone']) : '' ?></p></div>
                <?php if ($sv && ($sAddr !== '' || $sv['lat'] !== null)): ?>
                  <a href="<?= e(directions_url($sv['lat'] !== null ? (float) $sv['lat'] : null, $sv['lng'] !== null ? (float) $sv['lng'] : null, $storeName . ', ' . $sAddr)) ?>" target="_blank" rel="noopener" class="text-green-700 font-medium hover:underline inline-flex items-center gap-1"><i data-lucide="navigation" class="w-3.5 h-3.5"></i>Directions</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <p class="text-xs text-gray-500">We'll tell you when the store accepts your order and when it's ready for collection.</p>
          </div>

          <!-- Collection-centre details (shown only for Centralized Pickup) -->
          <div id="pickup-panel" class="hidden mt-4 rounded-lg border bg-gray-50 p-4">
            <p class="text-sm font-medium flex items-center gap-2"><i data-lucide="building-2" class="w-4 h-4 text-green-600"></i><?= e($center['name']) ?></p>
            <?php if ($center['address'] !== ''): ?><p class="text-sm text-gray-600 mt-1 flex items-start gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i><?= e($center['address']) ?></p><?php endif; ?>
            <p class="text-xs text-gray-500 mt-2">Each shop prepares its items and hands them to the centre. We'll notify you when your complete order is ready to collect.<?= $pickupFee > 0 ? ' A handling fee of ₹' . $pickupFee . ' applies.' : '' ?></p>
          </div>

          <!-- Delivery-partner picker (shown only for Home Delivery) -->
          <div id="partner-panel" class="hidden mt-4">
            <?php if ($partners): ?>
              <label class="block text-sm font-medium mb-1">Choose a delivery partner</label>
              <select name="delivery_partner_id" id="partner-select" class="w-full border rounded-lg px-3 py-2">
                <option value="">— Select a partner —</option>
                <?php foreach ($partners as $p): ?>
                  <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> ✓ · <?= e($p['vehicle'] ?: 'Bike') ?><?= (float) ($p['rating'] ?? 0) > 0 ? ' · ★ ' . e(number_format((float) $p['rating'], 1)) : '' ?></option>
                <?php endforeach; ?>
              </select>
              <p class="text-xs text-gray-500 mt-1">The partner will be notified and your order appears in their deliveries.</p>
            <?php else: ?>
              <p class="text-sm text-amber-600">No verified delivery partners are available right now — please choose a pickup option.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Offers -->
        <div class="bg-white rounded-xl border p-6">
          <h3 class="font-semibold text-lg mb-1 flex items-center gap-2"><i data-lucide="badge-percent" class="w-5 h-5 text-green-600"></i>Apply an Offer</h3>
          <?php if ($availableOffers): ?>
            <p class="text-gray-600 mb-4">Pick an offer to apply to this order.</p>
            <div class="space-y-3">
              <label class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                <input type="radio" name="offer_id" value="0" class="accent-green-600 offer-radio" data-amount="0" checked />
                <span class="font-medium text-sm">No offer</span>
              </label>
              <?php foreach ($availableOffers as $o): ?>
                <label class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                  <input type="radio" name="offer_id" value="<?= e($o['id']) ?>" class="mt-0.5 accent-green-600 offer-radio" data-amount="<?= (int) $o['_amount'] ?>" />
                  <div class="flex-1">
                    <p class="font-medium text-sm"><?= e($o['title']) ?> <span class="text-green-700">· <?= e($o['discount'] ?: offer_discount_text($o['discount_type'] ?? 'percent', (int) ($o['discount_value'] ?? 0))) ?></span></p>
                    <p class="text-xs text-gray-500"><?php if (!empty($o['code'])): ?>Code <?= e($o['code']) ?> · <?php endif; ?>You save ₹<?= (int) $o['_amount'] ?><?= (int) ($o['min_order'] ?? 0) > 0 ? ' · min order ₹' . (int) $o['min_order'] : '' ?></p>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-500">No offers apply to your current cart. Add more items or check back later.</p>
            <input type="hidden" name="offer_id" value="0" />
          <?php endif; ?>
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

          <!-- Detail notes. Card / UPI / bank / wallet details are entered securely
               inside the Razorpay window — we never collect or store them ourselves
               (that keeps the site safe and PCI-compliant). -->
          <div class="mt-4 space-y-3">
            <?php if (razorpay_enabled()): ?>
              <p class="pay-online-note hidden text-sm text-gray-600 flex items-start gap-2">
                <i data-lucide="lock" class="w-4 h-4 mt-0.5 text-green-600 flex-shrink-0"></i>
                <span>You'll complete this payment securely in the Razorpay window after you tap the button below. UPI, cards, net banking and wallets are all supported there.</span>
              </p>
            <?php else: ?>
              <p class="pay-online-note hidden text-sm text-amber-600 flex items-start gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                <span>Online payment isn't fully set up yet. You can still place this order now, or choose Cash on Delivery.</span>
              </p>
            <?php endif; ?>
            <p class="pay-cod-note hidden text-xs text-gray-500">You'll pay ₹<?= e($total) ?> in cash when the order is delivered.</p>
          </div>
        </div>
      </div>

      <!-- Right: order summary -->
      <div class="space-y-6">
        <div class="bg-white rounded-xl border p-6">
          <h3 class="font-semibold text-lg mb-4">Order summary</h3>
          <?php if (count($byStore) > 1): ?>
            <p class="text-xs text-gray-500 mb-3 inline-flex items-center gap-1"><i data-lucide="store" class="w-3.5 h-3.5"></i>Items from <?= count($byStore) ?> stores in one order</p>
          <?php endif; ?>
          <div class="space-y-4 mb-4">
            <?php foreach ($byStore as $storeName => $storeItems): ?>
              <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5 flex items-center gap-1"><i data-lucide="store" class="w-3.5 h-3.5"></i><?= e($storeName) ?></p>
                <div class="space-y-2">
                  <?php foreach ($storeItems as $it): ?>
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-600"><?= e($it['name']) ?> <span class="text-gray-400">× <?= e($it['quantity']) ?></span></span>
                      <span class="font-medium">₹<?= e($it['price'] * $it['quantity']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="flex justify-between text-sm border-t pt-3"><span class="text-gray-600">Subtotal</span><span>₹<?= e($total) ?></span></div>
          <div id="discount-row" class="flex justify-between text-sm mt-1 hidden"><span class="text-gray-600">Offer discount</span><span class="text-green-600">− ₹<span id="discount-amt">0</span></span></div>
          <div class="flex justify-between text-sm mt-1"><span class="text-gray-600">Delivery</span><span class="text-green-600">Free</span></div>
          <div id="pickup-fee-row" class="flex justify-between text-sm mt-1 hidden"><span class="text-gray-600">Collection handling</span><span>₹<span id="pickup-fee-amt"><?= (int) $pickupFee ?></span></span></div>
          <div class="flex justify-between font-bold text-lg border-t mt-3 pt-3"><span>Total payable</span><span class="text-green-600">₹<span id="total-payable"><?= e($total) ?></span></span></div>
          <button id="place-btn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg mt-5 font-medium">Place Order</button>
          <?php if (razorpay_enabled()): ?>
            <p class="text-xs text-gray-400 text-center mt-3 flex items-center justify-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i>Online payments are secured by Razorpay. Cash on Delivery also available.</p>
          <?php else: ?>
            <p class="text-xs text-gray-400 text-center mt-3">Cash on Delivery is ready to use. Online payment is being set up.</p>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </main>
</div>

<script>
  var ONLINE_METHODS = ['UPI', 'Card', 'Net Banking', 'Wallet'];
  var RZP_ENABLED = <?= razorpay_enabled() ? 'true' : 'false' ?>;

  function currentMethod() {
    var sel = document.querySelector('.pay-radio:checked');
    return sel ? sel.getAttribute('data-method') : '';
  }

  function updatePlaceButton() {
    var btn = document.getElementById('place-btn');
    if (!btn) return;
    var method = currentMethod();
    var online = ONLINE_METHODS.indexOf(method) !== -1;
    var t = document.getElementById('total-payable');
    var amt = t ? t.textContent : '';
    if (online && RZP_ENABLED) {
      btn.textContent = 'Pay ₹' + amt + ' securely';
    } else if (method === 'Cash on Delivery') {
      btn.textContent = 'Place Order (Cash on Delivery)';
    } else {
      btn.textContent = 'Place Order';
    }
  }

  function syncPayPanels() {
    var method = currentMethod();
    var online = ONLINE_METHODS.indexOf(method) !== -1;
    var on  = document.querySelector('.pay-online-note');
    var cod = document.querySelector('.pay-cod-note');
    if (on)  on.classList.toggle('hidden', !online);
    if (cod) cod.classList.toggle('hidden', method !== 'Cash on Delivery');
    updatePlaceButton();
  }
  document.querySelectorAll('.pay-radio').forEach(function (r) { r.addEventListener('change', syncPayPanels); });
  syncPayPanels();

  // Show the right panel per delivery option: partner picker for Home Delivery,
  // collection-centre details for Centralized Pickup.
  var partnerPanel  = document.getElementById('partner-panel');
  var partnerSelect = document.getElementById('partner-select');
  var pickupPanel   = document.getElementById('pickup-panel');
  var PICKUP_FEE    = <?= (int) $pickupFee ?>;

  function currentDeliveryOption() {
    var sel = document.querySelector('.delivery-radio:checked');
    return sel ? sel.getAttribute('data-option') : '';
  }
  function currentPickupFee() {
    return currentDeliveryOption() === 'Centralized Pickup' ? PICKUP_FEE : 0;
  }
  function syncDelivery() {
    var opt = currentDeliveryOption();
    if (partnerPanel)  partnerPanel.classList.toggle('hidden', opt !== 'Home Delivery');
    if (partnerSelect) partnerSelect.required = opt === 'Home Delivery';
    if (pickupPanel)   pickupPanel.classList.toggle('hidden', opt !== 'Centralized Pickup');
    var selfPanel = document.getElementById('selfpickup-panel');
    if (selfPanel)     selfPanel.classList.toggle('hidden', opt !== 'Self Pickup');
    syncOffer(); // delivery choice changes the handling fee → recompute the total
  }

  // Live offer discount + handling fee + total payable.
  var SUBTOTAL = <?= (int) $total ?>;
  function syncOffer() {
    var sel = document.querySelector('.offer-radio:checked');
    var amt = sel ? parseInt(sel.getAttribute('data-amount') || '0', 10) : 0;
    if (amt > SUBTOTAL) amt = SUBTOTAL;
    var row = document.getElementById('discount-row');
    if (row) row.classList.toggle('hidden', amt <= 0);
    var a = document.getElementById('discount-amt'); if (a) a.textContent = amt;
    var fee = currentPickupFee();
    var feeRow = document.getElementById('pickup-fee-row'); if (feeRow) feeRow.classList.toggle('hidden', fee <= 0);
    var t = document.getElementById('total-payable'); if (t) t.textContent = (SUBTOTAL - amt + fee);
    updatePlaceButton();
  }
  document.querySelectorAll('.delivery-radio').forEach(function (r) { r.addEventListener('change', syncDelivery); });
  document.querySelectorAll('.offer-radio').forEach(function (r) { r.addEventListener('change', syncOffer); });
  syncDelivery();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
