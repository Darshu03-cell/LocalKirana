<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dashboard.php';

$user = require_role('delivery');
$tab  = $_GET['tab'] ?? 'dashboard';

// Resolve this partner's record (linked by login email) and show only their jobs.
$partner             = delivery_partner_by_email($user['email'] ?? '');
$activeDeliveries    = $partner ? deliveries_for_partner((int) $partner['id']) : [];
$completedDeliveries = completed_deliveries();
$todayEarnings       = array_sum(array_column($completedDeliveries, 'earnings'));

function maps_link(string $address): string {
  return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
}

$nf = dashboard_notif_shell('delivery', $user['email'] ?? '');

$shell = [
  'panelTitle' => 'Delivery Panel', 'panelSubtitle' => $user['name'], 'baseUrl' => 'delivery.php',
  'activeTab' => $tab, 'headerSubtitle' => 'Manage your deliveries', 'roleLabel' => 'Delivery Partner',
  'userName' => $user['name'], 'notifList' => $nf['list'], 'notifUnread' => $nf['unread'],
  'nav' => [
    ['id' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['id' => 'active',    'icon' => 'package',          'label' => 'Active Deliveries', 'badge' => $activeDeliveries ? (string) count($activeDeliveries) : null],
    ['id' => 'completed', 'icon' => 'check-circle',     'label' => 'Completed'],
    ['id' => 'earnings',  'icon' => 'indian-rupee',     'label' => 'Earnings'],
  ],
];

$page_title = 'Delivery Dashboard · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
render_dashboard_start($shell);
?>

<?php if ($tab === 'dashboard'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <?php
      stat_card((string) count($activeDeliveries), 'Active Deliveries', 'package', 'bg-blue-100', 'text-blue-600');
      stat_card('₹' . number_format($todayEarnings), 'Earnings', 'indian-rupee', 'bg-green-100', 'text-green-600');
      stat_card((string) count($completedDeliveries), 'Completed', 'trending-up', 'bg-purple-100', 'text-purple-600');
      stat_card($user['phone'] ? '✓' : '—', 'Profile', 'check-circle', 'bg-orange-100', 'text-orange-600');
      ?>
    </div>

    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Active Deliveries</h3>
      <?php if ($activeDeliveries): ?>
        <div class="space-y-4">
          <?php foreach ($activeDeliveries as $d): ?>
            <div class="border rounded-lg p-4">
              <div class="flex items-start justify-between mb-3">
                <div><div class="flex items-center gap-2 mb-1"><h4 class="font-bold"><?= e($d['id']) ?></h4><span class="text-xs font-medium px-2 py-1 rounded-full <?= ($d['status'] ?? '') === 'Picked Up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>"><?= e($d['status'] ?? '') ?></span></div><p class="text-sm text-gray-600"><?= e($d['customer'] ?? '') ?></p></div>
                <span class="text-lg font-bold text-green-600">₹<?= e($d['amount'] ?? 0) ?></span>
              </div>
              <div class="flex items-start gap-2 mb-3 text-sm text-gray-600"><i data-lucide="map-pin" class="w-4 h-4 mt-0.5 flex-shrink-0"></i><p><?= e($d['address'] ?? '') ?></p></div>
              <div class="flex items-center justify-between pt-3 border-t flex-wrap gap-2">
                <span class="text-sm">OTP: <span class="font-bold text-green-600"><?= e($d['otp'] ?? '') ?></span></span>
                <div class="flex gap-2">
                  <form method="post" action="actions.php" <?= ($d['status'] ?? '') === 'Picked Up' ? 'data-confirm="Confirm delivery complete?"' : '' ?>><input type="hidden" name="do" value="delivery_advance" /><input type="hidden" name="id" value="<?= e($d['id']) ?>" /><button class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded-lg"><?= ($d['status'] ?? '') === 'Assigned' ? 'Pick Up Order' : 'Complete Delivery' ?></button></form>
                  <a href="<?= e(maps_link($d['address'] ?? '')) ?>" target="_blank" rel="noopener" class="text-sm border rounded-lg px-3 py-1.5 hover:bg-gray-50">Navigate</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: empty_state('No active deliveries assigned to you yet.', 'package'); endif; ?>
    </div>

    <div class="bg-white rounded-xl border p-6 overflow-x-auto">
      <h3 class="font-bold text-lg mb-4">Completed</h3>
      <?php if ($completedDeliveries): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Earnings</th><th class="py-2">Time</th></tr></thead>
          <tbody>
            <?php foreach ($completedDeliveries as $d): ?>
              <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($d['id']) ?></td><td class="py-3 pr-4"><?= e($d['customer']) ?></td><td class="py-3 pr-4">₹<?= e($d['amount']) ?></td><td class="py-3 pr-4 font-semibold text-green-600">₹<?= e($d['earnings']) ?></td><td class="py-3"><?= e($d['time']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state('No completed deliveries yet.', 'check-circle'); endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'active'): ?>
  <?php if ($activeDeliveries): ?>
    <div class="space-y-4">
      <?php foreach ($activeDeliveries as $d): ?>
        <div class="bg-white rounded-xl border p-6">
          <div class="flex items-start justify-between mb-4 flex-wrap gap-4">
            <div>
              <div class="flex items-center gap-2 mb-2"><h4 class="font-bold text-lg"><?= e($d['id']) ?></h4><span class="text-xs font-medium px-2 py-1 rounded-full <?= ($d['status'] ?? '') === 'Picked Up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>"><?= e($d['status'] ?? '') ?></span></div>
              <p class="text-gray-600 mb-2"><?= e($d['customer'] ?? '') ?></p>
              <div class="flex items-start gap-2 text-sm text-gray-600"><i data-lucide="map-pin" class="w-4 h-4 mt-0.5 flex-shrink-0"></i><p><?= e($d['address'] ?? '') ?></p></div>
            </div>
            <div class="text-right"><p class="text-sm text-gray-600">Order Amount</p><p class="text-2xl font-bold text-green-600">₹<?= e($d['amount'] ?? 0) ?></p></div>
          </div>
          <div class="bg-gray-50 p-3 rounded-lg mb-4"><p class="text-xs text-gray-600 mb-1">Delivery OTP</p><p class="font-semibold text-green-600 text-lg"><?= e($d['otp'] ?? '') ?></p></div>
          <div class="flex gap-3">
            <form method="post" action="actions.php" class="flex-1" <?= ($d['status'] ?? '') === 'Picked Up' ? 'data-confirm="Confirm delivery complete?"' : '' ?>><input type="hidden" name="do" value="delivery_advance" /><input type="hidden" name="id" value="<?= e($d['id']) ?>" /><input type="hidden" name="redirect" value="delivery.php?tab=active" /><button class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"><?= ($d['status'] ?? '') === 'Assigned' ? 'Pick Up Order' : 'Complete Delivery' ?></button></form>
            <a href="<?= e(maps_link($d['address'] ?? '')) ?>" target="_blank" rel="noopener" class="flex-1 border rounded-lg px-4 py-2 flex items-center justify-center gap-2 hover:bg-gray-50"><i data-lucide="navigation" class="w-4 h-4"></i>Navigate</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-xl border"><?php empty_state('No active deliveries. New assignments will show up here.', 'package'); ?></div>
  <?php endif; ?>

<?php elseif ($tab === 'completed'): ?>
  <div class="bg-white rounded-xl border p-6 overflow-x-auto">
    <h3 class="font-bold text-lg mb-4">Completed Deliveries</h3>
    <?php if ($completedDeliveries): ?>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Earnings</th><th class="py-2">Time</th></tr></thead>
        <tbody>
          <?php foreach ($completedDeliveries as $d): ?>
            <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($d['id']) ?></td><td class="py-3 pr-4"><?= e($d['customer']) ?></td><td class="py-3 pr-4">₹<?= e($d['amount']) ?></td><td class="py-3 pr-4 font-semibold text-green-600">₹<?= e($d['earnings']) ?></td><td class="py-3"><?= e($d['time']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: empty_state('No completed deliveries yet.', 'check-circle'); endif; ?>
  </div>

<?php else: /* earnings */ ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white rounded-xl border p-6"><h3 class="text-sm text-gray-600 mb-2">Total Earnings</h3><p class="text-3xl font-bold text-green-600">₹<?= number_format($todayEarnings) ?></p><p class="text-sm text-gray-500 mt-1"><?= count($completedDeliveries) ?> deliveries</p></div>
      <div class="bg-white rounded-xl border p-6"><h3 class="text-sm text-gray-600 mb-2">Completed</h3><p class="text-3xl font-bold text-blue-600"><?= count($completedDeliveries) ?></p><p class="text-sm text-gray-500 mt-1">all time</p></div>
      <div class="bg-white rounded-xl border p-6"><h3 class="text-sm text-gray-600 mb-2">Active</h3><p class="text-3xl font-bold text-purple-600"><?= count($activeDeliveries) ?></p><p class="text-sm text-gray-500 mt-1">in progress</p></div>
    </div>
    <div class="bg-white rounded-xl border p-6 overflow-x-auto">
      <h3 class="font-bold text-lg mb-4">Earnings by Delivery</h3>
      <?php if ($completedDeliveries): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Time</th><th class="py-2">Earnings</th></tr></thead>
          <tbody>
            <?php foreach ($completedDeliveries as $d): ?>
              <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($d['id']) ?></td><td class="py-3 pr-4"><?= e($d['customer']) ?></td><td class="py-3 pr-4"><?= e($d['time']) ?></td><td class="py-3 pr-4 font-semibold text-green-600">₹<?= e($d['earnings']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state('No earnings yet — complete a delivery to start earning.', 'indian-rupee'); endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
render_dashboard_end();
require __DIR__ . '/partials/foot.php';
