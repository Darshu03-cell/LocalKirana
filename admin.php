<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dashboard.php';

$user = require_role('admin');
$tab  = $_GET['tab'] ?? 'dashboard';

$vendorList   = vendors_list();
$supplierList = suppliers_list();
$partnerList  = delivery_partners();
$orderList    = orders();
$accountList  = accounts_all();
$settings     = shop_settings();
$CATS         = category_names();
$offerList    = offers();

// App Users tab: filter registered accounts by role
$uroleFilter = $_GET['urole'] ?? 'all';
$shownAccounts = $uroleFilter === 'all'
  ? $accountList
  : array_values(array_filter($accountList, fn($a) => ($a['role'] ?? '') === $uroleFilter));
$roleCounts = [];
foreach ($accountList as $a) { $r = $a['role'] ?? 'other'; $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1; }

function role_badge(string $role): string {
  $map = [
    'admin'    => 'bg-gray-800 text-white',
    'vendor'   => 'bg-green-100 text-green-700',
    'supplier' => 'bg-orange-100 text-orange-700',
    'delivery' => 'bg-blue-100 text-blue-700',
    'customer' => 'bg-purple-100 text-purple-700',
  ];
  return '<span class="text-xs font-medium px-2 py-1 rounded-full ' . ($map[$role] ?? 'bg-gray-100 text-gray-700') . '">' . e(ucfirst($role)) . '</span>';
}

$revenue = array_sum(array_column($orderList, 'total'));

$editVendor = isset($_GET['edit']) ? find_by_id($vendorList, (int) $_GET['edit']) : null;
$showVendorForm = isset($_GET['new']) || $editVendor;

// Orders by status (real)
$statusCounts = [];
foreach ($orderList as $o) { $statusCounts[$o['status']] = ($statusCounts[$o['status']] ?? 0) + 1; }

$nf = dashboard_notif_shell('admin', $user['email'] ?? '');

