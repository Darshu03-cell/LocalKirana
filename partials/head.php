<?php $page_title = $page_title ?? 'Local Kirana Connect'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($page_title) ?></title>

  <!-- Installable app (PWA): name, icon and colours used when added to a phone's home screen -->
  <link rel="manifest" href="manifest.json" />
  <meta name="theme-color" content="#16a34a" />
  <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32.png" />
  <link rel="apple-touch-icon" href="icons/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="Local Kirana" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js'); });
    }
  </script>

  <!-- Tailwind (Play CDN – no build step needed) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Inter', 'system-ui', 'Segoe UI', 'sans-serif'] }
        }
      }
    };
  </script>

  <!-- Lucide icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <!-- Chart.js for dashboards -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    html, body { height: 100%; }
    body { font-family: Inter, system-ui, 'Segoe UI', sans-serif; }
  </style>
</head>
<body class="antialiased text-gray-900">
<?php $__flash = function_exists('take_flash') ? take_flash() : null; ?>
<?php if ($__flash): ?>
  <div id="flash" class="fixed top-4 right-4 z-[100] max-w-sm rounded-lg shadow-lg px-4 py-3 text-white flex items-start gap-3 <?= $__flash['type'] === 'error' ? 'bg-red-600' : 'bg-green-600' ?>">
    <i data-lucide="<?= $__flash['type'] === 'error' ? 'alert-circle' : 'check-circle' ?>" class="w-5 h-5 mt-0.5 flex-shrink-0"></i>
    <p class="text-sm flex-1"><?= e($__flash['message']) ?></p>
    <button onclick="document.getElementById('flash').remove()" class="opacity-80 hover:opacity-100">✕</button>
  </div>
  <script>setTimeout(function(){var f=document.getElementById('flash'); if(f) f.remove();}, 4000);</script>
<?php endif; ?>
