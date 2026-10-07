<?php
// Shared chrome (sidebar + top bar) for the role dashboards.
// Usage:
//   $shell = [
//     'panelTitle'    => 'Sharma Kirana Store',
//     'panelSubtitle' => 'Rajesh Sharma',
//     'baseUrl'       => 'vendor.php',
//     'activeTab'     => 'dashboard',
//     'headerSubtitle'=> 'Manage your store operations',
//     'roleLabel'     => 'Vendor',
//     'userName'      => 'Rajesh Sharma',
//     'notifications' => 3,
//     'nav'           => [ ['id'=>'dashboard','icon'=>'layout-dashboard','label'=>'Dashboard','badge'=>null], ... ],
//   ];
//   render_dashboard_start($shell); ...content... ; render_dashboard_end();

function render_dashboard_start(array $shell): void {
  $active = $shell['activeTab'];
  ?>
  <div class="flex h-screen bg-gray-50">
    <!-- Mobile backdrop (tap to close the drawer) -->
    <div id="dash-backdrop" data-sidebar-close class="fixed inset-0 z-40 bg-black/40 hidden md:hidden" aria-hidden="true"></div>

    <!-- Sidebar: static column on md+, off-canvas drawer on mobile -->
    <aside id="dash-sidebar"
           class="fixed md:static inset-y-0 left-0 z-50 w-64 max-w-[82%] bg-white border-r flex flex-col
                  -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out">
      <div class="p-6 border-b flex items-start justify-between gap-2">
        <div class="min-w-0">
          <h2 class="font-bold text-xl text-green-600 truncate"><?= e($shell['panelTitle']) ?></h2>
          <p class="text-sm text-gray-600 truncate"><?= e($shell['panelSubtitle']) ?></p>
        </div>
        <button type="button" data-sidebar-close aria-label="Close menu"
                class="md:hidden -mr-2 -mt-1 p-2 rounded-lg text-gray-400 hover:bg-gray-100">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <nav class="flex-1 p-4 overflow-y-auto">
        <ul class="space-y-2">
          <?php foreach ($shell['nav'] as $item): ?>
            <li>
              <a href="<?= e($shell['baseUrl']) ?>?tab=<?= e($item['id']) ?>"
                 class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?= $active === $item['id'] ? 'bg-green-50 text-green-600' : 'text-gray-700 hover:bg-gray-50' ?>">
                <i data-lucide="<?= e($item['icon']) ?>" class="w-5 h-5"></i>
                <span class="font-medium flex-1 text-left"><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                  <span class="bg-red-500 text-white text-xs rounded-full px-2 py-0.5"><?= e($item['badge']) ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <div class="p-4 border-t">
        <a href="logout.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-50">
          <i data-lucide="log-out" class="w-5 h-5"></i><span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <header class="bg-white border-b px-4 md:px-8 py-3 md:py-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 md:gap-3 min-w-0">
          <button type="button" data-sidebar-toggle aria-label="Open menu"
                  class="md:hidden -ml-1 p-2 rounded-lg text-gray-600 hover:bg-gray-100 shrink-0">
            <i data-lucide="menu" class="w-6 h-6"></i>
          </button>
          <div class="min-w-0">
            <h1 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?= e(ucfirst($active)) ?></h1>
            <p class="text-xs md:text-sm text-gray-600 truncate"><?= e($shell['headerSubtitle']) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-2 md:gap-4 shrink-0">
          <div class="relative">
            <?php
              $notifs = $shell['notifList'] ?? [];
              $unread = (int) ($shell['notifUnread'] ?? 0);
            ?>
            <button data-bell type="button" class="relative border rounded-lg p-2 hover:bg-gray-50">
              <i data-lucide="bell" class="w-5 h-5"></i>
              <?php if ($unread > 0): ?><span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-semibold rounded-full min-w-[1rem] h-4 px-1 flex items-center justify-center"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
            </button>
            <div id="notif-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white border rounded-xl shadow-xl z-50">
              <div class="px-4 py-3 border-b font-semibold text-sm flex items-center justify-between">
                <span>Notifications</span>
                <?php if ($unread > 0): ?><span class="text-xs font-medium text-red-600"><?= $unread ?> new</span><?php endif; ?>
              </div>
              <ul class="max-h-80 overflow-auto divide-y">
                <?php foreach ($notifs as $n): $u = !empty($n['unread']); ?>
                  <li class="<?= $u ? 'bg-green-50/60' : '' ?>">
                    <?php $inner = '<div class="px-4 py-3 hover:bg-gray-50"><p class="text-sm font-medium flex items-center gap-2">' . ($u ? '<span class="w-1.5 h-1.5 rounded-full bg-green-600 shrink-0"></span>' : '') . e($n['title'] ?? '') . '</p>' . (!empty($n['body']) ? '<p class="text-xs text-gray-500 mt-0.5">' . e($n['body']) . '</p>' : '') . '</div>'; ?>
                    <?php if (!empty($n['link'])): ?><a href="<?= e($n['link']) ?>" class="block"><?= $inner ?></a><?php else: ?><?= $inner ?><?php endif; ?>
                  </li>
                <?php endforeach; ?>
                <?php if (!$notifs): ?><li class="px-4 py-6 text-center text-sm text-gray-400">No notifications yet</li><?php endif; ?>
              </ul>
              <?php if ($unread > 0): ?>
                <div class="border-t px-4 py-2 text-right">
                  <form method="post" action="actions.php">
                    <input type="hidden" name="do" value="notifications_read" />
                    <input type="hidden" name="redirect" value="<?= e($shell['baseUrl'] . '?tab=' . $active) ?>" />
                    <button class="text-xs font-medium text-green-700 hover:underline">Mark all as read</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="font-semibold text-sm"><?= e($shell['userName']) ?></p>
              <p class="text-xs text-gray-600"><?= e($shell['roleLabel']) ?></p>
            </div>
            <div class="w-9 h-9 md:w-10 md:h-10 bg-green-600 rounded-full flex items-center justify-center text-white font-semibold shrink-0"><?= e(mb_substr($shell['userName'], 0, 1)) ?></div>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-4 md:p-6 lg:p-8">
  <?php
}

