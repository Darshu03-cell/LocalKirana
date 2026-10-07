<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dashboard.php';

$user = require_role('superadmin');
$tab  = $_GET['tab'] ?? 'dashboard';

$allOrders = orders();
$offerList = offers();

// ---- filters (orders tab) ----
$statusFilter = $_GET['status'] ?? 'All';
$payFilter    = $_GET['pay'] ?? 'All';
$shownOrders = array_values(array_filter($allOrders, function ($o) use ($statusFilter, $payFilter) {
  if ($statusFilter !== 'All' && $o['status'] !== $statusFilter) return false;
  $cod = ($o['paymentMethod'] ?? '') === 'Cash on Delivery';
  $ps  = order_payment_status($o);
  return match ($payFilter) {
    'COD'     => $cod,
    'Prepaid' => !$cod,
    'Paid'    => $ps === 'Paid',
    'Unpaid'  => $ps === 'Unpaid',
    default   => true,
  };
}));
$qs = 'status=' . urlencode($statusFilter) . '&pay=' . urlencode($payFilter);

// ---- aggregates ----
$counts = ['Pending' => 0, 'Processing' => 0, 'In Transit' => 0, 'Delivered' => 0, 'Cancelled' => 0];
$revenue = 0; $collected = 0; $pendingPay = 0; $codCount = 0; $prepaidCount = 0;
foreach ($allOrders as $o) {
  $counts[$o['status']] = ($counts[$o['status']] ?? 0) + 1;
  if ($o['status'] === 'Cancelled') continue;
  $revenue += (int) $o['total'];
  if (order_payment_status($o) === 'Paid') $collected += (int) $o['total']; else $pendingPay += (int) $o['total'];
  if (($o['paymentMethod'] ?? '') === 'Cash on Delivery') $codCount++; else $prepaidCount++;
}
$unpaidCount = count(array_filter($allOrders, fn($o) => $o['status'] !== 'Cancelled' && order_payment_status($o) === 'Unpaid'));

$customers = array_values(array_filter(accounts_all(), fn($a) => ($a['role'] ?? '') === 'customer'));
$orderCountByEmail = [];
foreach ($allOrders as $o) { $e = strtolower($o['customerEmail'] ?? ''); if ($e) $orderCountByEmail[$e] = ($orderCountByEmail[$e] ?? 0) + 1; }

$returns        = return_requests_all();
$pendingReturns = return_requests_pending_count();

function so_status_badge(string $s): string {
  $map = ['Delivered' => 'bg-green-100 text-green-700', 'In Transit' => 'bg-blue-100 text-blue-700', 'Processing' => 'bg-amber-100 text-amber-700', 'Pending' => 'bg-gray-100 text-gray-700', 'Cancelled' => 'bg-red-100 text-red-700'];
  return '<span class="text-xs font-medium px-2 py-1 rounded-full ' . ($map[$s] ?? 'bg-gray-100 text-gray-700') . '">' . e($s) . '</span>';
}
function so_pay_badge(string $s): string {
  $cls = $s === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700';
  return '<span class="text-xs font-medium px-2 py-1 rounded-full ' . $cls . '">' . e($s) . '</span>';
}
function so_return_badge(string $s): string {
  $map = ['Approved' => 'bg-green-100 text-green-700', 'Refunded' => 'bg-emerald-100 text-emerald-700', 'Rejected' => 'bg-red-100 text-red-700', 'Requested' => 'bg-amber-100 text-amber-700'];
  return '<span class="text-xs font-medium px-2 py-1 rounded-full ' . ($map[$s] ?? 'bg-gray-100 text-gray-700') . '">' . e($s) . '</span>';
}
/** Safe DOM id for an order's hidden detail template (strips #, etc.). */
function so_order_slug(string $id): string { return preg_replace('/[^A-Za-z0-9]/', '', $id); }

