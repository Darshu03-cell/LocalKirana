<?php
require_once __DIR__ . '/includes/init.php';

$user = require_role('customer');
$myOrders = customer_orders($user['email'] ?? '');
$myNotifs = notifications_for('customer', $user['email'] ?? '', 15);
$unreadNotifs = notifications_unread_count('customer', $user['email'] ?? '');

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
                <p class="text-2xl font-bold text-green-600">₹<?= e($o['total']) ?></p>
                <p class="text-xs text-gray-500"><?= e($o['items']) ?> item<?= (int)$o['items'] === 1 ? '' : 's' ?> · <?= e($o['paymentMethod']) ?></p>
                <?php if ((int) ($o['discount'] ?? 0) > 0): ?><p class="text-xs font-medium text-green-600 mt-0.5">You saved ₹<?= (int) $o['discount'] ?><?= !empty($o['offerCode']) ? ' · ' . e($o['offerCode']) : '' ?></p><?php endif; ?>
                <?php if (!empty($o['deliveryOption'])): ?><p class="text-xs text-gray-500 mt-0.5 inline-flex items-center gap-1"><i data-lucide="<?= $o['deliveryOption'] === 'Home Delivery' ? 'truck' : 'shopping-bag' ?>" class="w-3.5 h-3.5"></i><?= e($o['deliveryOption']) ?><?= !empty($o['deliveryPartner']) ? ' · ' . e($o['deliveryPartner']) : '' ?></p><?php endif; ?>
              </div>
            </div>

            <div class="px-6 py-6">
              <?php if ($o['status'] === 'Cancelled'): ?>
                <div class="flex items-center gap-2 text-red-600 text-sm font-medium"><i data-lucide="x-circle" class="w-5 h-5"></i>This order was cancelled.</div>
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
                  </div>
                </div>
              <?php endif; ?>

              <div class="flex items-center justify-between mt-6 flex-wrap gap-3">
                <?php $addr = order_delivery_address($o); ?>
                <div class="flex items-start gap-2 text-sm text-gray-600">
                  <?php if ($addr !== ''): ?><i data-lucide="map-pin" class="w-4 h-4 mt-0.5 flex-shrink-0"></i><span>Delivering to: <?= e($addr) ?></span><?php endif; ?>
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
<?php require __DIR__ . '/partials/foot.php'; ?>
