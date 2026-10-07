<?php
require_once __DIR__ . '/includes/init.php';

$items = cart_items();
$total = cart_total_price();
$brand = brand_name();
$me    = current_user();
// Group by store — one cart can hold items from several shops.
$byStore = [];
foreach ($items as $item) { $byStore[$item['vendor'] ?: 'Local store'][] = $item; }
$page_title = 'Your Cart · ' . $brand;
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gray-50">
  <header class="bg-white/90 backdrop-blur border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
      <a href="index.php" class="flex items-center gap-2.5">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-green-600 text-white shadow-sm"><i data-lucide="shopping-basket" class="w-6 h-6"></i></span>
        <span class="text-xl font-extrabold tracking-tight text-gray-900"><?= e($brand) ?></span>
      </a>
      <div class="flex items-center gap-1 text-sm">
        <?php if ($me && ($me['role'] ?? '') === 'customer'): ?>
          <a href="orders.php" class="hidden sm:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">My Orders</a>
          <a href="profile.php" class="hidden sm:inline-flex px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium">Profile</a>
        <?php endif; ?>
        <a href="index.php#products" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium"><i data-lucide="arrow-left" class="w-4 h-4"></i>Continue shopping</a>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-10">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8">Your Cart</h2>

    <?php if (empty($items)): ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-14 text-center">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4"><i data-lucide="shopping-cart" class="w-8 h-8"></i></span>
        <p class="text-gray-600 mb-6">Your cart is empty.</p>
        <a href="index.php#products" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium"><i data-lucide="package" class="w-5 h-5"></i>Browse Products</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Items (grouped by store) -->
        <div class="lg:col-span-2 space-y-4">
          <?php if (count($byStore) > 1): ?>
            <p class="text-sm text-gray-500 inline-flex items-center gap-1.5"><i data-lucide="store" class="w-4 h-4"></i>Your cart has items from <?= count($byStore) ?> stores — they'll be placed as one order.</p>
          <?php endif; ?>
          <?php foreach ($byStore as $storeName => $storeItems): ?>
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
              <div class="px-4 py-2.5 bg-gray-50 border-b flex items-center gap-2 text-sm font-semibold text-gray-700">
                <i data-lucide="store" class="w-4 h-4 text-green-600"></i><?= e($storeName) ?>
              </div>
              <div class="divide-y">
                <?php foreach ($storeItems as $item): ?>
                  <div class="flex items-center gap-4 p-4">
                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="w-20 h-20 object-cover rounded-xl border border-gray-100 shrink-0" />
                    <div class="flex-1 min-w-0">
                      <h3 class="font-semibold text-gray-900 truncate"><?= e($item['name']) ?></h3>
                      <p class="text-sm text-gray-500 truncate"><?= e($item['unit']) ?></p>
                      <p class="text-green-600 font-bold mt-1">₹<?= e($item['price']) ?></p>
                    </div>
                    <div class="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1">
                      <a href="cart-action.php?action=decrement&id=<?= e($item['id']) ?>" class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white text-gray-600" aria-label="Decrease">−</a>
                      <span class="w-8 text-center font-semibold text-sm"><?= e($item['quantity']) ?></span>
                      <a href="cart-action.php?action=increment&id=<?= e($item['id']) ?>" class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white text-gray-600" aria-label="Increase">+</a>
                    </div>
                    <div class="w-24 text-right font-bold text-gray-900 hidden sm:block">₹<?= e($item['price'] * $item['quantity']) ?></div>
                    <a href="cart-action.php?action=remove&id=<?= e($item['id']) ?>" class="text-gray-300 hover:text-red-600 transition-colors" aria-label="Remove"><i data-lucide="trash-2" class="w-5 h-5"></i></a>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:sticky lg:top-24">
          <h3 class="font-semibold text-lg text-gray-900 mb-4">Order Summary</h3>
          <div class="flex justify-between text-sm text-gray-600 mb-2"><span>Items</span><span><?= cart_total_items() ?></span></div>
          <div class="flex justify-between text-sm text-gray-600 mb-2"><span>Subtotal</span><span>₹<?= e($total) ?></span></div>
          <div class="flex justify-between text-sm text-gray-600 mb-4"><span>Delivery</span><span class="text-green-600 font-medium">Free</span></div>
          <div class="flex justify-between items-baseline border-t pt-4">
            <span class="font-semibold text-gray-900">Total</span>
            <span class="text-2xl font-bold text-green-600">₹<?= e($total) ?></span>
          </div>
          <p class="mt-4 text-xs text-gray-500 flex items-center gap-1.5"><i data-lucide="badge-percent" class="w-4 h-4 text-amber-500"></i>Apply offers &amp; choose delivery at checkout.</p>
          <a href="checkout.php" class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium">Proceed to Checkout<i data-lucide="arrow-right" class="w-4 h-4"></i></a>
          <a href="cart-action.php?action=clear" class="mt-3 w-full inline-flex items-center justify-center gap-2 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-lg text-sm font-medium"><i data-lucide="trash-2" class="w-4 h-4"></i>Clear Cart</a>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