/** Full, self-contained detail card for one order (rendered into a modal). */
function so_order_detail_html(array $o, string $qs): string {
  $ps      = order_payment_status($o);
  $addr    = order_delivery_address($o);
  $lines   = order_items($o['id']);
  $acct    = !empty($o['customerEmail']) ? account_find($o['customerEmail']) : null;
  $phone   = $acct['phone'] ?? ($o['phone'] ?? '');
  $returns = return_requests_for_order($o['id']);
  $redir   = 'superadmin.php?tab=orders&' . $qs;
  ob_start(); ?>
  <div class="space-y-5 text-sm">
    <div class="flex items-center justify-between">
      <div>
        <p class="font-bold text-lg"><?= e($o['id']) ?></p>
        <p class="text-xs text-gray-400">Placed on <?= e($o['date']) ?> · <?= e($o['vendor'] ?: 'Local store') ?></p>
      </div>
      <?= so_status_badge($o['status']) ?>
    </div>

    <div class="bg-gray-50 rounded-lg p-4 space-y-1">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Customer</p>
      <p class="font-medium"><?= e($o['customerName']) ?></p>
      <?php if (!empty($o['customerEmail'])): ?><p class="text-gray-600 flex items-center gap-1.5"><i data-lucide="mail" class="w-3.5 h-3.5"></i><?= e($o['customerEmail']) ?></p><?php endif; ?>
      <?php if ($phone !== ''): ?><p class="text-gray-600 flex items-center gap-1.5"><i data-lucide="phone" class="w-3.5 h-3.5"></i><?= e($phone) ?></p><?php endif; ?>
      <p class="text-gray-600 flex items-start gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i><?= $addr !== '' ? e($addr) : '<span class="text-amber-600">No address on file</span>' ?></p>
    </div>

    <div>
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Items (<?= (int) $o['items'] ?>)</p>
      <div class="border rounded-lg divide-y">
        <?php if ($lines): foreach ($lines as $li): ?>
          <div class="flex justify-between px-3 py-2">
            <span class="text-gray-700"><?= e($li['name']) ?> <span class="text-gray-400">× <?= e($li['quantity']) ?><?= $li['unit'] ? ' · ' . e($li['unit']) : '' ?></span></span>
            <span class="font-medium">₹<?= e($li['price'] * $li['quantity']) ?></span>
          </div>
        <?php endforeach; else: ?>
          <div class="px-3 py-2 text-gray-400"><?= (int) $o['items'] ?> item(s) — not itemised</div>
        <?php endif; ?>
        <?php if ((int) ($o['discount'] ?? 0) > 0): ?>
          <div class="flex justify-between px-3 py-2 text-gray-500"><span>Subtotal</span><span>₹<?= (int) $o['total'] + (int) $o['discount'] ?></span></div>
          <div class="flex justify-between px-3 py-2 text-green-600"><span>Offer<?= !empty($o['offerCode']) ? ' (' . e($o['offerCode']) . ')' : '' ?></span><span>− ₹<?= (int) $o['discount'] ?></span></div>
        <?php endif; ?>
        <div class="flex justify-between px-3 py-2 bg-gray-50 font-bold"><span>Total</span><span>₹<?= e($o['total']) ?></span></div>
      </div>
      <?php if (!empty($o['deliveryOption'])): ?>
        <p class="text-xs text-gray-600 mt-2 flex items-center gap-1.5"><i data-lucide="truck" class="w-3.5 h-3.5"></i><?= e($o['deliveryOption']) ?><?= !empty($o['deliveryPartner']) ? ' · Partner: ' . e($o['deliveryPartner']) : '' ?></p>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="border rounded-lg p-3">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Payment</p>
        <p class="mb-2 flex items-center gap-1.5"><span class="text-xs border rounded-full px-2 py-0.5"><?= e($o['paymentMethod'] ?: 'Online') ?></span><?= so_pay_badge($ps) ?></p>
        <form method="post" action="actions.php">
          <input type="hidden" name="do" value="payment_status" />
          <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
          <input type="hidden" name="status" value="<?= $ps === 'Paid' ? 'Unpaid' : 'Paid' ?>" />
          <input type="hidden" name="redirect" value="<?= e($redir) ?>" />
          <button class="text-xs border rounded-lg px-2 py-1 <?= $ps === 'Paid' ? 'text-amber-600 border-amber-200 hover:bg-amber-50' : 'text-green-600 border-green-200 hover:bg-green-50' ?>"><?= $ps === 'Paid' ? 'Mark Unpaid' : 'Mark Paid' ?></button>
        </form>
      </div>
      <div class="border rounded-lg p-3">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Order status</p>
        <p class="mb-2"><?= so_status_badge($o['status']) ?></p>
        <?php if ($o['status'] !== 'Cancelled'): ?>
          <form method="post" action="actions.php" class="flex items-center gap-1">
            <input type="hidden" name="do" value="order_status" />
            <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
            <input type="hidden" name="redirect" value="<?= e($redir) ?>" />
            <select name="status" class="border rounded-lg px-2 py-1 text-xs"><?php foreach (['Pending', 'Processing', 'In Transit', 'Delivered'] as $s): ?><option <?= $o['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
            <button class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded-lg text-xs">Update</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($returns): ?>
      <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Return requests</p>
        <div class="space-y-2">
          <?php foreach ($returns as $r): ?>
            <div class="border rounded-lg p-3">
              <div class="flex items-center justify-between">
                <span class="font-medium"><?= e($r['item']) ?> <span class="text-gray-400">× <?= e($r['quantity']) ?></span></span>
                <?= so_return_badge($r['status']) ?>
              </div>
              <p class="text-gray-600 mt-1"><?= e($r['reason']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php return ob_get_clean();
}

$nf = dashboard_notif_shell('superadmin', $user['email'] ?? '');

$shell = [
  'panelTitle' => 'Super Admin', 'panelSubtitle' => $user['name'], 'baseUrl' => 'superadmin.php',
  'activeTab' => $tab, 'headerSubtitle' => 'Orders, payments & customers oversight', 'roleLabel' => 'Super Admin',
  'userName' => $user['name'], 'notifList' => $nf['list'], 'notifUnread' => $nf['unread'],
  'nav' => [
    ['id' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Overview'],
    ['id' => 'orders',    'icon' => 'shopping-cart',    'label' => 'All Orders', 'badge' => $counts['Pending'] ? (string) $counts['Pending'] : null],
    ['id' => 'offers',    'icon' => 'badge-percent',     'label' => 'Offers', 'badge' => $offerList ? (string) count($offerList) : null],
    ['id' => 'returns',   'icon' => 'undo-2',           'label' => 'Returns', 'badge' => $pendingReturns ? (string) $pendingReturns : null],
    ['id' => 'customers', 'icon' => 'users',            'label' => 'Customers'],
  ],
];

$page_title = 'Super Admin · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
render_dashboard_start($shell);
?>

<?php if ($tab === 'dashboard'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <?php
      stat_card((string) count($allOrders), 'Total Orders', 'shopping-cart', 'bg-blue-100', 'text-blue-600');
      stat_card('₹' . number_format($revenue), 'Order Value', 'indian-rupee', 'bg-green-100', 'text-green-600');
      stat_card('₹' . number_format($collected), 'Collected', 'badge-check', 'bg-emerald-100', 'text-emerald-600');
      stat_card('₹' . number_format($pendingPay), 'Payment Pending', 'clock', 'bg-amber-100', 'text-amber-600');
      ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Orders by Status</h3>
        <?php $nz = array_filter($counts); if ($nz): ?>
          <?php render_chart('soStatus', 'pie', ['data' => ['labels' => array_keys($nz), 'datasets' => [['data' => array_values($nz), 'backgroundColor' => ['#9ca3af', '#f59e0b', '#3b82f6', '#16a34a', '#ef4444']]]], 'options' => ['maintainAspectRatio' => false]], 260); ?>
        <?php else: empty_state('No orders yet.', 'pie-chart'); endif; ?>
      </div>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Payments</h3>
        <div class="grid grid-cols-2 gap-4">
          <a href="superadmin.php?tab=orders&pay=Paid" class="text-center p-4 bg-green-50 rounded-lg hover:bg-green-100"><p class="text-3xl font-bold text-green-600"><?= count($allOrders) - $unpaidCount - $counts['Cancelled'] ?></p><p class="text-sm text-gray-600 mt-1">Paid</p></a>
          <a href="superadmin.php?tab=orders&pay=Unpaid" class="text-center p-4 bg-amber-50 rounded-lg hover:bg-amber-100"><p class="text-3xl font-bold text-amber-600"><?= $unpaidCount ?></p><p class="text-sm text-gray-600 mt-1">Unpaid (to collect)</p></a>
          <a href="superadmin.php?tab=orders&pay=COD" class="text-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100"><p class="text-3xl font-bold text-gray-700"><?= $codCount ?></p><p class="text-sm text-gray-600 mt-1">Cash on Delivery</p></a>
          <a href="superadmin.php?tab=orders&pay=Prepaid" class="text-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100"><p class="text-3xl font-bold text-blue-600"><?= $prepaidCount ?></p><p class="text-sm text-gray-600 mt-1">Prepaid (online)</p></a>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <?php foreach ($counts as $st => $c): ?>
        <a href="superadmin.php?tab=orders&status=<?= urlencode($st) ?>" class="bg-white rounded-xl border p-4 text-center hover:border-green-300">
          <p class="text-2xl font-bold"><?= $c ?></p><p class="text-xs text-gray-500 mt-1"><?= e($st) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

<?php elseif ($tab === 'orders'): ?>
  <div class="space-y-4">
    <!-- filters -->
    <div class="flex flex-col gap-2">
      <div class="flex gap-2 flex-wrap items-center">
        <span class="text-xs text-gray-400 w-16">Status</span>
        <?php foreach (['All', 'Pending', 'Processing', 'In Transit', 'Delivered', 'Cancelled'] as $s): ?>
          <a href="superadmin.php?tab=orders&status=<?= urlencode($s) ?>&pay=<?= urlencode($payFilter) ?>" class="text-sm border rounded-lg px-3 py-1.5 <?= $statusFilter === $s ? 'bg-green-50 text-green-600 border-green-200' : 'hover:bg-gray-50' ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="flex gap-2 flex-wrap items-center">
        <span class="text-xs text-gray-400 w-16">Payment</span>
        <?php foreach (['All', 'Paid', 'Unpaid', 'COD', 'Prepaid'] as $s): ?>
          <a href="superadmin.php?tab=orders&status=<?= urlencode($statusFilter) ?>&pay=<?= urlencode($s) ?>" class="text-sm border rounded-lg px-3 py-1.5 <?= $payFilter === $s ? 'bg-green-50 text-green-600 border-green-200' : 'hover:bg-gray-50' ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <p class="text-xs text-gray-400 flex items-center gap-1"><i data-lucide="mouse-pointer-click" class="w-3.5 h-3.5"></i> Click any order to see full details.</p>
    <div class="bg-white rounded-xl border overflow-x-auto">
      <?php if ($shownOrders): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-3 px-4">Order</th><th class="py-3 px-4">Customer</th><th class="py-3 px-4">Items</th><th class="py-3 px-4">Amount</th><th class="py-3 px-4">Payment</th><th class="py-3 px-4">Status</th><th class="py-3 px-4"></th>
          </tr></thead>
          <tbody>
            <?php foreach ($shownOrders as $o): $ps = order_payment_status($o); $slug = so_order_slug($o['id']); ?>
              <tr data-modal data-modal-title="Order <?= e($o['id']) ?>" data-modal-target="#od-<?= $slug ?>" class="border-b last:border-0 cursor-pointer hover:bg-green-50/60">
                <td class="py-3 px-4"><span class="font-medium text-green-700"><?= e($o['id']) ?></span><br><span class="text-xs text-gray-400"><?= e($o['date']) ?></span></td>
                <td class="py-3 px-4"><span class="font-medium"><?= e($o['customerName']) ?></span><?php if (!empty($o['customerEmail'])): ?><br><span class="text-xs text-gray-400"><?= e($o['customerEmail']) ?></span><?php endif; ?></td>
                <td class="py-3 px-4"><?= e($o['items']) ?></td>
                <td class="py-3 px-4 font-semibold">₹<?= e($o['total']) ?></td>
                <td class="py-3 px-4"><div class="flex flex-col gap-1 items-start"><span class="text-xs border rounded-full px-2 py-0.5"><?= e($o['paymentMethod'] ?: 'Online') ?></span><?= so_pay_badge($ps) ?></div></td>
                <td class="py-3 px-4"><?= so_status_badge($o['status']) ?></td>
                <td class="py-3 px-4 text-gray-300"><i data-lucide="chevron-right" class="w-4 h-4"></i></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php /* Hidden per-order detail cards, opened by each row's data-modal-target. */ ?>
        <?php foreach ($shownOrders as $o): ?>
          <div id="od-<?= so_order_slug($o['id']) ?>" class="hidden"><?= so_order_detail_html($o, $qs) ?></div>
        <?php endforeach; ?>
      <?php else: empty_state('No orders match these filters.', 'shopping-cart'); endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'offers'): ?>
  <div class="space-y-6 max-w-4xl">
    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center gap-2 mb-1"><i data-lucide="badge-percent" class="w-5 h-5 text-green-600"></i><h3 class="font-bold text-lg">Create an Offer</h3></div>
      <p class="text-sm text-gray-500 mb-4">Publish a promotion that customers will see on the main shopping page.</p>
      <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="do" value="offer_add" />
        <input type="hidden" name="redirect" value="superadmin.php?tab=offers" />
        <div><label class="block text-sm font-medium mb-1">Offer Title</label><input name="title" required placeholder="Weekend Grocery Sale" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Discount Type</label><select name="discount_type" class="w-full border rounded-lg px-3 py-2"><option value="percent">Percentage (%)</option><option value="flat">Flat amount (₹)</option></select></div>
        <div><label class="block text-sm font-medium mb-1">Discount Value</label><input name="discount_value" type="number" min="1" required placeholder="e.g. 20" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Minimum Order (₹) <span class="text-xs text-gray-400">(optional)</span></label><input name="min_order" type="number" min="0" value="0" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Coupon Code <span class="text-xs text-gray-400">(optional)</span></label><input name="code" placeholder="WEEKEND20" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Description</label><input name="description" placeholder="Save on everyday essentials" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Starts</label><input name="start_date" type="date" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Ends</label><input name="end_date" type="date" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div class="md:col-span-2"><p class="text-xs text-gray-500 mb-3">Customers who meet the minimum order will be able to pick this offer at checkout; the discount is applied automatically.</p><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg">Publish Offer</button></div>
      </form>
    </div>
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Published Offers</h3>
      <?php if ($offerList): ?>
        <div class="divide-y">
          <?php foreach ($offerList as $offer): ?><div class="py-3 flex items-center justify-between gap-4"><div><p class="font-medium"><?= e($offer['title']) ?> <span class="text-green-700 ml-2"><?= e($offer['discount'] ?: offer_discount_text($offer['discount_type'] ?? 'percent', (int) ($offer['discount_value'] ?? 0))) ?></span><?php if (!empty($offer['code'])): ?> <span class="text-xs font-mono bg-gray-100 rounded px-1.5 py-0.5 ml-1"><?= e($offer['code']) ?></span><?php endif; ?></p><p class="text-sm text-gray-500"><?= e($offer['description']) ?><?php if ((int) ($offer['min_order'] ?? 0) > 0): ?> · min order ₹<?= (int) $offer['min_order'] ?><?php endif; ?></p></div><span class="text-xs text-gray-500 whitespace-nowrap"><?= e($offer['start_date']) ?> to <?= e($offer['end_date']) ?></span></div><?php endforeach; ?>
        </div>
      <?php else: ?><p class="text-sm text-gray-500">No offers published yet.</p><?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'returns'): ?>
  <div class="bg-white rounded-xl border overflow-x-auto">
    <div class="p-6">
      <h3 class="font-bold text-lg mb-4">Return Requests <span class="text-gray-400 font-normal">(<?= count($returns) ?><?= $pendingReturns ? ' · ' . $pendingReturns . ' pending' : '' ?>)</span></h3>
      <?php if ($returns): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Order</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Item</th><th class="py-2 pr-4">Qty</th><th class="py-2 pr-4">Reason</th><th class="py-2 pr-4">Status</th><th class="py-2">Action</th>
          </tr></thead>
          <tbody>
            <?php foreach ($returns as $r): ?>
              <tr class="border-b last:border-0 align-top">
                <td class="py-3 pr-4 text-gray-500 whitespace-nowrap"><?= e($r['date']) ?></td>
                <td class="py-3 pr-4 font-medium text-green-700"><?= e($r['orderId']) ?></td>
                <td class="py-3 pr-4"><span class="font-medium"><?= e($r['customerName'] ?: '—') ?></span><?php if (!empty($r['customerEmail'])): ?><br><span class="text-xs text-gray-400"><?= e($r['customerEmail']) ?></span><?php endif; ?></td>
                <td class="py-3 pr-4"><?= e($r['item']) ?></td>
                <td class="py-3 pr-4"><?= e($r['quantity']) ?></td>
                <td class="py-3 pr-4 max-w-[18rem] text-gray-600"><?= e($r['reason']) ?></td>
                <td class="py-3 pr-4"><?= so_return_badge($r['status']) ?></td>
                <td class="py-3">
                  <?php if ($r['status'] === 'Requested'): ?>
                    <div class="flex gap-1.5">
                      <?php foreach ([['Approved', 'Approve', 'bg-green-600 hover:bg-green-700 text-white'], ['Rejected', 'Reject', 'border text-red-600 border-red-200 hover:bg-red-50']] as $b): ?>
                        <form method="post" action="actions.php">
                          <input type="hidden" name="do" value="return_status" />
                          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                          <input type="hidden" name="status" value="<?= $b[0] ?>" />
                          <button class="text-xs rounded-lg px-2.5 py-1 <?= $b[2] ?>"><?= $b[1] ?></button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($r['status'] === 'Approved'): ?>
                    <form method="post" action="actions.php">
                      <input type="hidden" name="do" value="return_status" />
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                      <input type="hidden" name="status" value="Refunded" />
                      <button class="text-xs rounded-lg px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white">Mark Refunded</button>
                    </form>
                  <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state('No return requests yet.', 'undo-2'); endif; ?>
    </div>
  </div>

<?php else: /* customers */ ?>
  <div class="bg-white rounded-xl border p-6 overflow-x-auto">
    <h3 class="font-bold text-lg mb-4">Customers <span class="text-gray-400 font-normal">(<?= count($customers) ?> can log in)</span></h3>
    <?php if ($customers): ?>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Login Email</th><th class="py-2 pr-4">Phone</th><th class="py-2 pr-4">Delivery Address</th><th class="py-2">Orders</th></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): $addr = format_address($c['address'] ?? '', $c['city'] ?? '', $c['pincode'] ?? ''); ?>
            <tr class="border-b last:border-0 align-top">
              <td class="py-3 pr-4 font-medium"><?= e($c['name'] ?: '—') ?></td>
              <td class="py-3 pr-4 text-green-700"><?= e($c['email']) ?></td>
              <td class="py-3 pr-4"><?= e($c['phone'] ?: '—') ?></td>
              <td class="py-3 pr-4 max-w-[18rem] text-gray-600"><?= $addr !== '' ? e($addr) : '<span class="text-amber-600 text-xs">No address set</span>' ?></td>
              <td class="py-3"><?= (int) ($orderCountByEmail[strtolower($c['email'])] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: empty_state('No customers have signed up yet.', 'users'); endif; ?>
  </div>
<?php endif; ?>

<?php
render_dashboard_end();
require __DIR__ . '/partials/foot.php';