$shell = [
  'panelTitle' => 'Admin Panel', 'panelSubtitle' => 'System Control', 'baseUrl' => 'admin.php',
  'activeTab' => $tab, 'headerSubtitle' => 'Manage your entire platform', 'roleLabel' => 'Administrator',
  'userName' => $user['name'], 'notifList' => $nf['list'], 'notifUnread' => $nf['unread'],
  'nav' => [
    ['id' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['id' => 'setup',     'icon' => 'settings',         'label' => 'Shop Setup'],
    ['id' => 'offers',    'icon' => 'badge-percent',     'label' => 'Offers', 'badge' => $offerList ? (string) count($offerList) : null],
    ['id' => 'vendors',   'icon' => 'store',            'label' => 'Vendors', 'badge' => count($vendorList) ? (string) count($vendorList) : null],
    ['id' => 'suppliers', 'icon' => 'package',          'label' => 'Suppliers'],
    ['id' => 'delivery',  'icon' => 'truck',            'label' => 'Delivery Partners'],
    ['id' => 'customers', 'icon' => 'users',            'label' => 'Customers'],
    ['id' => 'users',     'icon' => 'key-round',        'label' => 'App Users', 'badge' => $accountList ? (string) count($accountList) : null],
    ['id' => 'analytics', 'icon' => 'trending-up',      'label' => 'Analytics'],
  ],
];

$page_title = 'Admin Dashboard · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
render_dashboard_start($shell);
?>

<?php if ($tab === 'dashboard'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <?php
      stat_card('₹' . number_format($revenue), 'Total Revenue', 'indian-rupee', 'bg-green-100', 'text-green-600');
      stat_card((string) count($orderList), 'Total Orders', 'shopping-cart', 'bg-blue-100', 'text-blue-600');
      stat_card((string) count($vendorList), 'Vendors', 'store', 'bg-purple-100', 'text-purple-600');
      stat_card((string) count($supplierList), 'Suppliers', 'package', 'bg-orange-100', 'text-orange-600');
      ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Order Status Distribution</h3>
        <?php if ($statusCounts): ?>
          <?php render_chart('adminPie', 'pie', ['data' => ['labels' => array_keys($statusCounts), 'datasets' => [['data' => array_values($statusCounts), 'backgroundColor' => ['#16a34a', '#3b82f6', '#f59e0b', '#9ca3af', '#ef4444']]]], 'options' => ['maintainAspectRatio' => false]], 260); ?>
        <?php else: empty_state('No orders on the platform yet.', 'pie-chart'); endif; ?>
      </div>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Platform Overview</h3>
        <?php if ($vendorList || $supplierList || $partnerList): ?>
          <?php render_chart('adminBar', 'bar', ['data' => ['labels' => ['Vendors', 'Suppliers', 'Delivery', 'Orders'], 'datasets' => [['label' => 'Count', 'data' => [count($vendorList), count($supplierList), count($partnerList), count($orderList)], 'backgroundColor' => ['#8b5cf6', '#f97316', '#3b82f6', '#16a34a']]]], 'options' => ['plugins' => ['legend' => ['display' => false]], 'maintainAspectRatio' => false]], 260); ?>
        <?php else: empty_state('No participants yet. Vendors, suppliers and partners appear here as they sign up.', 'users'); endif; ?>
      </div>
    </div>

    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Pending Approvals</h3>
      <div class="space-y-3">
        <?php foreach (approvals() as $item): ?>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div><p class="text-sm font-medium"><?= e($item['name']) ?></p><p class="text-xs text-gray-500"><?= e($item['type']) ?> - <?= e($item['action']) ?></p></div>
            <div class="flex gap-2">
              <form method="post" action="actions.php"><input type="hidden" name="do" value="approval" /><input type="hidden" name="id" value="<?= e($item['id']) ?>" /><input type="hidden" name="name" value="<?= e($item['name']) ?>" /><input type="hidden" name="decision" value="approve" /><input type="hidden" name="redirect" value="admin.php" /><button class="border rounded-lg h-8 w-8 flex items-center justify-center hover:bg-green-50" title="Approve"><i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i></button></form>
              <form method="post" action="actions.php"><input type="hidden" name="do" value="approval" /><input type="hidden" name="id" value="<?= e($item['id']) ?>" /><input type="hidden" name="name" value="<?= e($item['name']) ?>" /><input type="hidden" name="decision" value="reject" /><input type="hidden" name="redirect" value="admin.php" /><button class="border rounded-lg h-8 w-8 flex items-center justify-center hover:bg-red-50" title="Reject"><i data-lucide="x-circle" class="w-4 h-4 text-red-600"></i></button></form>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!approvals()): ?><p class="text-sm text-gray-500">No pending approvals.</p><?php endif; ?>
      </div>
    </div>

    <div class="bg-white rounded-xl border p-6 overflow-x-auto">
      <h3 class="font-bold text-lg mb-4">Vendors</h3>
      <?php if ($vendorList): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Store</th><th class="py-2 pr-4">Owner</th><th class="py-2 pr-4">City</th><th class="py-2 pr-4">Login email</th><th class="py-2">Products</th></tr></thead>
          <tbody>
            <?php foreach ($vendorList as $v): $pc = count(array_filter(products(), fn($p) => ($p['vendor'] ?? '') === $v['name'])); ?>
              <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($v['name']) ?></td><td class="py-3 pr-4"><?= e($v['owner']) ?></td><td class="py-3 pr-4"><?= e($v['city'] ?: '—') ?></td><td class="py-3 pr-4 text-green-700"><?= e($v['email'] ?: '—') ?></td><td class="py-3"><?= $pc ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state('No vendors yet. Add one, or let vendors sign up themselves.', 'store', 'admin.php?tab=vendors&new=1', 'Add Vendor'); endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'vendors'): ?>
  <div class="space-y-6">
    <div class="flex justify-between items-center flex-wrap gap-4">
      <h3 class="text-lg font-bold">Vendors <span class="text-gray-400 font-normal">(<?= count($vendorList) ?>)</span></h3>
      <a href="admin.php?tab=vendors&new=1" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">+ Add Vendor</a>
    </div>

    <?php if ($showVendorForm): ?>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4"><?= $editVendor ? 'Edit Vendor' : 'Add Vendor' ?></h3>
        <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="hidden" name="do" value="<?= $editVendor ? 'vendor_update' : 'vendor_add' ?>" />
          <?php if ($editVendor): ?><input type="hidden" name="id" value="<?= e($editVendor['id']) ?>" /><?php endif; ?>
          <div><label class="block text-sm font-medium mb-1">Store Name</label><input name="name" required value="<?= e($editVendor['name'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Owner</label><input name="owner" value="<?= e($editVendor['owner'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Address</label><input name="address" value="<?= e($editVendor['address'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">City</label><input name="city" value="<?= e($editVendor['city'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Phone</label><input name="phone" value="<?= e($editVendor['phone'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div class="md:col-span-2 pt-2 border-t"><p class="text-sm font-semibold text-gray-700 mt-2">Vendor login credentials</p><p class="text-xs text-gray-500">The vendor signs in on the <span class="font-medium">Vendor</span> tab with these.</p></div>
          <div><label class="block text-sm font-medium mb-1">Login Email</label><input name="email" type="email" <?= $editVendor ? '' : 'required' ?> value="<?= e($editVendor['email'] ?? '') ?>" placeholder="vendor@store.com" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Password <?= $editVendor ? '<span class="text-xs text-gray-400">(leave blank to keep)</span>' : '' ?></label><input name="password" type="text" <?= $editVendor ? '' : 'required' ?> placeholder="set a password" class="w-full border rounded-lg px-3 py-2" /></div>
          <div class="md:col-span-2 flex gap-3"><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg"><?= $editVendor ? 'Save Changes' : 'Add Vendor' ?></button><a href="admin.php?tab=vendors" class="px-5 py-2 border rounded-lg hover:bg-gray-50">Cancel</a></div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($vendorList): ?>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($vendorList as $v): $slug = 'v' . $v['id']; ?>
          <div class="bg-white rounded-xl border overflow-hidden">
            <img src="<?= e($v['image']) ?>" alt="<?= e($v['name']) ?>" class="w-full h-40 object-cover" />
            <div class="p-4">
              <div class="flex items-start justify-between mb-3 gap-2">
                <div><h3 class="font-bold"><?= e($v['name']) ?></h3><p class="text-sm text-gray-600"><?= e($v['owner']) ?></p></div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                  <?php if ($v['verified']): ?><span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700">✓ Verified</span><?php endif; ?>
                  <?php if (!empty($v['hygiene_verified'])): ?><span class="text-xs font-medium px-2 py-1 rounded-full bg-teal-100 text-teal-700 inline-flex items-center gap-1"><i data-lucide="shield-check" class="w-3 h-3"></i>Hygiene</span><?php endif; ?>
                </div>
              </div>
              <div class="space-y-2 text-sm">
                <p class="text-gray-600"><?= e($v['address'] ?: '—') ?></p><p class="text-gray-600"><?= e($v['phone'] ?: '—') ?></p>
                <?php if (!empty($v['email'])): ?><p class="text-green-700 flex items-center gap-1"><i data-lucide="log-in" class="w-3.5 h-3.5"></i><?= e($v['email']) ?></p><?php endif; ?>
              </div>
              <form method="post" action="actions.php" class="mt-3">
                <input type="hidden" name="do" value="toggle_hygiene" />
                <input type="hidden" name="type" value="vendor" />
                <input type="hidden" name="id" value="<?= e($v['id']) ?>" />
                <input type="hidden" name="redirect" value="admin.php?tab=vendors" />
                <button class="w-full text-sm rounded-lg px-3 py-1.5 inline-flex items-center justify-center gap-1.5 <?= !empty($v['hygiene_verified']) ? 'bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100' : 'border hover:bg-gray-50 text-gray-700' ?>"><i data-lucide="shield-check" class="w-4 h-4"></i><?= !empty($v['hygiene_verified']) ? 'Remove hygiene certification' : 'Mark Hygiene Certified' ?></button>
              </form>
              <div class="flex gap-2 mt-2">
                <button type="button" data-modal data-modal-title="<?= e($v['name']) ?>" data-modal-target="#<?= $slug ?>" class="flex-1 text-sm border rounded-lg px-3 py-1.5 hover:bg-gray-50">View</button>
                <a href="admin.php?tab=vendors&edit=<?= e($v['id']) ?>" class="flex-1 text-center text-sm border rounded-lg px-3 py-1.5 hover:bg-gray-50">Edit</a>
                <form method="post" action="actions.php" data-confirm="Remove &quot;<?= e($v['name']) ?>&quot; and its login?"><input type="hidden" name="do" value="vendor_delete" /><input type="hidden" name="id" value="<?= e($v['id']) ?>" /><button class="text-sm border rounded-lg px-3 py-1.5 hover:bg-red-50 text-red-600 border-red-200">Delete</button></form>
              </div>
            </div>
          </div>
          <div id="<?= $slug ?>" hidden>
            <img src="<?= e($v['image']) ?>" class="w-full h-40 object-cover rounded-lg mb-4" />
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-gray-500">Owner</span><span class="font-medium"><?= e($v['owner']) ?></span></div>
              <div class="flex justify-between"><span class="text-gray-500">Address</span><span class="font-medium text-right"><?= e($v['address'] ?: '—') ?><?= $v['city'] ? ', ' . e($v['city']) : '' ?></span></div>
              <div class="flex justify-between"><span class="text-gray-500">Phone</span><span class="font-medium"><?= e($v['phone'] ?: '—') ?></span></div>
              <?php if (!empty($v['email'])): ?><div class="flex justify-between"><span class="text-gray-500">Login email</span><span class="font-medium text-green-700"><?= e($v['email']) ?></span></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$showVendorForm): empty_state('No vendors yet.', 'store', 'admin.php?tab=vendors&new=1', 'Add your first vendor'); endif; ?>
  </div>

<?php elseif ($tab === 'suppliers'): ?>
  <div class="bg-white rounded-xl border p-6 overflow-x-auto">
    <h3 class="font-bold text-lg mb-4">Suppliers <span class="text-gray-400 font-normal">(<?= count($supplierList) ?>)</span></h3>
    <?php if ($supplierList): ?>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Company</th><th class="py-2 pr-4">Contact</th><th class="py-2 pr-4">Email</th><th class="py-2 pr-4">Phone</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <?php foreach ($supplierList as $s): ?>
            <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($s['name']) ?></td><td class="py-3 pr-4"><?= e($s['contact']) ?></td><td class="py-3 pr-4 text-green-700"><?= e($s['email'] ?: '—') ?></td><td class="py-3 pr-4"><?= e($s['phone'] ?: '—') ?></td><td class="py-3"><span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700">Active</span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: empty_state('No suppliers yet. Suppliers appear here after they sign up on the Supplier tab.', 'package'); endif; ?>
  </div>

<?php elseif ($tab === 'delivery'): ?>
  <div class="bg-white rounded-xl border p-6 overflow-x-auto">
    <h3 class="font-bold text-lg mb-4">Delivery Partners <span class="text-gray-400 font-normal">(<?= count($partnerList) ?>)</span></h3>
    <?php if ($partnerList): ?>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Email</th><th class="py-2 pr-4">Phone</th><th class="py-2 pr-4">Vehicle</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <?php foreach ($partnerList as $d): ?>
            <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($d['name']) ?></td><td class="py-3 pr-4 text-green-700"><?= e($d['email'] ?: '—') ?></td><td class="py-3 pr-4"><?= e($d['phone'] ?: '—') ?></td><td class="py-3 pr-4"><?= e($d['vehicle']) ?></td><td class="py-3"><span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700"><?= e($d['status']) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: empty_state('No delivery partners yet. They appear here after signing up on the Delivery tab.', 'truck'); endif; ?>
  </div>

<?php elseif ($tab === 'analytics'): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Orders by Status</h3>
      <?php if ($statusCounts): ?>
        <?php render_chart('aStatus', 'bar', ['data' => ['labels' => array_keys($statusCounts), 'datasets' => [['label' => 'Orders', 'data' => array_values($statusCounts), 'backgroundColor' => '#16a34a']]], 'options' => ['plugins' => ['legend' => ['display' => false]], 'maintainAspectRatio' => false]], 300); ?>
      <?php else: empty_state('No orders to analyse yet.', 'bar-chart-3'); endif; ?>
    </div>
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Platform Participants</h3>
      <?php render_chart('aParticipants', 'pie', ['data' => ['labels' => ['Vendors', 'Suppliers', 'Delivery'], 'datasets' => [['data' => [count($vendorList), count($supplierList), count($partnerList)], 'backgroundColor' => ['#8b5cf6', '#f97316', '#3b82f6']]]], 'options' => ['maintainAspectRatio' => false]], 300); ?>
    </div>
  </div>

<?php elseif ($tab === 'setup'): ?>
  <div class="space-y-6 max-w-4xl">
    <!-- 1. Shop / business settings -->
    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center gap-2 mb-1"><i data-lucide="store" class="w-5 h-5 text-green-600"></i><h3 class="font-bold text-lg">Shop / Business Details</h3></div>
      <p class="text-sm text-gray-500 mb-4">This is the kirana this app is set up for. The shop name &amp; tagline are shown to customers across the app.</p>
      <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="do" value="shop_settings_save" />
        <div><label class="block text-sm font-medium mb-1">Shop Name</label><input name="shopName" value="<?= e($settings['shopName'] ?? '') ?>" placeholder="e.g. Sharma Kirana Store" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Tagline</label><input name="tagline" value="<?= e($settings['tagline'] ?? '') ?>" placeholder="e.g. Fresh &amp; local, delivered fast" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Address</label><input name="address" value="<?= e($settings['address'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Phone</label><input name="phone" value="<?= e($settings['phone'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Contact Email</label><input name="email" type="email" value="<?= e($settings['email'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
        <div class="md:col-span-2"><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg">Save Shop Details</button></div>
      </form>
    </div>

    <!-- 2. Quick add vendor -->
    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center gap-2 mb-1"><i data-lucide="user-plus" class="w-5 h-5 text-green-600"></i><h3 class="font-bold text-lg">Add a Vendor</h3></div>
      <p class="text-sm text-gray-500 mb-4">Create a store and its login. The vendor signs in on the Vendor tab to manage their own products.</p>
      <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="do" value="vendor_add" />
        <input type="hidden" name="redirect" value="admin.php?tab=setup" />
        <div><label class="block text-sm font-medium mb-1">Store Name</label><input name="name" required class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Owner</label><input name="owner" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">City</label><input name="city" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Phone</label><input name="phone" class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Login Email</label><input name="email" type="email" required class="w-full border rounded-lg px-3 py-2" /></div>
        <div><label class="block text-sm font-medium mb-1">Password</label>
          <div class="relative">
            <input id="setup-vpass" name="password" type="password" required class="w-full border rounded-lg px-3 py-2 pr-11" />
            <button type="button" data-toggle-password="#setup-vpass" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"><i data-lucide="eye" class="icon-eye w-5 h-5"></i><i data-lucide="eye-off" class="icon-eye-off w-5 h-5 hidden"></i></button>
          </div>
        </div>
        <div class="md:col-span-2"><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg">Add Vendor</button></div>
      </form>
    </div>

    <!-- 3. Quick add product -->
    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center gap-2 mb-1"><i data-lucide="package-plus" class="w-5 h-5 text-green-600"></i><h3 class="font-bold text-lg">Add a Product</h3></div>
      <?php if ($vendorList): ?>
        <p class="text-sm text-gray-500 mb-4">Add a product to a store's catalog. It becomes visible to customers immediately.</p>
        <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="hidden" name="do" value="admin_product_add" />
          <input type="hidden" name="redirect" value="admin.php?tab=setup" />
          <div><label class="block text-sm font-medium mb-1">Product Name</label><input name="name" required class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Store (Vendor)</label>
            <select name="vendor" class="w-full border rounded-lg px-3 py-2"><?php foreach ($vendorList as $v): ?><option value="<?= e($v['name']) ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div><label class="block text-sm font-medium mb-1">Category</label><select name="category" class="w-full border rounded-lg px-3 py-2"><?php foreach ($CATS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
          <div><label class="block text-sm font-medium mb-1">Unit / Quantity</label><input name="unit" value="1kg" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Brand Name <span class="text-xs text-gray-400">(optional)</span></label><input name="brand" placeholder="e.g. Aashirvaad" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Expiry / Best Before <span class="text-xs text-gray-400">(optional)</span></label><input name="expiry" placeholder="e.g. Dec 2026" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Price (₹)</label><input name="price" type="number" min="0" required class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Stock</label><input name="stock" type="number" min="0" required class="w-full border rounded-lg px-3 py-2" /></div>
          <label class="md:col-span-2 flex items-center gap-2 bg-teal-50 border border-teal-100 rounded-lg px-3 py-2.5 cursor-pointer"><input type="checkbox" name="hygiene_verified" value="1" class="accent-teal-600 w-4 h-4" /><span class="text-sm text-gray-700 flex items-center gap-1.5"><i data-lucide="shield-check" class="w-4 h-4 text-teal-600"></i>Hygiene / quality checked</span></label>
          <div class="md:col-span-2"><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg">Add Product</button></div>
        </form>
      <?php else: ?>
        <p class="text-sm text-gray-500 mb-4">Add a vendor first — products belong to a store.</p>
        <div class="text-sm text-gray-400">No vendors yet. Use "Add a Vendor" above.</div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'offers'): ?>
  <div class="space-y-6 max-w-4xl">
    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center gap-2 mb-1"><i data-lucide="badge-percent" class="w-5 h-5 text-green-600"></i><h3 class="font-bold text-lg">Create an Offer</h3></div>
      <p class="text-sm text-gray-500 mb-4">Publish a promotion that customers will see on the main shopping page.</p>
      <form method="post" action="actions.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="do" value="offer_add" />
        <input type="hidden" name="redirect" value="admin.php?tab=offers" />
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

<?php elseif ($tab === 'users'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <?php foreach (['customer' => 'Customers', 'vendor' => 'Vendors', 'supplier' => 'Suppliers', 'delivery' => 'Delivery', 'admin' => 'Admins'] as $rk => $rl): ?>
        <div class="bg-white rounded-xl border p-4 text-center">
          <p class="text-2xl font-bold"><?= (int) ($roleCounts[$rk] ?? 0) ?></p>
          <p class="text-xs text-gray-500 mt-1"><?= e($rl) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl border p-6 overflow-x-auto">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <h3 class="font-bold text-lg">App Users <span class="text-gray-400 font-normal">(<?= count($accountList) ?> can log in)</span></h3>
        <div class="flex gap-1 flex-wrap text-sm">
          <?php foreach (['all' => 'All', 'customer' => 'Customer', 'vendor' => 'Vendor', 'supplier' => 'Supplier', 'delivery' => 'Delivery', 'admin' => 'Admin'] as $rk => $rl): ?>
            <a href="admin.php?tab=users<?= $rk === 'all' ? '' : '&urole=' . $rk ?>" class="px-3 py-1.5 rounded-lg border <?= $uroleFilter === $rk ? 'bg-green-50 text-green-600 border-green-200' : 'hover:bg-gray-50' ?>"><?= e($rl) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($shownAccounts): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Login Email</th><th class="py-2 pr-4">Role</th><th class="py-2 pr-4">Store / Phone</th><th class="py-2">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($shownAccounts as $a): $isSelf = strtolower($a['email'] ?? '') === strtolower($user['email'] ?? ''); ?>
              <tr class="border-b last:border-0">
                <td class="py-3 pr-4 font-medium"><?= e($a['name'] ?? '—') ?><?php if ($isSelf): ?> <span class="text-xs text-gray-400">(you)</span><?php endif; ?></td>
                <td class="py-3 pr-4 text-green-700"><?= e($a['email'] ?? '') ?></td>
                <td class="py-3 pr-4"><?= role_badge($a['role'] ?? 'other') ?></td>
                <td class="py-3 pr-4 text-gray-600"><?= e($a['shopName'] ?? ($a['phone'] ?? '—')) ?></td>
                <td class="py-3">
                  <?php if ($isSelf): ?>
                    <span class="text-xs text-gray-400">Current session</span>
                  <?php else: ?>
                    <form method="post" action="actions.php" data-confirm="Revoke login access for <?= e($a['email'] ?? '') ?>? They will no longer be able to sign in.">
                      <input type="hidden" name="do" value="account_revoke" />
                      <input type="hidden" name="email" value="<?= e($a['email'] ?? '') ?>" />
                      <button class="text-sm border rounded-lg px-3 py-1.5 hover:bg-red-50 text-red-600 border-red-200">Revoke login</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state($accountList ? 'No ' . $uroleFilter . ' accounts.' : 'No one has signed up yet.', 'key-round'); endif; ?>
    </div>
  </div>

<?php else: /* customers */ ?>
  <div class="bg-white rounded-xl border p-6 overflow-x-auto">
    <h3 class="font-bold text-lg mb-4">Customer Orders</h3>
    <?php if ($orderList): ?>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Vendor</th><th class="py-2 pr-4">Total</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <?php foreach ($orderList as $o): ?>
            <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($o['id']) ?></td><td class="py-3 pr-4"><?= e($o['customerName']) ?></td><td class="py-3 pr-4"><?= e($o['vendor'] ?: '—') ?></td><td class="py-3 pr-4">₹<?= e($o['total']) ?></td><td class="py-3"><span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700"><?= e($o['status']) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: empty_state('No customer orders yet.', 'users'); endif; ?>
  </div>
<?php endif; ?>

<?php
render_dashboard_end();
require __DIR__ . '/partials/foot.php';