function render_dashboard_end(): void {
  ?>
      </main>
    </div>
  </div>
  <script>
    // Mobile off-canvas sidebar (drawer) toggle. No-op on md+ where the sidebar is static.
    (function () {
      var sidebar  = document.getElementById('dash-sidebar');
      var backdrop = document.getElementById('dash-backdrop');
      if (!sidebar) return;
      function openDrawer()  { sidebar.classList.remove('-translate-x-full'); if (backdrop) backdrop.classList.remove('hidden'); }
      function closeDrawer() { sidebar.classList.add('-translate-x-full');    if (backdrop) backdrop.classList.add('hidden'); }
      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-toggle]')) { e.preventDefault(); openDrawer();  return; }
        if (e.target.closest('[data-sidebar-close]'))  { e.preventDefault(); closeDrawer(); return; }
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawer(); });
      // If the viewport grows to desktop, make sure the drawer state is reset.
      window.addEventListener('resize', function () { if (window.innerWidth >= 768) closeDrawer(); });
    })();
  </script>
  <?php
}

/** Render a stat card. */
function stat_card(string $value, string $label, string $icon, string $iconBg = 'bg-green-100', string $iconColor = 'text-green-600', ?string $badge = null, bool $badgeUp = true): void {
  ?>
  <div class="bg-white rounded-xl border p-6">
    <div class="flex items-center justify-between <?= $badge ? 'mb-4' : '' ?>">
      <div class="p-3 rounded-lg <?= e($iconBg) ?>"><i data-lucide="<?= e($icon) ?>" class="w-6 h-6 <?= e($iconColor) ?>"></i></div>
      <?php if ($badge !== null): ?>
        <span class="text-xs font-medium px-2 py-1 rounded-full <?= $badgeUp ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>"><?= e($badge) ?></span>
      <?php endif; ?>
    </div>
    <h3 class="text-3xl font-bold <?= $badge ? '' : 'mt-4' ?> mb-1"><?= e($value) ?></h3>
    <p class="text-sm text-gray-600"><?= e($label) ?></p>
  </div>
  <?php
}

/** Consistent empty-state block. */
function empty_state(string $message, string $icon = 'inbox', ?string $ctaHref = null, ?string $ctaLabel = null): void {
  ?>
  <div class="text-center py-12">
    <i data-lucide="<?= e($icon) ?>" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
    <p class="text-gray-500"><?= e($message) ?></p>
    <?php if ($ctaHref && $ctaLabel): ?>
      <a href="<?= e($ctaHref) ?>" class="inline-block mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm"><?= e($ctaLabel) ?></a>
    <?php endif; ?>
  </div>
  <?php
}

/** Build the bell's notification list + unread count for a signed-in person. */
function dashboard_notif_shell(string $role, string $email): array {
  $rows = notifications_for($role, $email, 12);
  $list = array_map(fn($n) => [
    'title'  => $n['title'],
    'body'   => $n['body'],
    'unread' => empty($n['is_read']),
    'link'   => $n['link'] ?? '',
  ], $rows);
  return ['list' => $list, 'unread' => notifications_unread_count($role, $email)];
}

/** Emit a canvas + Chart.js init. $type = line|bar|pie. */
function render_chart(string $id, string $type, array $config, int $height = 250): void {
  echo '<div style="position:relative;height:' . $height . 'px"><canvas id="' . e($id) . '"></canvas></div>';
  echo '<script>document.addEventListener("DOMContentLoaded",function(){new Chart(document.getElementById("' . e($id) . '"),'
     . json_encode(['type' => $type] + $config, JSON_UNESCAPED_UNICODE) . ');});</script>';
}
