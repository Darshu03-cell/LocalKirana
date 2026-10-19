<?php
require_once __DIR__ . '/includes/init.php';

$user = require_role('customer');
$myOrders = customer_orders($user['email'] ?? '');
$myNotifs = notifications_for('customer', $user['email'] ?? '', 15);
$unreadNotifs = notifications_unread_count('customer', $user['email'] ?? '');

// Live-tracking data for Home Delivery orders still on their way. Each entry
// drives one map (rendered with Leaflet + OpenStreetMap at the bottom of the page).
$trackPayload = [];

// Payment history (stored transactions) + the just-paid order, if we arrived here
// straight from checkout (?paid=#ORDxxx) — that one gets a confirmation popup.
$myPayments = payments_for_customer($user['email'] ?? '');
$paidId     = trim((string) ($_GET['paid'] ?? ''));
$paidOrder  = null;
if ($paidId !== '') {
  foreach ($myOrders as $o) { if ((string) $o['id'] === $paidId) { $paidOrder = $o; break; } }
}
$paidEco = $paidOrder ? order_economics((int) $paidOrder['total']) : null;

$STEPS = ['Pending', 'Processing', 'In Transit', 'Delivered'];
function step_index(string $status): int {
  $steps = ['Pending' => 0, 'Processing' => 1, 'In Transit' => 2, 'Delivered' => 3];
  return $steps[$status] ?? 0;
}
function status_badge(string $status): string {
  $map = ['Delivered' => 'bg-green-100 text-green-700', 'In Transit' => 'bg-blue-100 text-blue-700', 'Processing' => 'bg-amber-100 text-amber-700', 'Pending' => 'bg-gray-100 text-gray-700', 'Cancelled' => 'bg-red-100 text-red-700'];
  return '<span class="text-xs font-medium px-2.5 py-1 rounded-full ' . ($map[$status] ?? 'bg-gray-100 text-gray-700') . '">' . e($status) . '</span>';
}
function return_status_badge(string $status): string {
  $map = ['Approved' => 'bg-green-100 text-green-700', 'Refunded' => 'bg-emerald-100 text-emerald-700', 'Rejected' => 'bg-red-100 text-red-700', 'Requested' => 'bg-amber-100 text-amber-700'];
  return '<span class="text-xs font-medium px-2.5 py-1 rounded-full ' . ($map[$status] ?? 'bg-gray-100 text-gray-700') . '">' . e($status) . '</span>';
}
function pay_status_badge(string $status): string {
  // Cash on Delivery is "Pending" until collected; prepaid is "Paid".
  $label = $status === 'Paid' ? 'Paid' : 'Pay on delivery';
  $cls   = $status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700';
  return '<span class="text-xs font-medium px-2.5 py-1 rounded-full ' . $cls . '">' . e($label) . '</span>';
}

