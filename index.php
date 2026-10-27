<?php
require_once __DIR__ . '/includes/init.php';

$search = trim($_GET['q'] ?? '');
$cat    = trim($_GET['cat'] ?? '');
$allProducts = products();
$allVendors  = vendors_list();
$activeOffers = active_offers();

// The store the customer is shopping at. Once chosen (?store=ID) it is remembered,
// and every product list / search / category stays inside that store until they
// pick another one (or "All stores", ?store=all).
if (isset($_GET['store'])) {
  if ($_GET['store'] === 'all') unset($_SESSION['shop_store']);
  elseif (find_by_id($allVendors, (int) $_GET['store'])) $_SESSION['shop_store'] = (int) $_GET['store'];
}
$selectedStore = isset($_SESSION['shop_store']) ? find_by_id($allVendors, $_SESSION['shop_store']) : null;
if (!$selectedStore) unset($_SESSION['shop_store']);

$filteredProducts = array_values(array_filter($allProducts, function ($p) use ($search, $cat, $selectedStore) {
  if ($selectedStore && ($p['vendor'] ?? '') !== $selectedStore['name']) return false;
  $matchSearch = $search === ''
    || str_contains(mb_strtolower($p['name']), mb_strtolower($search))
    || str_contains(mb_strtolower($p['category']), mb_strtolower($search));
  $matchCat = $cat === '' || strcasecmp((string) ($p['category'] ?? ''), $cat) === 0;
  return $matchSearch && $matchCat;
}));

// Verified delivery partners who have set a location, for "Delivery partners near
// you". Positions are rounded to ~1 km so a partner's home is never exposed.
$nearbyPartners = [];
foreach (assignable_delivery_partners() as $dp) {
  if ($dp['lat'] === null || $dp['lng'] === null) continue;
  $nearbyPartners[] = [
    'name' => $dp['name'], 'vehicle' => $dp['vehicle'] ?: 'Bike', 'photo' => $dp['photo'] ?: '',
    'rating' => (float) ($dp['rating'] ?? 0), 'lat' => round((float) $dp['lat'], 2), 'lng' => round((float) $dp['lng'], 2),
  ];
}

$me        = current_user();
$isCustomer = $me && ($me['role'] ?? '') === 'customer';
$cartCount = cart_total_items();
$brand     = brand_name();
$cats      = $CATEGORIES ?? [];

// Regular / Family Stores this customer has marked.
$favIds    = $isCustomer ? favorite_store_ids($me['email'] ?? '') : [];
$favStores = $isCustomer ? favorite_stores_list($me['email'] ?? '') : [];

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

