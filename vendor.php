<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dashboard.php';

$user = require_role('vendor');
$tab  = $_GET['tab'] ?? 'dashboard';

$shopName = $user['shopName'] ?? '';
$vendorProducts = array_values(array_filter(products(), fn($p) => ($p['vendor'] ?? '') === $shopName));
$vendorOrders   = array_values(array_filter(orders(),   fn($o) => ($o['vendor'] ?? '') === $shopName));

// Real aggregates
$revenue     = array_sum(array_column($vendorOrders, 'total'));
$pendingCnt  = count(array_filter($vendorOrders, fn($o) => $o['status'] === 'Pending'));
$lowStock    = array_values(array_filter($vendorProducts, fn($p) => $p['stock'] < 20));

// Products tab filters
$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$shownProducts = array_values(array_filter($vendorProducts, function ($p) use ($q, $filter) {
  $matchQ = $q === '' || str_contains(mb_strtolower($p['name']), mb_strtolower($q)) || str_contains(mb_strtolower($p['category']), mb_strtolower($q));
  $matchF = $filter === 'all' || ($filter === 'in' && $p['stock'] >= 20) || ($filter === 'low' && $p['stock'] < 20);
  return $matchQ && $matchF;
}));

// Orders tab filter
$statusFilter = $_GET['status'] ?? 'All';
$shownOrders  = $statusFilter === 'All' ? $vendorOrders : array_values(array_filter($vendorOrders, fn($o) => $o['status'] === $statusFilter));

$editProduct = isset($_GET['edit']) ? find_by_id($vendorProducts, (int) $_GET['edit']) : null;
$showForm    = isset($_GET['new']) || $editProduct;
$catalogProducts = catalog_products();
$formProduct = $editProduct ?: (isset($_GET['source']) ? find_by_id($catalogProducts, (int) $_GET['source']) : null);
$CATS = category_names();

// Real notifications
$notifList = [];
if ($pendingCnt)       $notifList[] = [$pendingCnt . ' pending order' . ($pendingCnt > 1 ? 's' : ''), 'Awaiting your action'];
if (count($lowStock))  $notifList[] = [count($lowStock) . ' low-stock item' . (count($lowStock) > 1 ? 's' : ''), 'Restock soon'];

function status_badge(string $status): string {
  $map = ['Delivered' => 'bg-green-100 text-green-700', 'In Transit' => 'bg-blue-100 text-blue-700', 'Processing' => 'bg-amber-100 text-amber-700', 'Pending' => 'bg-gray-100 text-gray-700'];
  return '<span class="text-xs font-medium px-2 py-1 rounded-full ' . ($map[$status] ?? 'bg-gray-100 text-gray-700') . '">' . e($status) . '</span>';
}

// Orders-by-status for the analytics pie
$statusCounts = [];
foreach ($vendorOrders as $o) { $statusCounts[$o['status']] = ($statusCounts[$o['status']] ?? 0) + 1; }