$brand = brand_name();
$page_title = 'My Orders · ' . $brand;
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gray-50">
  <header class="bg-white border-b sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-3 md:py-4 flex items-center justify-between gap-2">
      <a href="index.php" class="flex items-center gap-2 min-w-0 shrink">
        <i data-lucide="shopping-cart" class="w-7 h-7 md:w-8 md:h-8 text-green-600 shrink-0"></i>
        <h1 class="text-lg md:text-2xl font-bold text-green-600 truncate"><?= e($brand) ?></h1>
      </a>
      <div class="flex items-center gap-0.5 sm:gap-2 shrink-0 text-sm">
        <span class="text-sm text-gray-600 hidden lg:inline mr-1">Hi, <?= e($user['name']) ?></span>
        <a href="index.php" class="px-2.5 md:px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">Shop</a>
        <a href="profile.php" class="hidden sm:inline-flex px-2.5 md:px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">Profile</a>
        <a href="cart.php" class="px-2.5 md:px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">Cart</a>
        <a href="logout.php" class="px-2.5 md:px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">Logout</a>
      </div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-8 flex-wrap gap-3">
      <h2 class="text-3xl font-bold">My Orders</h2>
      <a href="index.php#products" class="text-sm border rounded-lg px-4 py-2 hover:bg-gray-50">← Continue shopping</a>
    </div>

    <?php if ($myNotifs): ?>
      <div class="bg-white rounded-xl border mb-8 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50">
          <h3 class="font-semibold flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4 text-green-600"></i>Notifications <?php if ($unreadNotifs): ?><span class="text-xs font-medium text-white bg-red-500 rounded-full px-2 py-0.5"><?= $unreadNotifs ?> new</span><?php endif; ?></h3>
          <?php if ($unreadNotifs): ?>
            <form method="post" action="actions.php">
              <input type="hidden" name="do" value="notifications_read" />
              <input type="hidden" name="redirect" value="orders.php" />
              <button class="text-xs font-medium text-green-700 hover:underline">Mark all as read</button>
            </form>
          <?php endif; ?>
        </div>
        <ul class="divide-y max-h-72 overflow-auto">
          <?php foreach ($myNotifs as $n): $u = empty($n['is_read']); ?>
            <li class="px-5 py-3 <?= $u ? 'bg-green-50/60' : '' ?>">
              <p class="text-sm font-medium flex items-center gap-2"><?php if ($u): ?><span class="w-1.5 h-1.5 rounded-full bg-green-600 shrink-0"></span><?php endif; ?><?= e($n['title']) ?></p>
              <?php if (!empty($n['body'])): ?><p class="text-xs text-gray-500 mt-0.5"><?= e($n['body']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($myPayments): ?>
      <div id="payment-history" class="bg-white rounded-xl border mb-8 overflow-hidden scroll-mt-24">
        <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50">
          <h3 class="font-semibold flex items-center gap-2"><i data-lucide="receipt" class="w-4 h-4 text-green-600"></i>Payment History</h3>
          <span class="text-xs text-gray-500"><?= count($myPayments) ?> transaction<?= count($myPayments) === 1 ? '' : 's' ?></span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 border-b"><th class="py-2.5 px-5">Date</th><th class="py-2.5 pr-4">Order</th><th class="py-2.5 pr-4">Store</th><th class="py-2.5 pr-4">Method</th><th class="py-2.5 pr-4">Amount</th><th class="py-2.5 pr-5">Status</th></tr></thead>
            <tbody>
              <?php foreach ($myPayments as $p): ?>
                <tr class="border-b last:border-0">
                  <td class="py-3 px-5 whitespace-nowrap text-gray-500"><?= e(substr((string) ($p['created_at'] ?? ''), 0, 16)) ?></td>
                  <td class="py-3 pr-4 font-medium"><?= e($p['orderId']) ?></td>
                  <td class="py-3 pr-4"><?= e($p['vendor'] ?: 'Local store') ?></td>
                  <td class="py-3 pr-4"><span class="text-xs border rounded-full px-2 py-1"><?= e($p['method']) ?></span></td>
                  <td class="py-3 pr-4 font-semibold">₹<?= e($p['amount']) ?></td>
                  <td class="py-3 pr-5"><?= pay_status_badge($p['status'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$myOrders): ?>
      <div class="bg-white rounded-xl border p-12 text-center">
        <i data-lucide="package" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
        <p class="text-gray-600 mb-6">You haven't placed any orders yet.</p>
        <a href="index.php#products" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg">Start shopping</a>
      </div>
    <?php else: ?>
      <div class="space-y-5">
        <?php foreach ($myOrders as $o): $active = step_index($o['status']); ?>
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-gray-50 flex-wrap gap-2">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-bold"><?= e($o['id']) ?></span>
                  <?= status_badge($o['status']) ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">Placed on <?= e($o['date']) ?> · <?= e($o['vendor'] ?: 'Local store') ?></p>
              </div>
              <div class="text-right">
                <p class="text-2xl font-bold text-green-600">₹<?= e(order_grand_total($o)) ?></p>
                <p class="text-xs text-gray-500"><?= e($o['items']) ?> item<?= (int)$o['items'] === 1 ? '' : 's' ?> · <?= e($o['paymentMethod']) ?><?php if ((int) ($o['pickupFee'] ?? 0) > 0): ?> · incl. ₹<?= (int) $o['pickupFee'] ?> handling<?php endif; ?></p>
                <?php if ((int) ($o['discount'] ?? 0) > 0): ?><p class="text-xs font-medium text-green-600 mt-0.5">You saved ₹<?= (int) $o['discount'] ?><?= !empty($o['offerCode']) ? ' · ' . e($o['offerCode']) : '' ?></p><?php endif; ?>
                <?php if (!empty($o['deliveryOption'])): ?><p class="text-xs text-gray-500 mt-0.5 inline-flex items-center gap-1"><i data-lucide="<?= $o['deliveryOption'] === 'Home Delivery' ? 'truck' : ($o['deliveryOption'] === 'Centralized Pickup' ? 'building-2' : 'shopping-bag') ?>" class="w-3.5 h-3.5"></i><?= e($o['deliveryOption']) ?><?= !empty($o['deliveryPartner']) ? ' · ' . e($o['deliveryPartner']) : '' ?></p><?php endif; ?>
              </div>
            </div>

            <div class="px-6 py-6">
              <?php if ($o['status'] === 'Cancelled'): ?>
                <div class="flex items-center gap-2 text-red-600 text-sm font-medium"><i data-lucide="x-circle" class="w-5 h-5"></i>This order was cancelled.</div>
              <?php elseif (($o['deliveryOption'] ?? '') === 'Centralized Pickup'): ?>
                <?php
                  // Pickup-specific progress: placed → shops preparing → ready at centre → collected.
                  $pp        = pickup_progress($o['id']);
                  $allReady  = $pp['total'] > 0 && $pp['ready'] >= $pp['total'];
                  $collected = $o['status'] === 'Delivered';
                  $pickActive = $collected ? 3 : ($allReady ? 2 : ($pp['ready'] > 0 ? 1 : 0));
                  $pickSteps  = ['Order placed', 'Shops preparing', 'Ready at centre', 'Collected'];
                ?>
                <div class="flex items-center">
                  <?php foreach ($pickSteps as $i => $step): $done = $i <= $pickActive; ?>
                    <div class="flex flex-col items-center flex-shrink-0" style="min-width:76px">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $done ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($done): ?><i data-lucide="check" class="w-4 h-4"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
                      </div>
                      <span class="text-[11px] mt-1 text-center <?= $done ? 'text-green-700 font-medium' : 'text-gray-400' ?>"><?= e($step) ?><?php if ($i === 1 && $pp['total'] > 0 && !$allReady): ?><br><span class="text-[10px] text-gray-400"><?= $pp['ready'] ?>/<?= $pp['total'] ?> ready</span><?php endif; ?></span>
                    </div>
                    <?php if ($i < count($pickSteps) - 1): ?>
                      <div class="flex-1 h-1 rounded <?= $i < $pickActive ? 'bg-green-600' : 'bg-gray-200' ?>"></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <!-- Status tracker -->
                <div class="flex items-center">
                  <?php foreach ($STEPS as $i => $step): $done = $i <= $active; ?>
                    <div class="flex flex-col items-center flex-shrink-0" style="min-width:70px">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $done ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($done): ?><i data-lucide="check" class="w-4 h-4"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
                      </div>
                      <span class="text-[11px] mt-1 <?= $done ? 'text-green-700 font-medium' : 'text-gray-400' ?>"><?= e($step) ?></span>
                    </div>
                    <?php if ($i < count($STEPS) - 1): ?>
                      <div class="flex-1 h-1 rounded <?= $i < $active ? 'bg-green-600' : 'bg-gray-200' ?>"></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php
                // Live delivery map: only for Home Delivery orders that are on their
                // way (not cancelled). Picks up the delivery partner's sub-status too.
                $isHomeDelivery = ($o['deliveryOption'] ?? '') === 'Home Delivery';
                if ($isHomeDelivery && $o['status'] !== 'Cancelled'):
                  $dlv       = delivery_for_order($o['id']);
                  $subStatus = $dlv['status'] ?? '';
                  $trackAddr = order_delivery_address($o);
                  $mapId     = 'map-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $o['id']);
                  $isLive    = $o['status'] === 'In Transit' || $subStatus === 'Picked Up';
                  $isDone    = $o['status'] === 'Delivered';
                  $trackPayload[] = [
                    'mapId'     => $mapId,
                    'orderId'   => (string) $o['id'],
                    'status'    => $o['status'],
                    'subStatus' => $subStatus,
                    'address'   => $trackAddr,
                    'store'     => $o['vendor'] ?: 'Local store',
                    'rider'     => $o['deliveryPartner'] ?: 'Delivery partner',
                  ];
              ?>
                <div class="mt-6 border-t pt-4">
                  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                      <i data-lucide="navigation" class="w-4 h-4 text-green-600"></i>Live delivery tracking
                    </p>
                    <?php if ($isDone): ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 rounded-full px-2.5 py-1"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i>Delivered</span>
                    <?php elseif ($isLive): ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-full px-2.5 py-1"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span></span>Live · on the way</span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-full px-2.5 py-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i>Preparing your order</span>
                    <?php endif; ?>
                  </div>
                  <div id="<?= e($mapId) ?>" class="tracking-map w-full h-64 rounded-lg overflow-hidden border bg-gray-100 relative z-0">
                    <div class="absolute inset-0 flex items-center justify-center text-sm text-gray-400 gap-2"><i data-lucide="loader" class="w-4 h-4 animate-spin"></i>Loading map…</div>
                  </div>
                  <div class="flex items-center justify-between mt-2 flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    <div class="flex items-center gap-4">
                      <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span><?= e($o['vendor'] ?: 'Local store') ?></span>
                      <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-600"></span><?= e($o['deliveryPartner'] ?: 'Delivery partner') ?></span>
                      <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-green-600"></span>You</span>
                    </div>
                    <span class="tracking-eta font-medium text-gray-600" data-eta-for="<?= e($mapId) ?>"></span>
                  </div>
                </div>
              <?php endif; ?>

              <?php
                // Centralized Pickup: where to collect + per-shop readiness + collect button.
                if (($o['deliveryOption'] ?? '') === 'Centralized Pickup' && $o['status'] !== 'Cancelled'):
                  $pp        = pickup_progress($o['id']);
                  $allReady  = $pp['total'] > 0 && $pp['ready'] >= $pp['total'];
                  $collected = $o['status'] === 'Delivered';
                  $centerLoc = $o['pickupLocation'] ?: pickup_center_label(pickup_center());
              ?>
                <div class="mt-6 border-t pt-4">
                  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-2"><i data-lucide="building-2" class="w-4 h-4 text-green-600"></i>Centralized pickup</p>
                    <?php if ($collected): ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 rounded-full px-2.5 py-1"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i>Collected</span>
                    <?php elseif ($allReady): ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 rounded-full px-2.5 py-1"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-600"></span></span>Ready to collect</span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-full px-2.5 py-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i><?= $pp['ready'] ?> of <?= $pp['total'] ?: 1 ?> shops ready</span>
                    <?php endif; ?>
                  </div>
                  <div class="rounded-lg border bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-700 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-green-600"></i>Collect your complete order from</p>
                    <p class="text-sm text-gray-700 mt-1"><?= e($centerLoc) ?></p>
                    <?php if ($pp['total'] > 0): ?>
                      <div class="mt-3 space-y-1.5">
                        <?php foreach ($pp['shops'] as $sh): $r = in_array($sh, $pp['readyShops'], true); ?>
                          <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 inline-flex items-center gap-1.5"><i data-lucide="store" class="w-3.5 h-3.5"></i><?= e($sh) ?></span>
                            <?php if ($r): ?><span class="text-green-700 font-medium inline-flex items-center gap-1"><i data-lucide="check" class="w-3.5 h-3.5"></i>Ready at centre</span><?php else: ?><span class="text-amber-600">Preparing…</span><?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($allReady && !$collected): ?>
                      <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3">
                        <p class="text-sm text-green-800 font-medium flex items-center gap-1.5"><i data-lucide="party-popper" class="w-4 h-4"></i>Your complete order is ready!</p>
                        <p class="text-xs text-green-700 mt-0.5"><?= order_is_prepaid($o) ? 'Just show your order number at the centre.' : 'Pay ₹' . order_grand_total($o) . ' in cash at the centre when you collect.' ?></p>
                        <form method="post" action="actions.php" class="mt-3" data-confirm="Confirm you have collected your complete order?">
                          <input type="hidden" name="do" value="pickup_collected" />
                          <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
                          <button class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg inline-flex items-center gap-1.5"><i data-lucide="package-check" class="w-4 h-4"></i>I've collected my order</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php $lines = order_items($o['id']); if ($lines): ?>
                <div class="mt-6 border-t pt-4">
                  <p class="text-xs font-medium text-gray-500 mb-2">Items</p>
                  <div class="space-y-1">
                    <?php foreach ($lines as $li): ?>
                      <div class="flex justify-between text-sm">
                        <span class="text-gray-600"><?= e($li['name']) ?> <span class="text-gray-400">× <?= e($li['quantity']) ?><?= $li['unit'] ? ' · ' . e($li['unit']) : '' ?></span></span>
                        <span>₹<?= e($li['price'] * $li['quantity']) ?></span>
                      </div>
                    <?php endforeach; ?>
                    <?php if ((int) ($o['pickupFee'] ?? 0) > 0): ?>
                      <div class="flex justify-between text-sm pt-1 mt-1 border-t"><span class="text-gray-500">Collection handling</span><span>₹<?= (int) $o['pickupFee'] ?></span></div>
                      <div class="flex justify-between text-sm font-semibold"><span>Total</span><span>₹<?= order_grand_total($o) ?></span></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <div class="flex items-center justify-between mt-6 flex-wrap gap-3">
                <?php $addr = order_delivery_address($o); ?>
                <div class="flex items-start gap-2 text-sm text-gray-600">
                  <?php if ($addr !== '' && ($o['deliveryOption'] ?? '') !== 'Centralized Pickup'): ?><i data-lucide="map-pin" class="w-4 h-4 mt-0.5 flex-shrink-0"></i><span><?= ($o['deliveryOption'] ?? '') === 'Home Delivery' ? 'Delivering to' : 'Delivery address' ?>: <?= e($addr) ?></span><?php endif; ?>
                </div>
                <?php if ($o['status'] === 'Pending'): ?>
                  <form method="post" action="actions.php" data-confirm="Cancel order <?= e($o['id']) ?>?">
                    <input type="hidden" name="do" value="order_cancel" />
                    <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
                    <button class="text-sm border rounded-lg px-4 py-1.5 text-red-600 border-red-200 hover:bg-red-50">Cancel order</button>
                  </form>
                <?php endif; ?>
              </div>

              <?php if ($o['status'] === 'Delivered'): $returns = return_requests_for_order($o['id']); ?>
                <div class="mt-6 border-t pt-4">
                  <div class="flex items-center gap-2 mb-3">
                    <i data-lucide="undo-2" class="w-4 h-4 text-gray-500"></i>
                    <p class="text-sm font-semibold text-gray-700">Returns</p>
                  </div>
                  <?php if ($returns): ?>
                    <div class="space-y-2 mb-4">
                      <?php foreach ($returns as $r): ?>
                        <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg px-3 py-2">
                          <span class="text-gray-700"><?= e($r['item']) ?> <span class="text-gray-400">× <?= e($r['quantity']) ?></span> — <span class="text-gray-500"><?= e($r['reason']) ?></span></span>
                          <?= return_status_badge($r['status']) ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <details class="group">
                    <summary class="cursor-pointer text-sm text-green-600 font-medium select-none">Request a return</summary>
                    <form method="post" action="actions.php" class="mt-3 space-y-3 bg-gray-50 rounded-lg p-4">
                      <input type="hidden" name="do" value="return_request" />
                      <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
                      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                          <label class="block text-xs font-medium text-gray-500 mb-1">Item to return</label>
                          <?php if ($lines): ?>
                            <select name="item" required class="w-full border rounded-lg px-3 py-2 text-sm">
                              <?php foreach ($lines as $li): ?><option value="<?= e($li['name']) ?>"><?= e($li['name']) ?></option><?php endforeach; ?>
                            </select>
                          <?php else: ?>
                            <input type="text" name="item" required value="Whole order" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Which item?" />
                          <?php endif; ?>
                        </div>
                        <div>
                          <label class="block text-xs font-medium text-gray-500 mb-1">Quantity</label>
                          <input type="number" name="quantity" min="1" value="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        </div>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Reason</label>
                        <textarea name="reason" required rows="2" placeholder="Tell us what went wrong (damaged, wrong item, expired, ...)" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                      </div>
                      <button class="text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg px-4 py-2">Submit return request</button>
                    </form>
                  </details>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php if ($paidOrder): $paidStatus = order_payment_status($paidOrder); ?>
  <!-- Payment confirmation popup (shown once, right after checkout) -->
  <div id="pay-receipt" class="fixed inset-0 z-[95] flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="bg-green-600 text-white px-6 py-6 text-center">
        <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h3 class="text-xl font-bold"><?= $paidStatus === 'Paid' ? 'Payment successful' : 'Order confirmed' ?></h3>
        <p class="text-green-100 text-sm mt-1">Order <?= e($paidOrder['id']) ?> · <?= e($paidOrder['date']) ?></p>
      </div>
      <div class="p-6 space-y-4">
        <div class="text-center">
          <p class="text-sm text-gray-500">Amount <?= $paidStatus === 'Paid' ? 'paid' : 'payable' ?></p>
          <p class="text-4xl font-bold text-gray-900">₹<?= e(order_grand_total($paidOrder)) ?></p>
          <?php if ((int) ($paidOrder['pickupFee'] ?? 0) > 0): ?><p class="text-xs text-gray-500 mt-0.5">Goods ₹<?= e($paidOrder['total']) ?> + ₹<?= (int) $paidOrder['pickupFee'] ?> collection handling</p><?php endif; ?>
          <div class="mt-2 flex items-center justify-center gap-2 text-sm text-gray-600">
            <span class="inline-flex items-center gap-1"><i data-lucide="store" class="w-4 h-4"></i><?= e($paidOrder['vendor'] ?: 'Local store') ?></span>
            <span>·</span>
            <span><?= e($paidOrder['paymentMethod']) ?></span>
            <?= pay_status_badge($paidStatus) ?>
          </div>
        </div>

        <!-- Who receives what: the vendor and the platform/admin. -->
        <div class="rounded-xl border bg-gray-50 divide-y">
          <div class="flex items-center justify-between px-4 py-2.5 text-sm">
            <span class="inline-flex items-center gap-2 text-gray-600"><span class="inline-block w-2.5 h-2.5 rounded-full bg-green-600"></span>Vendor receives</span>
            <span class="font-semibold">₹<?= e($paidEco['vendorNet']) ?></span>
          </div>
          <div class="flex items-center justify-between px-4 py-2.5 text-sm">
            <span class="inline-flex items-center gap-2 text-gray-600"><span class="inline-block w-2.5 h-2.5 rounded-full bg-purple-600"></span>Platform &amp; admin</span>
            <span class="font-semibold">₹<?= e($paidEco['commission']) ?></span>
          </div>
          <?php if ((int) ($paidOrder['pickupFee'] ?? 0) > 0): ?>
          <div class="flex items-center justify-between px-4 py-2.5 text-sm">
            <span class="inline-flex items-center gap-2 text-gray-600"><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span>Collection handling</span>
            <span class="font-semibold">₹<?= (int) $paidOrder['pickupFee'] ?></span>
          </div>
          <?php endif; ?>
        </div>

        <p class="text-xs text-gray-500 flex items-start gap-1.5">
          <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
          The store and our team have been notified. This payment is saved to your Payment History.
        </p>

        <div class="flex gap-3 pt-1">
          <a href="#payment-history" onclick="document.getElementById('pay-receipt').remove()" class="flex-1 text-center border rounded-lg px-4 py-2.5 text-sm hover:bg-gray-50">View history</a>
          <button type="button" onclick="document.getElementById('pay-receipt').remove()" class="flex-1 bg-green-600 hover:bg-green-700 text-white rounded-lg px-4 py-2.5 text-sm font-medium">Done</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var o = document.getElementById('pay-receipt');
      if (!o) return;
      o.addEventListener('click', function (e) { if (e.target === o) o.remove(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.getElementById('pay-receipt')) o.remove(); });
      if (window.lucide) lucide.createIcons();
    })();
  </script>
