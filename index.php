<?php
require_once __DIR__ . '/includes/init.php';

$search = trim($_GET['q'] ?? '');
$cat    = trim($_GET['cat'] ?? '');
$allProducts = products();
$allVendors  = vendors_list();
$activeOffers = active_offers();

$filteredProducts = array_values(array_filter($allProducts, function ($p) use ($search, $cat) {
  $matchSearch = $search === ''
    || str_contains(mb_strtolower($p['name']), mb_strtolower($search))
    || str_contains(mb_strtolower($p['category']), mb_strtolower($search));
  $matchCat = $cat === '' || strcasecmp((string) ($p['category'] ?? ''), $cat) === 0;
  return $matchSearch && $matchCat;
}));

$me        = current_user();
$cartCount = cart_total_items();
$brand     = brand_name();
$cats      = $CATEGORIES ?? [];

/** Small 5-star rating row. */
function stars_html($rating): string {
  $r = (float) $rating;
  $html = '<span class="inline-flex items-center gap-0.5">';
  for ($i = 1; $i <= 5; $i++) {
    $cls = $i <= round($r) ? 'text-amber-400 fill-amber-400' : 'text-gray-300';
    $html .= '<i data-lucide="star" class="w-3.5 h-3.5 ' . $cls . '"></i>';
  }
  return $html . '</span>';
}