/** One store card, reused for the Regular Stores row and the Nearby Stores grid. */
function store_card(array $vendor, bool $isCustomer, array $favIds, ?array $selected = null): void {
  $isFav = in_array((int) $vendor['id'], $favIds, true);
  $isSel = $selected && (int) $selected['id'] === (int) $vendor['id'];
  $geoAddr = trim(($vendor['address'] ?? '') . (trim($vendor['city'] ?? '') !== '' ? ', ' . $vendor['city'] : ''));
  ?>
  <div data-store-card data-lat="<?= e($vendor['lat'] ?? '') ?>" data-lng="<?= e($vendor['lng'] ?? '') ?>" data-geo-address="<?= e($geoAddr) ?>"
       class="group bg-white rounded-2xl border overflow-hidden hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 <?= $isSel ? 'border-green-500 ring-2 ring-green-300' : ($isFav ? 'border-green-300 ring-1 ring-green-200' : 'border-gray-200') ?>">
    <div class="relative h-44 overflow-hidden">
      <img src="<?= e($vendor['image']) ?>" alt="<?= e($vendor['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
      <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5">
        <?php if (!empty($vendor['verified'])): ?>
          <span class="inline-flex items-center gap-1 bg-white/95 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"><i data-lucide="badge-check" class="w-3.5 h-3.5"></i>Verified</span>
        <?php endif; ?>
        <?php if (!empty($vendor['hygiene_verified'])): ?>
          <span class="inline-flex items-center gap-1 bg-teal-600 text-white text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i>Hygiene Certified</span>
        <?php endif; ?>
        <?php if ($isFav): ?>
          <span class="inline-flex items-center gap-1 bg-amber-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"><i data-lucide="heart" class="w-3.5 h-3.5 fill-white"></i>Regular Store</span>
        <?php endif; ?>
      </div>
      <span data-distance class="hidden absolute bottom-3 left-3 inline-flex items-center gap-1 bg-blue-600 text-white text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"></span>
      <?php if ((float) ($vendor['rating'] ?? 0) > 0): ?>
        <span class="absolute top-3 right-3 inline-flex items-center gap-1 bg-white/95 text-gray-800 text-xs font-semibold px-2 py-1 rounded-full shadow-sm"><i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-400"></i><?= e(number_format((float) $vendor['rating'], 1)) ?></span>
      <?php endif; ?>
    </div>
    <div class="p-5">
      <h3 class="font-bold text-lg text-gray-900 truncate"><?= e($vendor['name']) ?></h3>
      <p class="text-gray-500 text-sm mt-1 flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4 shrink-0"></i><span class="truncate"><?= e($vendor['address'] ?: $vendor['city'] ?: 'Local store') ?></span></p>
      <?php if (!empty($vendor['deliveryTime'])): ?><p class="text-gray-500 text-sm mt-1 flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4 shrink-0"></i><?= e($vendor['deliveryTime']) ?></p><?php endif; ?>
      <?php if ($isCustomer): ?>
        <form method="post" action="actions.php" class="mt-4">
          <input type="hidden" name="do" value="toggle_favorite" />
          <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>" />
          <input type="hidden" name="redirect" value="index.php#stores" />
          <button class="w-full inline-flex items-center justify-center gap-2 py-2 rounded-lg font-medium transition-colors text-sm <?= $isFav ? 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100' : 'border border-gray-200 text-gray-600 hover:border-amber-300 hover:text-amber-700' ?>">
            <i data-lucide="heart" class="w-4 h-4 <?= $isFav ? 'fill-amber-500 text-amber-500' : '' ?>"></i><?= $isFav ? 'Regular / Family Store' : 'Mark as Regular Store' ?>
          </button>
        </form>
      <?php endif; ?>
      <?php if ($isSel): ?>
        <a href="index.php#products" class="mt-3 flex items-center justify-center gap-1.5 w-full bg-green-600 text-white font-medium py-2 rounded-lg"><i data-lucide="check" class="w-4 h-4"></i>Shopping here</a>
      <?php else: ?>
        <a href="index.php?store=<?= (int) $vendor['id'] ?>#products" class="mt-3 block text-center w-full border border-green-600 text-green-700 hover:bg-green-600 hover:text-white font-medium py-2 rounded-lg transition-colors">Shop at this store</a>
      <?php endif; ?>
    </div>
  </div>
  <?php
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
          <span data-cart-count class="<?= $cartCount > 0 ? '' : 'hidden ' ?>absolute -top-2 -right-2 bg-amber-500 text-white text-xs font-bold rounded-full min-w-[1.25rem] h-5 px-1 flex items-center justify-center ring-2 ring-white"><?= $cartCount ?></span>
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

  <!-- Regular / Family Stores -->
  <?php if ($isCustomer && $favStores): ?>
    <section class="py-10 bg-white border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center gap-2 mb-5"><i data-lucide="heart" class="w-6 h-6 text-amber-500 fill-amber-500"></i><h2 class="text-2xl font-bold text-gray-900">Your Regular / Family Stores</h2></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($favStores as $vendor): store_card($vendor, $isCustomer, $favIds, $selectedStore); endforeach; ?>
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
          <p class="text-gray-500 mt-1"><?= $isCustomer ? 'Tap the heart to save a shop as your Regular / Family Store' : 'Shop from trusted local kirana stores' ?></p>
        </div>
      </div>
      <?php if ($allVendors): ?>
        <!-- Near me: uses the browser's location (only if the customer allows it) -->
        <div class="bg-white border border-blue-100 rounded-2xl p-4 mb-6 flex items-center justify-between gap-3 flex-wrap">
          <p class="text-sm text-gray-600 flex items-center gap-2"><i data-lucide="locate-fixed" class="w-5 h-5 text-blue-600 shrink-0"></i>
            <span data-near-status>Allow location to see the shops and delivery partners closest to you.</span></p>
          <button type="button" data-near-me class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg"><i data-lucide="navigation" class="w-4 h-4"></i>Use my location</button>
        </div>
        <div id="near-partners" class="hidden mb-8">
          <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2"><i data-lucide="bike" class="w-5 h-5 text-blue-600"></i>Delivery partners near you</h3>
          <div id="near-partners-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        </div>
        <div id="store-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($allVendors as $vendor): store_card($vendor, $isCustomer, $favIds, $selectedStore); endforeach; ?>
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
          <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?= $selectedStore ? 'Products at ' . e($selectedStore['name']) : 'Products' ?></h2>
          <p class="text-gray-500 mt-1"><?= count($filteredProducts) ?> item<?= count($filteredProducts) === 1 ? '' : 's' ?> available<?= $cat !== '' ? ' in ' . e($cat) : '' ?><?= $selectedStore ? '' : ' from all stores' ?></p>
        </div>
        <form action="index.php#products" method="get" class="flex items-center gap-2 bg-gray-100 rounded-lg px-3 py-2 w-full md:w-80">
          <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>" /><?php endif; ?>
          <i data-lucide="search" class="w-5 h-5 text-gray-400 shrink-0"></i>
          <input name="q" value="<?= e($search) ?>" placeholder="Search products..." class="flex-1 border-0 bg-transparent outline-none text-sm" />
        </form>
      </div>

      <?php if ($selectedStore): ?>
        <div class="flex items-center justify-between gap-3 flex-wrap bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-5">
          <p class="text-sm text-green-800 flex items-center gap-2"><i data-lucide="store" class="w-4 h-4 shrink-0"></i>You're shopping at <strong><?= e($selectedStore['name']) ?></strong> — only its products are shown.</p>
          <div class="flex items-center gap-2 text-sm">
            <a href="index.php#stores" class="font-medium text-green-700 hover:underline">Change store</a>
            <span class="text-green-300">|</span>
            <a href="index.php?store=all#products" class="font-medium text-gray-600 hover:underline">Browse all stores</a>
          </div>
        </div>
      <?php elseif ($allVendors): ?>
        <p class="text-sm text-gray-500 mb-5 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i>Tip: pick a store above (“Shop at this store”) to see only that shop's products.</p>
      <?php endif; ?>

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
            <div id="p-<?= (int) $product['id'] ?>" class="scroll-mt-24 group bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 flex flex-col">
              <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                <span class="absolute top-3 left-3 bg-white/95 text-gray-700 text-xs font-medium px-2 py-0.5 rounded-full shadow-sm"><?= e($product['category']) ?></span>
                <?php if (!empty($product['hygiene_verified'])): ?>
                  <span class="absolute top-3 right-3 inline-flex items-center gap-1 bg-teal-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full shadow-sm"><i data-lucide="shield-check" class="w-3 h-3"></i>Hygiene</span>
                <?php endif; ?>
                <?php if ($stock <= 0): ?>
                  <div class="absolute inset-0 bg-white/60 flex items-center justify-center"><span class="bg-gray-900 text-white text-xs font-semibold px-3 py-1 rounded-full">Out of stock</span></div>
                <?php endif; ?>
              </div>
              <div class="p-4 flex flex-col flex-1">
                <h3 class="font-semibold text-gray-900 leading-snug line-clamp-1" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></h3>
                <?php if (!empty($product['brand'])): ?>
                  <p class="text-xs text-gray-500 mt-0.5">by <span class="font-medium text-gray-700"><?= e($product['brand']) ?></span></p>
                <?php endif; ?>
                <div class="mt-1.5 space-y-1 text-xs text-gray-500">
                  <p class="flex items-center gap-1"><i data-lucide="store" class="w-3.5 h-3.5 shrink-0"></i><span class="truncate"><?= e($product['vendor'] ?: 'Local store') ?></span></p>
                  <?php if (!empty($product['expiry'])): ?>
                    <p class="flex items-center gap-1"><i data-lucide="calendar-clock" class="w-3.5 h-3.5 shrink-0"></i>Best before <span class="font-medium text-gray-700"><?= e($product['expiry']) ?></span></p>
                  <?php endif; ?>
                  <p class="flex items-center gap-1"><i data-lucide="scale" class="w-3.5 h-3.5 shrink-0"></i>Qty: <span class="font-medium text-gray-700"><?= e($product['unit']) ?></span></p>
                </div>
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
                  <?php else: ?>
                    <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded-full">Out of stock</span>
                  <?php endif; ?>
                </div>
                <?php if ($stock > 0): ?>
                  <form action="cart-action.php" method="post" class="mt-auto" data-ajax-cart>
                    <input type="hidden" name="action" value="add" />
                    <input type="hidden" name="id" value="<?= e($product['id']) ?>" />
                    <input type="hidden" name="redirect" value="index.php#p-<?= (int) $product['id'] ?>" />
                    <button class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white py-2.5 rounded-lg font-medium transition-colors"><i data-lucide="plus" class="w-4 h-4"></i><span data-cart-label>Add to Cart</span></button>
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

<script id="near-partners-data" type="application/json"><?= json_encode($nearbyPartners, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
// "Near me": with the customer's permission, measure the distance to every shop
// (pinned by the vendor, or looked up from its address) and to verified delivery
// partners, then sort the shop list nearest-first. Nothing is sent to our server.
(function () {
  var btn = document.querySelector('[data-near-me]');
  var status = document.querySelector('[data-near-status]');
  var grid = document.getElementById('store-grid');
  if (!btn || !grid) return;
  var PARTNERS = [];
  try { PARTNERS = JSON.parse(document.getElementById('near-partners-data').textContent) || []; } catch (e) {}

  function km(a, b) {
    var R = 6371, r = Math.PI / 180, dLat = (b[0] - a[0]) * r, dLng = (b[1] - a[1]) * r;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a[0] * r) * Math.cos(b[0] * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function fmt(d) { return d < 1 ? Math.round(d * 1000) + ' m away' : d.toFixed(1) + ' km away'; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  // Look up a shop's address once and cache it in the browser (OpenStreetMap, ~1 lookup/sec).
  function geocode(address) {
    if (!address) return Promise.resolve(null);
    var key = 'lkgeo2:' + address;
    try { var c = localStorage.getItem(key); if (c) return Promise.resolve(JSON.parse(c)); } catch (e) {}
    return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=in&q=' + encodeURIComponent(address + ', India'))
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d[0]) return null;
        var pt = [parseFloat(d[0].lat), parseFloat(d[0].lon)];
        try { localStorage.setItem(key, JSON.stringify(pt)); } catch (e) {}
        return pt;
      }).catch(function () { return null; });
  }

  function placeCards(me) {
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-store-card]'));
    var lookups = 0;
    function measure(card, pt) {
      var badge = card.querySelector('[data-distance]');
      if (!pt) { card.dataset.km = '99999'; return; }
      var d = km(me, pt);
      card.dataset.km = String(d);
      if (badge) { badge.textContent = fmt(d); badge.classList.remove('hidden'); }
    }
    function sortGrid() {
      Array.prototype.slice.call(grid.children)
        .sort(function (a, b) { return parseFloat(a.dataset.km || 99999) - parseFloat(b.dataset.km || 99999); })
        .forEach(function (c) { grid.appendChild(c); });
    }
    var queue = [];
    cards.forEach(function (card) {
      var lat = parseFloat(card.dataset.lat), lng = parseFloat(card.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) measure(card, [lat, lng]);
      else queue.push(card);
    });
    sortGrid();
    (function next() {
      var card = queue.shift();
      if (!card || lookups >= 12) { sortGrid(); return; }
      lookups++;
      geocode(card.dataset.geoAddress).then(function (pt) { measure(card, pt); sortGrid(); setTimeout(next, 1100); });
    })();
  }

  function showPartners(me) {
    var box = document.getElementById('near-partners'), g = document.getElementById('near-partners-grid');
    var near = PARTNERS.map(function (p) { return Object.assign({ km: km(me, [p.lat, p.lng]) }, p); })
      .filter(function (p) { return p.km <= 20; })
      .sort(function (a, b) { return a.km - b.km; }).slice(0, 6);
    box.classList.remove('hidden');
    if (!near.length) { g.innerHTML = '<p class="text-sm text-gray-500">No verified delivery partners within 20 km right now — you can still choose Self Pickup.</p>'; return; }
    g.innerHTML = near.map(function (p) {
      var img = p.photo ? '<img src="' + esc(p.photo) + '" alt="" class="w-12 h-12 rounded-full object-cover border" />'
                        : '<div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-bold">' + esc(p.name.charAt(0)) + '</div>';
      return '<div class="bg-white border rounded-xl p-3 flex items-center gap-3">' + img +
        '<div class="min-w-0"><p class="font-semibold text-gray-900 truncate">' + esc(p.name) + ' <span class="text-xs text-green-700">✓ Verified</span></p>' +
        '<p class="text-xs text-gray-500">' + esc(p.vehicle) + (p.rating > 0 ? ' · ★ ' + p.rating.toFixed(1) : '') + ' · about ' + fmt(p.km) + '</p></div></div>';
    }).join('');
  }

  function apply(me) {
    status.textContent = 'Showing shops nearest to you first.';
    btn.innerHTML = '<span>Refresh location</span>';
    placeCards(me);
    showPartners(me);
  }

  btn.addEventListener('click', function () {
    if (!navigator.geolocation) { status.textContent = 'Location is not available in this browser.'; return; }
    status.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(function (p) {
      var me = [p.coords.latitude, p.coords.longitude];
      try { localStorage.setItem('lk_loc', JSON.stringify({ pt: me, t: Date.now() })); } catch (e) {}
      apply(me);
    }, function () { status.textContent = 'Location permission was denied. You can still browse all shops below.'; },
    { enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 });
  });

  // Re-use a location the customer shared in the last 12 hours.
  try {
    var saved = JSON.parse(localStorage.getItem('lk_loc') || 'null');
    if (saved && saved.pt && Date.now() - saved.t < 12 * 3600 * 1000) apply(saved.pt);
  } catch (e) {}
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