$shell = [
  'panelTitle' => $shopName ?: 'Vendor', 'panelSubtitle' => $user['name'], 'baseUrl' => 'vendor.php',
  'activeTab' => $tab, 'headerSubtitle' => 'Manage your store operations', 'roleLabel' => 'Vendor',
  'userName' => $user['name'], 'notifList' => $notifList,
  'nav' => [
    ['id' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['id' => 'products',  'icon' => 'package',          'label' => 'Products'],
    ['id' => 'orders',    'icon' => 'shopping-cart',    'label' => 'Orders', 'badge' => $pendingCnt ? (string) $pendingCnt : null],
    ['id' => 'analytics', 'icon' => 'trending-up',      'label' => 'Analytics'],
    ['id' => 'settings',  'icon' => 'settings',         'label' => 'Settings'],
  ],
];

$page_title = 'Vendor Dashboard · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
render_dashboard_start($shell);
?>

<?php if ($tab === 'dashboard'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <?php
      stat_card('₹' . number_format($revenue), 'Total Sales', 'indian-rupee', 'bg-green-100', 'text-green-600');
      stat_card((string) count($vendorOrders), 'Total Orders', 'shopping-cart', 'bg-blue-100', 'text-blue-600');
      stat_card((string) count($vendorProducts), 'Products', 'package', 'bg-purple-100', 'text-purple-600');
      stat_card((string) count($lowStock), 'Low Stock Items', 'triangle-alert', 'bg-orange-100', 'text-orange-600');
      ?>
    </div>

    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Stock by Product</h3>
      <?php if ($vendorProducts): ?>
        <?php render_chart('vendorStock', 'bar', ['data' => ['labels' => array_column($vendorProducts, 'name'), 'datasets' => [['label' => 'Stock', 'data' => array_map('intval', array_column($vendorProducts, 'stock')), 'backgroundColor' => '#16a34a']]], 'options' => ['plugins' => ['legend' => ['display' => false]], 'maintainAspectRatio' => false]], 260); ?>
      <?php else: empty_state('No products yet — add your first product to see stock levels.', 'package', 'vendor.php?tab=products&new=1', 'Add Product'); endif; ?>
    </div>

    <div class="bg-white rounded-xl border p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-lg">Recent Orders</h3>
        <a href="vendor.php?tab=orders" class="text-sm border rounded-lg px-3 py-1.5 hover:bg-gray-50">View All</a>
      </div>
      <?php if ($vendorOrders): ?>
        <div class="overflow-x-auto"><table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Items</th><th class="py-2 pr-4">Amount</th><th class="py-2">Status</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($vendorOrders, 0, 5) as $o): ?>
              <tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium"><?= e($o['id']) ?></td><td class="py-3 pr-4"><?= e($o['customerName']) ?></td><td class="py-3 pr-4"><?= e($o['items']) ?> items</td><td class="py-3 pr-4">₹<?= e($o['total']) ?></td><td class="py-3"><?= status_badge($o['status']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: empty_state('No orders yet. Orders placed by customers for your store will appear here.', 'shopping-cart'); endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'products'): ?>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div class="flex items-center gap-3 flex-wrap">
        <form action="vendor.php" method="get" class="relative w-72">
          <input type="hidden" name="tab" value="products" />
          <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>" /><?php endif; ?>
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
          <input name="q" value="<?= e($q) ?>" placeholder="Search products..." class="w-full border rounded-lg pl-10 pr-3 py-2 outline-none" />
        </form>
        <div class="flex items-center gap-1 text-sm">
          <span class="text-gray-500 mr-1"><i data-lucide="filter" class="w-4 h-4 inline"></i></span>
          <?php foreach (['all' => 'All', 'in' => 'In stock', 'low' => 'Low stock'] as $fk => $fl): ?>
            <a href="vendor.php?tab=products<?= $q ? '&q=' . urlencode($q) : '' ?>&filter=<?= $fk ?>" class="px-3 py-1.5 rounded-lg border <?= $filter === $fk ? 'bg-green-50 text-green-600 border-green-200' : 'hover:bg-gray-50' ?>"><?= e($fl) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="vendor.php?tab=products&new=1" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i>Add Product</a>
    </div>

    <?php if ($showForm): ?>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4"><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h3>
        <form method="post" action="actions.php" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="hidden" name="do" value="<?= $editProduct ? 'product_update' : 'product_add' ?>" />
          <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= e($editProduct['id']) ?>" /><?php endif; ?>
          <?php if (!$editProduct): ?>
            <input type="hidden" name="source_id" id="source-product-id" value="<?= e($formProduct['id'] ?? '') ?>" />
            <div class="md:col-span-2">
              <label for="catalog-search" class="block text-sm font-medium mb-1">Search the product catalogue</label>
              <input id="catalog-search" type="search" placeholder="Search by product name or category..." class="w-full border rounded-lg px-3 py-2" />
              <div id="catalog-products" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 max-h-80 overflow-y-auto">
                <?php foreach ($catalogProducts as $catalogProduct): ?>
                  <button type="button" data-catalog-product data-name="<?= e(mb_strtolower($catalogProduct['name'])) ?>" data-category="<?= e(mb_strtolower($catalogProduct['category'])) ?>" data-id="<?= e($catalogProduct['id']) ?>" data-product-name="<?= e($catalogProduct['name']) ?>" data-product-category="<?= e($catalogProduct['category']) ?>" data-product-price="<?= e($catalogProduct['price']) ?>" data-product-unit="<?= e($catalogProduct['unit']) ?>" class="text-left border rounded-lg overflow-hidden hover:border-green-500 hover:bg-green-50">
                    <img src="<?= e($catalogProduct['image']) ?>" alt="<?= e($catalogProduct['name']) ?>" class="w-full h-20 object-cover" />
                    <span class="block px-2 py-1.5 text-sm font-medium truncate"><?= e($catalogProduct['name']) ?></span>
                    <span class="block px-2 pb-2 text-xs text-gray-500 truncate"><?= e($catalogProduct['category']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
              <?php if (!$catalogProducts): ?><p class="text-sm text-gray-400 mt-2">No catalogue products are available yet.</p><?php endif; ?>
              <p class="text-xs text-gray-400 mt-2">Choose an image to fill the form, then set your store price and stock.</p>
            </div>
          <?php endif; ?>
          <div><label class="block text-sm font-medium mb-1">Name</label><input id="product-name" name="name" required value="<?= e($formProduct['name'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Category</label><select id="product-category" name="category" class="w-full border rounded-lg px-3 py-2"><?php foreach ($CATS as $c): ?><option <?= ($formProduct['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
          <div><label class="block text-sm font-medium mb-1">Price (₹)</label><input id="product-price" name="price" type="number" min="0" required value="<?= e($formProduct['price'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Stock</label><input name="stock" type="number" min="0" required value="<?= e($formProduct['stock'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div><label class="block text-sm font-medium mb-1">Unit</label><input id="product-unit" name="unit" value="<?= e($formProduct['unit'] ?? '1kg') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
          <div>
            <label class="block text-sm font-medium mb-1">Product image</label>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full border rounded-lg px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 file:px-3 file:py-1.5 file:cursor-pointer" />
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP or GIF · up to 3 MB<?= $editProduct ? ' · leave empty to keep the current image' : '' ?></p>
          </div>
          <?php if ($editProduct && !empty($editProduct['image'])): ?>
            <div class="flex items-center gap-3">
              <img src="<?= e($editProduct['image']) ?>" alt="" class="w-16 h-16 rounded-lg object-cover border" />
              <span class="text-xs text-gray-500">Current image</span>
            </div>
          <?php endif; ?>
          <div class="md:col-span-2 flex gap-3"><button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg"><?= $editProduct ? 'Save Changes' : 'Add Product' ?></button><a href="vendor.php?tab=products" class="px-5 py-2 border rounded-lg hover:bg-gray-50">Cancel</a></div>
        </form>
        <?php if (!$editProduct): ?>
          <script>
            const catalogSearch = document.getElementById('catalog-search');
            const catalogCards = document.querySelectorAll('[data-catalog-product]');
            catalogSearch?.addEventListener('input', () => {
              const query = catalogSearch.value.toLowerCase().trim();
              catalogCards.forEach((card) => {
                const matches = `${card.dataset.name} ${card.dataset.category}`.includes(query);
                card.classList.toggle('hidden', !matches);
              });
            });
            catalogCards.forEach((card) => card.addEventListener('click', () => {
              document.getElementById('source-product-id').value = card.dataset.id;
              document.getElementById('product-name').value = card.dataset.productName;
              document.getElementById('product-price').value = card.dataset.productPrice;
              document.getElementById('product-unit').value = card.dataset.productUnit;
              const category = document.getElementById('product-category');
              category.value = card.dataset.productCategory;
              card.classList.add('border-green-600', 'bg-green-50');
            }));
          </script>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($shownProducts): ?>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($shownProducts as $p): ?>
          <div class="bg-white rounded-xl border overflow-hidden">
            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="w-full h-48 object-cover" />
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <div><h3 class="font-semibold"><?= e($p['name']) ?></h3><p class="text-sm text-gray-600"><?= e($p['category']) ?></p></div>
                <span class="text-xs font-medium px-2 py-1 rounded-full <?= $p['stock'] >= 20 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= e($p['stock']) ?> units</span>
              </div>
              <div class="flex items-center justify-between mt-4">
                <span class="text-xl font-bold text-green-600">₹<?= e($p['price']) ?></span>
                <div class="flex gap-2">
                  <a href="vendor.php?tab=products&edit=<?= e($p['id']) ?>" class="text-sm border rounded-lg px-3 py-1.5 hover:bg-gray-50">Edit</a>
                  <form method="post" action="actions.php" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;?"><input type="hidden" name="do" value="product_delete" /><input type="hidden" name="id" value="<?= e($p['id']) ?>" /><button class="text-sm border rounded-lg px-3 py-1.5 hover:bg-red-50 text-red-600 border-red-200">Delete</button></form>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$showForm): empty_state($q || $filter !== 'all' ? 'No products match your search/filter.' : 'You have no products yet.', 'package', 'vendor.php?tab=products&new=1', 'Add your first product'); endif; ?>
  </div>

<?php elseif ($tab === 'orders'): ?>
  <div class="space-y-6">
    <div class="flex gap-2 flex-wrap">
      <?php foreach (['All', 'Pending', 'Processing', 'In Transit', 'Delivered'] as $s): ?>
        <a href="vendor.php?tab=orders&status=<?= urlencode($s) ?>" class="text-sm border rounded-lg px-3 py-1.5 <?= $statusFilter === $s ? 'bg-green-50 text-green-600 border-green-200' : 'hover:bg-gray-50' ?>"><?= e($s) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="bg-white rounded-xl border p-6 overflow-x-auto">
      <?php if ($shownOrders): ?>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Order ID</th><th class="py-2 pr-4">Customer</th><th class="py-2 pr-4">Address</th><th class="py-2 pr-4">Items</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Payment</th><th class="py-2 pr-4">Status</th><th class="py-2">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($shownOrders as $o): ?>
              <tr class="border-b last:border-0">
                <td class="py-3 pr-4 font-medium"><?= e($o['id']) ?></td>
                <td class="py-3 pr-4"><?= e($o['customerName']) ?></td>
                <td class="py-3 pr-4 max-w-xs truncate"><?= e(order_delivery_address($o) ?: '—') ?></td>
                <td class="py-3 pr-4"><?= e($o['items']) ?> items</td>
                <td class="py-3 pr-4 font-semibold">₹<?= e($o['total']) ?></td>
                <td class="py-3 pr-4"><span class="text-xs border rounded-full px-2 py-1"><?= e($o['paymentMethod']) ?></span></td>
                <td class="py-3 pr-4"><?= status_badge($o['status']) ?></td>
                <td class="py-3">
                  <form method="post" action="actions.php" class="flex items-center gap-2">
                    <input type="hidden" name="do" value="order_status" />
                    <input type="hidden" name="id" value="<?= e($o['id']) ?>" />
                    <input type="hidden" name="redirect" value="vendor.php?tab=orders<?= $statusFilter !== 'All' ? '&status=' . urlencode($statusFilter) : '' ?>" />
                    <select name="status" class="border rounded-lg px-2 py-1.5 text-xs">
                      <?php foreach (['Pending', 'Processing', 'In Transit', 'Delivered'] as $s): ?><option <?= $o['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                    </select>
                    <button class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: empty_state($statusFilter === 'All' ? 'No orders yet.' : 'No ' . $statusFilter . ' orders.', 'shopping-cart'); endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'analytics'): ?>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Orders by Status</h3>
        <?php if ($statusCounts): ?>
          <?php render_chart('vOrdersPie', 'pie', ['data' => ['labels' => array_keys($statusCounts), 'datasets' => [['data' => array_values($statusCounts), 'backgroundColor' => ['#16a34a', '#3b82f6', '#f59e0b', '#9ca3af', '#ef4444']]]], 'options' => ['maintainAspectRatio' => false]], 280); ?>
        <?php else: empty_state('No orders to analyse yet.', 'pie-chart'); endif; ?>
      </div>
      <div class="bg-white rounded-xl border p-6">
        <h3 class="font-bold text-lg mb-4">Stock by Product</h3>
        <?php if ($vendorProducts): ?>
          <?php render_chart('vStockBar', 'bar', ['data' => ['labels' => array_column($vendorProducts, 'name'), 'datasets' => [['label' => 'Stock', 'data' => array_map('intval', array_column($vendorProducts, 'stock')), 'backgroundColor' => '#16a34a']]], 'options' => ['plugins' => ['legend' => ['display' => false]], 'maintainAspectRatio' => false]], 280); ?>
        <?php else: empty_state('No products to analyse yet.', 'bar-chart-3'); endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-bold text-lg mb-4">Key Metrics</h3>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <?php foreach ([['₹' . number_format($revenue), 'Total Revenue', 'text-green-600'], [(string) count($vendorOrders), 'Total Orders', 'text-blue-600'], [(string) count($vendorProducts), 'Products', 'text-purple-600'], [(string) count($lowStock), 'Low Stock', 'text-orange-600']] as $m): ?>
          <div class="text-center p-4 bg-gray-50 rounded-lg"><p class="text-3xl font-bold <?= $m[2] ?>"><?= e($m[0]) ?></p><p class="text-sm text-gray-600 mt-2"><?= e($m[1]) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php else: ?>
  <div class="bg-white rounded-xl border p-8 max-w-lg">
    <h3 class="font-bold text-lg mb-4">Store Settings</h3>
    <form method="post" action="actions.php" class="space-y-4">
      <input type="hidden" name="do" value="settings_save" />
      <div><label class="block text-sm font-medium mb-1">Shop Name</label><input name="shopName" value="<?= e($user['shopName'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
      <div><label class="block text-sm font-medium mb-1">Owner</label><input name="name" value="<?= e($user['name']) ?>" class="w-full border rounded-lg px-3 py-2" /></div>
      <div><label class="block text-sm font-medium mb-1">Phone</label><input name="phone" value="<?= e($user['phone'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" /></div>
      <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">Save Changes</button>
    </form>
  </div>
<?php endif; ?>

<?php
render_dashboard_end();
require __DIR__ . '/partials/foot.php';