$page_title = $brand;
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gray-50">
  <!-- Header -->
  <header class="bg-white/90 backdrop-blur border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
      <a href="index.php" class="flex items-center gap-2.5 shrink-0">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-green-600 text-white shadow-sm"><i data-lucide="shopping-basket" class="w-6 h-6"></i></span>
        <span class="text-xl font-extrabold tracking-tight text-gray-900"><?= e($brand) ?></span>
      </a>

      <nav class="flex items-center gap-1 text-sm">
        <a href="index.php#stores" class="hidden md:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Stores</a>
        <a href="index.php#products" class="hidden md:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Products</a>
        <?php if ($me): ?>
          <?php if (($me['role'] ?? '') === 'customer'): ?>
            <a href="orders.php" class="hidden sm:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">My Orders</a>
            <a href="profile.php" class="hidden sm:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Profile</a>
          <?php else: ?>
            <a href="<?= e(dashboard_for($me['role'])) ?>" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Dashboard</a>
          <?php endif; ?>
          <a href="logout.php" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Logout</a>
        <?php else: ?>
          <a href="login.php" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Login</a>
          <a href="login.php?mode=signup" class="hidden sm:inline-flex px-3.5 py-2 rounded-lg border border-green-600 text-green-700 hover:bg-green-50 font-medium">Sign Up</a>
        <?php endif; ?>
        <a href="cart.php" class="relative inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-3.5 py-2 rounded-lg font-medium shadow-sm ml-1">
          <i data-lucide="shopping-cart" class="w-5 h-5"></i><span class="hidden sm:inline">Cart</span>
          <?php if ($cartCount > 0): ?><span class="absolute -top-2 -right-2 bg-amber-500 text-white text-xs font-bold rounded-full min-w-[1.25rem] h-5 px-1 flex items-center justify-center ring-2 ring-white"><?= $cartCount ?></span><?php endif; ?>
        </a>
      </nav>
    </div>
  </header>

  <!-- Hero -->
  <section class="relative overflow-hidden bg-gradient-to-br from-green-700 via-green-600 to-emerald-500 text-white">
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-white/10 rounded-full blur-2xl"></div>
    <div class="absolute -bottom-32 -left-16 w-96 h-96 bg-emerald-300/20 rounded-full blur-3xl"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-16 md:py-20">
      <div class="max-w-2xl">
        <span class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-3 py-1 rounded-full text-sm font-medium mb-5"><i data-lucide="sparkles" class="w-4 h-4"></i>Fresh from your neighbourhood</span>
        <h2 class="text-4xl md:text-5xl font-extrabold leading-tight mb-4">Your Local Kirana,<br class="hidden sm:block" /> Now Online</h2>
        <p class="text-lg text-green-50 mb-8 max-w-xl"><?= e(brand_tagline()) ?></p>
        <form action="index.php#products" method="get" class="flex items-center gap-2 bg-white rounded-xl p-2 shadow-lg max-w-xl">
          <i data-lucide="search" class="w-5 h-5 text-gray-400 ml-2 shrink-0"></i>
          <input name="q" value="<?= e($search) ?>" placeholder="Search products or categories..." class="flex-1 border-0 outline-none text-gray-900 px-1 py-2 bg-transparent" />
          <button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg font-medium shrink-0">Search</button>
        </form>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-6 text-sm text-green-50">
          <span class="inline-flex items-center gap-1.5"><i data-lucide="package" class="w-4 h-4"></i><?= count($allProducts) ?> products</span>
          <span class="inline-flex items-center gap-1.5"><i data-lucide="store" class="w-4 h-4"></i><?= count($allVendors) ?> local stores</span>
          <span class="inline-flex items-center gap-1.5"><i data-lucide="truck" class="w-4 h-4"></i>Free delivery</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section class="py-10 bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ([['package', 'Local Selection', 'From neighbourhood stores'], ['truck', 'Quick Delivery', 'Delivered from nearby'], ['shield-check', 'Safe & Secure', 'Protected checkout'], ['leaf', 'Fresh Quality', 'Sourced locally']] as $f): ?>
          <div class="flex items-start gap-3 p-4 rounded-xl hover:bg-gray-50 transition-colors">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-green-50 text-green-600 shrink-0"><i data-lucide="<?= $f[0] ?>" class="w-6 h-6"></i></span>
            <div><h3 class="font-semibold text-gray-900"><?= e($f[1]) ?></h3><p class="text-gray-500 text-sm mt-0.5"><?= e($f[2]) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Offers -->
  <?php if ($activeOffers): ?>
    <section class="py-10 bg-amber-50 border-y border-amber-100">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center gap-2 mb-5"><i data-lucide="badge-percent" class="w-6 h-6 text-amber-600"></i><h2 class="text-2xl font-bold text-gray-900">Offers for You</h2></div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($activeOffers as $offer): ?>
            <div class="bg-white border border-amber-200 rounded-xl p-5 flex items-start justify-between gap-4 shadow-sm">
              <div><p class="text-xs font-semibold uppercase tracking-wide text-amber-600 mb-1"><?= e($offer['discount']) ?></p><h3 class="font-bold text-gray-900"><?= e($offer['title']) ?></h3><?php if ($offer['description'] !== ''): ?><p class="text-sm text-gray-500 mt-1"><?= e($offer['description']) ?></p><?php endif; ?><?php if ($offer['code'] !== ''): ?><p class="inline-flex mt-3 bg-gray-100 rounded px-2 py-1 text-xs font-mono font-semibold text-gray-700">Code: <?= e($offer['code']) ?></p><?php endif; ?></div>
              <a href="#products" class="shrink-0 text-sm font-medium text-green-700 hover:text-green-800">Shop now</a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- Nearby Stores -->
  <section id="stores" class="py-14 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex items-end justify-between mb-8">
        <div>
          <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Stores Near You</h2>
          <p class="text-gray-500 mt-1">Shop from trusted local kirana stores</p>
        </div>
      </div>
      <?php if ($allVendors): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($allVendors as $vendor): ?>
            <div class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
              <div class="relative h-44 overflow-hidden">
                <img src="<?= e($vendor['image']) ?>" alt="<?= e($vendor['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                <?php if ($vendor['verified']): ?>
                  <span class="absolute top-3 left-3 inline-flex items-center gap-1 bg-white/95 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"><i data-lucide="badge-check" class="w-3.5 h-3.5"></i>Verified</span>
                <?php endif; ?>
                <?php if ((float) ($vendor['rating'] ?? 0) > 0): ?>
                  <span class="absolute top-3 right-3 inline-flex items-center gap-1 bg-white/95 text-gray-800 text-xs font-semibold px-2 py-1 rounded-full shadow-sm"><i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-400"></i><?= e(number_format((float) $vendor['rating'], 1)) ?></span>
                <?php endif; ?>
              </div>
              <div class="p-5">
                <h3 class="font-bold text-lg text-gray-900 truncate"><?= e($vendor['name']) ?></h3>
                <p class="text-gray-500 text-sm mt-1 flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4 shrink-0"></i><span class="truncate"><?= e($vendor['address'] ?: $vendor['city'] ?: 'Local store') ?></span></p>
                <?php if (!empty($vendor['deliveryTime'])): ?><p class="text-gray-500 text-sm mt-1 flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4 shrink-0"></i><?= e($vendor['deliveryTime']) ?></p><?php endif; ?>
                <a href="index.php#products" class="mt-4 block text-center w-full border border-green-600 text-green-700 hover:bg-green-600 hover:text-white font-medium py-2 rounded-lg transition-colors">View Products</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
          <i data-lucide="store" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
          <p class="text-gray-500">No stores yet. Vendors who sign up will appear here.</p>
          <a href="login.php?role=vendor&mode=signup" class="inline-block mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">Register your store</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Products -->
  <section id="products" class="py-14 bg-white">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Products</h2>
          <p class="text-gray-500 mt-1"><?= count($filteredProducts) ?> item<?= count($filteredProducts) === 1 ? '' : 's' ?> available<?= $cat !== '' ? ' in ' . e($cat) : '' ?></p>
        </div>
        <form action="index.php#products" method="get" class="flex items-center gap-2 bg-gray-100 rounded-lg px-3 py-2 w-full md:w-80">
          <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>" /><?php endif; ?>
          <i data-lucide="search" class="w-5 h-5 text-gray-400 shrink-0"></i>
          <input name="q" value="<?= e($search) ?>" placeholder="Search products..." class="flex-1 border-0 bg-transparent outline-none text-sm" />
        </form>
      </div>

      <!-- Category chips -->
      <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-6 -mx-1 px-1">
        <a href="index.php?<?= $search !== '' ? 'q=' . urlencode($search) . '&' : '' ?>#products" class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-colors <?= $cat === '' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:border-green-300' ?>">All</a>
        <?php foreach ($cats as $c): ?>
          <a href="index.php?cat=<?= urlencode($c['name']) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>#products" class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-colors <?= strcasecmp($cat, $c['name']) === 0 ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:border-green-300' ?>">
            <span><?= $c['icon'] ?></span><?= e($c['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($filteredProducts): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
          <?php foreach ($filteredProducts as $product): $stock = (int) $product['stock']; ?>
            <div class="group bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 flex flex-col">
              <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                <span class="absolute top-3 left-3 bg-white/95 text-gray-700 text-xs font-medium px-2 py-0.5 rounded-full shadow-sm"><?= e($product['category']) ?></span>
                <?php if ($stock <= 0): ?>
                  <div class="absolute inset-0 bg-white/60 flex items-center justify-center"><span class="bg-gray-900 text-white text-xs font-semibold px-3 py-1 rounded-full">Out of stock</span></div>
                <?php endif; ?>
              </div>
              <div class="p-4 flex flex-col flex-1">
                <h3 class="font-semibold text-gray-900 leading-snug line-clamp-1" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></h3>
                <p class="text-gray-500 text-xs mt-1 flex items-center gap-1"><i data-lucide="store" class="w-3.5 h-3.5"></i><span class="truncate"><?= e($product['vendor'] ?: 'Local store') ?></span></p>
                <div class="mt-2 mb-3">
                  <?php if ((float) ($product['rating'] ?? 0) > 0): ?>
                    <?= stars_html($product['rating']) ?>
                  <?php else: ?>
                    <span class="text-xs text-gray-400">New arrival</span>
                  <?php endif; ?>
                </div>
                <div class="flex items-end justify-between mt-auto mb-3">
                  <div><span class="text-xl font-bold text-gray-900">₹<?= e($product['price']) ?></span><span class="text-xs text-gray-400 ml-1"><?= e($product['unit']) ?></span></div>
                  <?php if ($stock > 0 && $stock < 20): ?>
                    <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Only <?= $stock ?> left</span>
                  <?php elseif ($stock >= 20): ?>
                    <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">In stock</span>
                  <?php endif; ?>
                </div>
                <?php if ($stock > 0): ?>
                  <form action="cart-action.php" method="post" class="mt-auto">
                    <input type="hidden" name="action" value="add" />
                    <input type="hidden" name="id" value="<?= e($product['id']) ?>" />
                    <button class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg font-medium transition-colors"><i data-lucide="plus" class="w-4 h-4"></i>Add to Cart</button>
                  </form>
                <?php else: ?>
                  <button disabled class="w-full bg-gray-100 text-gray-400 py-2.5 rounded-lg font-medium cursor-not-allowed">Unavailable</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="bg-gray-50 rounded-2xl border border-dashed border-gray-300 p-12 text-center">
          <i data-lucide="package-search" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
          <p class="text-gray-500"><?= $search || $cat ? 'No products match your search.' : 'No products listed yet. Vendors add products from their dashboard.' ?></p>
          <?php if ($search || $cat): ?><a href="index.php#products" class="inline-block mt-4 text-sm text-green-600 font-medium hover:underline">Clear filters</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
        <div class="col-span-2 md:col-span-1">
          <div class="flex items-center gap-2.5 mb-4">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-green-600 text-white"><i data-lucide="shopping-basket" class="w-5 h-5"></i></span>
            <span class="font-bold text-lg"><?= e($brand) ?></span>
          </div>
          <p class="text-gray-400 text-sm">Connecting local stores with customers for a better shopping experience.</p>
        </div>
        <div><h4 class="font-semibold mb-4">Quick Links</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="index.php#stores" class="hover:text-white">Stores</a></li><li><a href="index.php#products" class="hover:text-white">Products</a></li><li><a href="cart.php" class="hover:text-white">Cart</a></li></ul></div>
        <div><h4 class="font-semibold mb-4">For Business</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="login.php?role=vendor&mode=signup" class="hover:text-white">Register as Vendor</a></li><li><a href="login.php?role=supplier&mode=signup" class="hover:text-white">Become a Supplier</a></li><li><a href="login.php?role=delivery&mode=signup" class="hover:text-white">Delivery Partner</a></li></ul></div>
        <div><h4 class="font-semibold mb-4">Support</h4><ul class="space-y-2 text-sm text-gray-400"><li>Help Center</li><li>Privacy Policy</li><li>Terms &amp; Conditions</li></ul></div>
      </div>
      <div class="border-t border-gray-800 mt-10 pt-8 text-center text-gray-400 text-sm">© 2026 <?= e($brand) ?>. All rights reserved.</div>
    </div>
  </footer>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