<?php endif; ?>

<?php if (!empty($trackPayload)): ?>
  <!-- Leaflet (free OpenStreetMap maps — no API key needed) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script id="track-data" type="application/json"><?= json_encode($trackPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script>
  (function () {
    var DATA;
    try { DATA = JSON.parse(document.getElementById('track-data').textContent); } catch (e) { return; }
    if (!window.L || !DATA || !DATA.length) return;

    // Home region: Hassan, Karnataka (PIN 573201). Used as the map centre when an
    // address can't be geocoded, and to bias address lookups to this area.
    var FALLBACK = [13.0072, 76.0962];
    var animants = []; // active rider animations, advanced by one shared loop

    // Deterministic 0..1 pseudo-random from a string, so each order's synthetic
    // store position (and fallback point) stays stable across refreshes.
    function hash01(str, salt) {
      var s = (salt || '') + str, h = 2166136261;
      for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = (h * 16777619) >>> 0; }
      return (h % 100000) / 100000;
    }

    // Move a lat/lng by `meters` along a compass bearing (degrees).
    function offset(pt, meters, bearingDeg) {
      var R = 6378137, br = bearingDeg * Math.PI / 180;
      var dLat = (meters * Math.cos(br)) / R;
      var dLng = (meters * Math.sin(br)) / (R * Math.cos(pt[0] * Math.PI / 180));
      return [pt[0] + dLat * 180 / Math.PI, pt[1] + dLng * 180 / Math.PI];
    }

    function metersBetween(a, b) {
      var R = 6371000, toR = Math.PI / 180;
      var dLat = (b[0] - a[0]) * toR, dLng = (b[1] - a[1]) * toR;
      var la1 = a[0] * toR, la2 = b[0] * toR;
      var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.sin(dLng / 2) * Math.sin(dLng / 2) * Math.cos(la1) * Math.cos(la2);
      return 2 * R * Math.asin(Math.min(1, Math.sqrt(x)));
    }

    // A gently curved path (quadratic bezier) store -> you, so the route doesn't
    // look like a robotic straight line.
    function bezier(a, c, b, t) {
      var u = 1 - t;
      return [
        u * u * a[0] + 2 * u * t * c[0] + t * t * b[0],
        u * u * a[1] + 2 * u * t * c[1] + t * t * b[1]
      ];
    }
    function controlPoint(a, b, order) {
      var mid = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
      var bend = (hash01(order, 'bend') - 0.5) * 0.4; // sign + size of the curve
      // perpendicular to the a->b direction
      return [mid[0] + (b[1] - a[1]) * bend, mid[1] - (b[0] - a[0]) * bend];
    }

    function routePoints(a, c, b) {
      var pts = [];
      for (var i = 0; i <= 48; i++) pts.push(bezier(a, c, b, i / 48));
      return pts;
    }

    // How far along the route the rider should be (0 = store, 1 = your door).
    function targetFor(status, sub) {
      if (status === 'Delivered') return 1;
      if (status === 'In Transit' || sub === 'Picked Up') return 0.9;
      if (status === 'Processing' || sub === 'Assigned') return 0.12;
      return 0.04; // Pending
    }

    function emojiIcon(emoji, ring) {
      return L.divIcon({
        className: '',
        html: '<div style="font-size:22px;line-height:28px;width:28px;height:28px;text-align:center;' +
              'filter:drop-shadow(0 1px 2px rgba(0,0,0,.35));' + (ring ? '' : '') + '">' + emoji + '</div>',
        iconSize: [28, 28], iconAnchor: [14, 14]
      });
    }

    function geocode(address) {
      if (!address) return Promise.resolve(null);
      // Anchor vague addresses to Hassan so "MG Road" resolves here, not in Bangalore.
      var q = /hassan/i.test(address) ? address : address + ', Hassan, Karnataka';
      // Cache key is bumped to lkgeo2 so stale Bengaluru lookups are re-resolved.
      var key = 'lkgeo2:' + q;
      try { var cached = localStorage.getItem(key); if (cached) return Promise.resolve(JSON.parse(cached)); } catch (e) {}
      // viewbox biases results to the Hassan district (left,top,right,bottom lon/lat).
      var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=in'
              + '&viewbox=75.80,13.30,76.40,12.75&q=' + encodeURIComponent(q);
      return fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (d && d[0]) {
            var pt = [parseFloat(d[0].lat), parseFloat(d[0].lon)];
            try { localStorage.setItem(key, JSON.stringify(pt)); } catch (e) {}
            return pt;
          }
          return null;
        })
        .catch(function () { return null; });
    }

    function etaText(entry, frac, distM) {
      if (entry.status === 'Delivered') return 'Delivered 🎉';
      if (entry.status === 'In Transit' || entry.subStatus === 'Picked Up') {
        var remaining = (1 - frac) * distM;          // metres left
        var mins = Math.max(1, Math.round(remaining / 250)); // ~15 km/h
        return 'Arriving in about ' + mins + ' min';
      }
      if (entry.status === 'Processing' || entry.subStatus === 'Assigned') return 'Store is preparing your order';
      return 'Waiting for the store to confirm';
    }

    function buildMap(entry, you) {
      var el = document.getElementById(entry.mapId);
      if (!el) return;
      el.innerHTML = ''; // clear the "Loading…" placeholder

      var store = offset(you, 1600 + Math.round(hash01(entry.orderId, 'd') * 1400),
                              Math.round(hash01(entry.orderId, 'a') * 360));
      var ctrl  = controlPoint(store, you, entry.orderId);
      var route = routePoints(store, ctrl, you);
      var distM = metersBetween(store, you);

      var map = L.map(entry.mapId, { scrollWheelZoom: false, zoomControl: true, attributionControl: true });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      map.fitBounds(L.latLngBounds(route).pad(0.25));

      L.polyline(route, { color: '#16a34a', weight: 4, opacity: 0.6, dashArray: '2 8', lineCap: 'round' }).addTo(map);
      L.marker(store, { icon: emojiIcon('🏪') }).addTo(map).bindPopup('Store · ' + entry.store);
      L.marker(you,   { icon: emojiIcon('📍') }).addTo(map).bindPopup('Delivering to you');

      var rider = L.marker(bezier(store, ctrl, you, 0), { icon: emojiIcon('🛵'), zIndexOffset: 1000 })
                   .addTo(map).bindPopup('Rider · ' + entry.rider);

      var etaEl = document.querySelector('.tracking-eta[data-eta-for="' + entry.mapId + '"]');
      var target = targetFor(entry.status, entry.subStatus);
      var moving = entry.status !== 'Delivered' && target >= 0.9;

      animants.push({
        entry: entry, store: store, ctrl: ctrl, you: you, dist: distM,
        rider: rider, etaEl: etaEl, cur: Math.min(target, 0.12),
        target: target, moving: moving
      });
    }

    // One animation loop drives every rider so motion stays smooth and cheap.
    function tick() {
      var now = Date.now();
      for (var i = 0; i < animants.length; i++) {
        var a = animants[i];
        var tgt = a.target;
        // While "on the way", hover near the destination so it feels live without
        // ever arriving before the order is actually marked Delivered.
        if (a.moving) tgt = 0.86 + 0.05 * Math.sin(now / 1600);
        a.cur += (tgt - a.cur) * 0.04; // ease toward the target
        var p = bezier(a.store, a.ctrl, a.you, a.cur);
        a.rider.setLatLng(p);
        if (a.etaEl) a.etaEl.textContent = etaText(a.entry, a.cur, a.dist);
      }
      requestAnimationFrame(tick);
    }

    // Build each map in sequence so we stay within Nominatim's ~1 req/sec limit.
    var idx = 0;
    function next() {
      if (idx >= DATA.length) { requestAnimationFrame(tick); return; }
      var entry = DATA[idx++];
      geocode(entry.address).then(function (pt) {
        var you = pt || offset(FALLBACK, 500 + hash01(entry.orderId, 'f') * 2500, hash01(entry.orderId, 'g') * 360);
        try { buildMap(entry, you); } catch (e) {}
        if (pt) { setTimeout(next, 1100); } else { next(); } // throttle only real lookups
      });
    }
    next();
  })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/foot.php'; ?>
