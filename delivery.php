<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dashboard.php';

$user = require_role('delivery');
$tab  = $_GET['tab'] ?? 'dashboard';

// Resolve this partner's record (linked by login email) and show only their jobs.
$partner = delivery_partner_by_email($user['email'] ?? '');
if (!$partner) {
  // Older accounts may have no partner row yet — create it so the profile can be filled in.
  $partner = delivery_profile_create(['name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'phone' => $user['phone'] ?? '', 'vehicle' => 'Bike']);
}
$activeDeliveries    = deliveries_for_partner((int) $partner['id']);
$completedDeliveries = completed_deliveries_for_partner((int) $partner['id']);
$todayEarnings       = array_sum(array_column($completedDeliveries, 'earnings'));
$idStatus            = partner_id_status($partner);
$missing             = partner_profile_missing($partner);

function maps_link(string $address): string {
  return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
}

/** Cash on Delivery jobs need the amount collected on hand-over. */
function delivery_is_cod(array $d): bool { return ($d['paymentMethod'] ?? '') === 'Cash on Delivery'; }

/** Button label: pick up, then collect-cash (COD) or just complete (prepaid). */
function delivery_action_label(array $d): string {
  if (($d['status'] ?? '') === 'Assigned') return 'Pick Up Order';
  return delivery_is_cod($d) ? ('Collect ₹' . (int) ($d['amount'] ?? 0) . ' & Complete') : 'Complete Delivery';
}

/** Confirmation prompt shown before completing a delivery. */
function delivery_confirm_msg(array $d): string {
  if (($d['status'] ?? '') !== 'Picked Up') return '';
  return delivery_is_cod($d)
    ? ('Collect ₹' . (int) ($d['amount'] ?? 0) . ' cash and mark this order delivered?')
    : 'Confirm delivery complete?';
}

/** Small payment chip: amber "cash to collect" (COD) or green "prepaid". */
function delivery_pay_chip(array $d): void {
  if (delivery_is_cod($d)) {
    echo '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-800 px-2.5 py-1 text-xs font-medium"><i data-lucide="wallet" class="w-3.5 h-3.5"></i>Cash to collect: ₹' . (int) ($d['amount'] ?? 0) . '</span>';
  } else {
    echo '<span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-700 px-2.5 py-1 text-xs font-medium"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i>Prepaid · paid online</span>';
  }
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
    ['id' => 'profile',   'icon' => 'id-card',          'label' => 'Profile & ID', 'badge' => $idStatus === 'Verified' ? null : '!'],
  ],
];

/** Coloured chip for the ID verification state. */
function id_status_chip(string $s): string {
  $map = ['Verified' => 'bg-green-100 text-green-700', 'Pending' => 'bg-amber-100 text-amber-700', 'Rejected' => 'bg-red-100 text-red-700'];
  $icon = ['Verified' => 'shield-check', 'Pending' => 'clock', 'Rejected' => 'x-circle'][$s] ?? 'alert-circle';
  $text = ['Pending' => 'Awaiting verification'][$s] ?? $s;
  return '<span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ' . ($map[$s] ?? 'bg-gray-100 text-gray-700') . '"><i data-lucide="' . $icon . '" class="w-3.5 h-3.5"></i>' . e($text) . '</span>';
}

$page_title = 'Delivery Dashboard · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
render_dashboard_start($shell);
?>

<?php if ($idStatus !== 'Verified' && $tab !== 'profile'): ?>
  <a href="delivery.php?tab=profile" class="mb-6 flex items-start gap-3 rounded-xl border p-4 <?= $idStatus === 'Rejected' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800' ?>">
    <i data-lucide="id-card" class="w-5 h-5 mt-0.5 flex-shrink-0"></i>
    <span class="text-sm">
      <?php if ($idStatus === 'Pending'): ?><strong>Your ID proof is being verified.</strong> You'll start receiving orders as soon as an admin approves it.
      <?php elseif ($idStatus === 'Rejected'): ?><strong>Your ID proof was rejected.</strong> <?= e($partner['idNote'] ?: 'Please upload a clear, valid document.') ?> Tap to re-upload.
      <?php else: ?><strong>Complete your profile to start delivering.</strong> Add your <?= e(implode(', ', $missing) ?: 'details') ?> — customers can only choose verified partners.<?php endif; ?>
    </span>
  </a>
<?php endif; ?>

<?php if ($tab === 'dashboard'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <?php
      stat_card((string) count($activeDeliveries), 'Active Deliveries', 'package', 'bg-blue-100', 'text-blue-600');
      stat_card('₹' . number_format($todayEarnings), 'Earnings', 'indian-rupee', 'bg-green-100', 'text-green-600');
      stat_card((string) count($completedDeliveries), 'Completed', 'trending-up', 'bg-purple-100', 'text-purple-600');
      stat_card($idStatus === 'Verified' ? '✓' : '—', $idStatus === 'Verified' ? 'ID Verified' : 'ID ' . $idStatus, 'shield-check', 'bg-orange-100', 'text-orange-600');
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
              <div class="mb-3"><?php delivery_pay_chip($d); ?></div>
              <div class="flex items-center justify-between pt-3 border-t flex-wrap gap-2">
                <span class="text-sm">OTP: <span class="font-bold text-green-600"><?= e($d['otp'] ?? '') ?></span></span>
                <div class="flex gap-2">
                  <form method="post" action="actions.php" <?php $c = delivery_confirm_msg($d); echo $c ? 'data-confirm="' . e($c) . '"' : ''; ?>><input type="hidden" name="do" value="delivery_advance" /><input type="hidden" name="id" value="<?= e($d['id']) ?>" /><button class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded-lg"><?= e(delivery_action_label($d)) ?></button></form>
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
          <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="bg-gray-50 p-3 rounded-lg flex-1 min-w-[8rem]"><p class="text-xs text-gray-600 mb-1">Delivery OTP</p><p class="font-semibold text-green-600 text-lg"><?= e($d['otp'] ?? '') ?></p></div>
            <div><?php delivery_pay_chip($d); ?></div>
          </div>
          <div class="flex gap-3">
            <form method="post" action="actions.php" class="flex-1" <?php $c = delivery_confirm_msg($d); echo $c ? 'data-confirm="' . e($c) . '"' : ''; ?>><input type="hidden" name="do" value="delivery_advance" /><input type="hidden" name="id" value="<?= e($d['id']) ?>" /><input type="hidden" name="redirect" value="delivery.php?tab=active" /><button class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"><?= e(delivery_action_label($d)) ?></button></form>
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

<?php elseif ($tab === 'profile'): $p = $partner; ?>
  <form method="post" action="actions.php" enctype="multipart/form-data" class="space-y-6 max-w-3xl">
    <input type="hidden" name="do" value="delivery_profile_save" />

    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
        <div class="flex items-center gap-4">
          <?php if (!empty($p['photo'])): ?>
            <img src="<?= e($p['photo']) ?>" alt="" class="w-20 h-20 rounded-full object-cover border" />
          <?php else: ?>
            <div class="w-20 h-20 rounded-full bg-gray-100 border flex items-center justify-center text-gray-400"><i data-lucide="user" class="w-9 h-9"></i></div>
          <?php endif; ?>
          <div>
            <h3 class="font-bold text-lg"><?= e($p['name'] ?: $user['name']) ?></h3>
            <p class="text-sm text-gray-500"><?= e($user['email']) ?></p>
            <div class="mt-1.5"><?= id_status_chip($idStatus) ?></div>
          </div>
        </div>
      </div>
      <?php if ($idStatus === 'Rejected' && !empty($p['idNote'])): ?>
        <p class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">Reason from admin: <?= e($p['idNote']) ?></p>
      <?php endif; ?>

      <h4 class="font-semibold mb-3 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4 text-green-600"></i>Personal details</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium mb-1" for="dp-name">Full name <span class="text-red-500">*</span></label><input id="dp-name" name="name" required value="<?= e($p['name'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1" for="dp-phone">Contact number <span class="text-red-500">*</span></label><input id="dp-phone" name="phone" type="tel" required value="<?= e($p['phone'] ?? '') ?>" placeholder="+91 98765 43210" class="w-full border rounded-lg px-3 py-2" /></div>
        <div class="md:col-span-2"><label class="block text-sm font-medium mb-1" for="dp-address">Address <span class="text-red-500">*</span></label><textarea id="dp-address" name="address" required rows="2" placeholder="House / street / area, city, pincode" class="w-full border rounded-lg px-3 py-2"><?= e($p['address'] ?? '') ?></textarea></div>
        <div><label class="block text-sm font-medium mb-1" for="dp-vehicle">Vehicle</label>
          <select id="dp-vehicle" name="vehicle" class="w-full border rounded-lg px-3 py-2"><?php foreach (['Bike', 'Scooter', 'Bicycle', 'Car', 'Van'] as $v): ?><option <?= ($p['vehicle'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        </div>
        <div><label class="block text-sm font-medium mb-1" for="dp-photo">Profile photograph <?= empty($p['photo']) ? '<span class="text-red-500">*</span>' : '' ?></label><input id="dp-photo" name="photo_file" type="file" accept="image/jpeg,image/png,image/webp" <?= empty($p['photo']) ? 'required' : '' ?> class="w-full text-sm border rounded-lg px-3 py-1.5 file:mr-3 file:border-0 file:bg-green-50 file:text-green-700 file:rounded file:px-2 file:py-1" /><p class="text-xs text-gray-500 mt-1">A clear photo of your face — customers see it so they can recognise you.</p></div>
        <div class="md:col-span-2"><?php location_fields($p['lat'] ?? null, $p['lng'] ?? null); ?><p class="text-xs text-gray-500 mt-1">Used to show you to customers nearby. Customers only see your approximate distance, never your address.</p></div>
      </div>
    </div>

    <div class="bg-white rounded-xl border p-6">
      <h4 class="font-semibold mb-1 flex items-center gap-2"><i data-lucide="id-card" class="w-4 h-4 text-green-600"></i>ID proof verification</h4>
      <p class="text-sm text-gray-500 mb-4">Upload any one government ID. It is stored privately — only you and the admin team can view it.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium mb-1" for="dp-idtype">Document type</label>
          <select id="dp-idtype" name="id_type" class="w-full border rounded-lg px-3 py-2">
            <option value="">— Choose one —</option>
            <?php foreach (PARTNER_ID_TYPES as $t): ?><option <?= ($p['idType'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-medium mb-1" for="dp-idnum">Document number</label><input id="dp-idnum" name="id_number" value="" placeholder="<?= !empty($p['idNumber']) ? e('Saved: ' . partner_id_masked($p['idNumber'])) : 'e.g. 1234 5678 9012' ?>" autocomplete="off" class="w-full border rounded-lg px-3 py-2" /><p class="text-xs text-gray-500 mt-1">Aadhaar: 12 digits · PAN: ABCDE1234F · Voter ID: ABC1234567<?= !empty($p['idNumber']) ? ' · leave blank to keep the saved one' : '' ?></p></div>
        <div class="md:col-span-2"><label class="block text-sm font-medium mb-1" for="dp-idfile">Photo or scan of the document</label><input id="dp-idfile" name="id_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full text-sm border rounded-lg px-3 py-1.5 file:mr-3 file:border-0 file:bg-green-50 file:text-green-700 file:rounded file:px-2 file:py-1" />
          <p class="text-xs text-gray-500 mt-1">JPG, PNG, WebP or PDF, up to 5 MB.<?php if (!empty($p['idDoc'])): ?> <a href="id-doc.php?id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener" class="text-green-700 font-medium hover:underline">View uploaded <?= e($p['idType'] ?: 'document') ?></a><?php endif; ?></p>
        </div>
      </div>
      <?php if ($idStatus === 'Verified'): ?><p class="text-xs text-amber-700 mt-4">Changing your ID details sends your profile back for verification — you won't receive new orders until it is approved again.</p><?php endif; ?>
    </div>

    <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-medium">Save profile</button>
  </form>

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
