<?php
require_once __DIR__ . '/includes/init.php';

$roleInfo = [
  'customer' => ['icon' => 'user',    'title' => 'Customer',         'desc' => 'Order groceries from local stores'],
  'vendor'   => ['icon' => 'store',   'title' => 'Vendor',           'desc' => 'Manage your kirana store'],
  'supplier' => ['icon' => 'package', 'title' => 'Supplier',         'desc' => 'Supply products to vendors'],
  'delivery' => ['icon' => 'truck',   'title' => 'Delivery Partner', 'desc' => 'Manage your deliveries'],
  'admin'    => ['icon' => 'shield',  'title' => 'Admin',            'desc' => 'System administration'],
  'superadmin' => ['icon' => 'shield-check', 'title' => 'Super Admin', 'desc' => 'Monitor orders, payments & customers'],
];

$activeRole = $_GET['role'] ?? 'customer';
if (!isset($roleInfo[$activeRole])) $activeRole = 'customer';
$mode = ($_GET['mode'] ?? 'signin') === 'signup' ? 'signup' : 'signin';

/** Admin / Super Admin accounts can't be self-created once one exists — otherwise
 *  anyone could sign up as an admin. The very first one may still register. */
function signup_allowed(string $role): bool {
  if (!in_array($role, ['admin', 'superadmin'], true)) return true;
  return !db_row("SELECT 1 FROM accounts WHERE role = :r LIMIT 1", [':r' => $role]);
}
if ($mode === 'signup' && !signup_allowed($activeRole)) $mode = 'signin';
$isSignup = $mode === 'signup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $role     = $_POST['role'] ?? 'customer';
  $email    = strtolower(trim($_POST['email'] ?? ''));
  $password = (string) ($_POST['password'] ?? '');
  $back     = 'login.php?role=' . urlencode($role) . '&mode=' . (!empty($_POST['signup']) ? 'signup' : 'signin');

  if (!isset($roleInfo[$role])) { header('Location: login.php'); exit; }

  /* ------------------------------- SIGN UP ------------------------------- */
  if (!empty($_POST['signup'])) {
    if (!signup_allowed($role)) {
      set_flash(ucfirst($role) . ' accounts can only be created by the existing administrator.', 'error');
      header('Location: login.php?role=' . urlencode($role) . '&mode=signin'); exit;
    }
    $name = trim($_POST['name'] ?? '');
    if ($email === '' || $password === '' || $name === '') {
      set_flash('Please fill in your name, email and password.', 'error');
      header('Location: ' . $back); exit;
    }
    if (account_find($email)) {
      set_flash('An account with ' . $email . ' already exists. Please sign in.', 'error');
      header('Location: login.php?role=' . urlencode($role) . '&mode=signin'); exit;
    }

    $phone = trim($_POST['phone'] ?? '');
    $account = ['email' => $email, 'role' => $role, 'name' => $name, 'phone' => $phone];

    if ($role === 'vendor') {
      $profile = vendor_profile_create(['name' => trim($_POST['shopName'] ?? '') ?: ($name . "'s Store"), 'owner' => $name, 'email' => $email, 'phone' => $phone]);
      $account['shopName'] = $profile['name'];
      $account['vendorId'] = $profile['id'];
    } elseif ($role === 'supplier') {
      supplier_profile_create(['name' => trim($_POST['company'] ?? '') ?: $name, 'contact' => $name, 'email' => $email, 'phone' => $phone]);
    } elseif ($role === 'delivery') {
      delivery_profile_create(['name' => $name, 'email' => $email, 'phone' => $phone, 'vehicle' => trim($_POST['vehicle'] ?? 'Bike')]);
    }

    account_register($account, $password);
    session_regenerate_id(true);
    $_SESSION['user'] = account_to_user(account_find($email));
    if ($role === 'customer') {
      set_flash('Welcome, ' . $name . '! Please add your delivery address to finish your profile.');
      header('Location: profile.php');
    } elseif ($role === 'delivery') {
      set_flash('Welcome, ' . $name . '! Complete your profile and upload an ID proof — you can take orders once it is verified.');
      header('Location: delivery.php?tab=profile');
    } else {
      set_flash('Account created — welcome, ' . $name . '!');
      header('Location: ' . dashboard_for($role));
    }
    exit;
  }

  /* ------------------------------- SIGN IN ------------------------------- */
  $acct = account_authenticate($email, $password);
  if (!$acct) {
    set_flash('Invalid email or password. If you are new, please sign up.', 'error');
    header('Location: ' . $back); exit;
  }
  // Each login page is for one role only — a vendor account can't sign in on the
  // Customer page (and so on). Point them to the page for the role they registered as.
  $acctRole = $acct['role'] ?? 'customer';
  if ($acctRole !== $role) {
    $right = $roleInfo[$acctRole]['title'] ?? ucfirst($acctRole);
    set_flash('This account is registered as a ' . $right . '. Please sign in on the ' . $right . ' login.', 'error');
    header('Location: login.php?role=' . urlencode($acctRole) . '&mode=signin'); exit;
  }
  session_regenerate_id(true);
  $_SESSION['user'] = account_to_user($acct);
  if (($acct['role'] ?? '') === 'customer' && !profile_complete($_SESSION['user'])) {
    set_flash('Welcome back! Please add your delivery address to place orders.');
    header('Location: profile.php');
  } else {
    set_flash('Signed in as ' . $_SESSION['user']['name'] . '.');
    header('Location: ' . dashboard_for($acct['role']));
  }
  exit;
}

$page_title = ($isSignup ? 'Sign Up' : 'Login') . ' · Local Kirana Connect';
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gradient-to-br from-green-50 to-green-100 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden">
    <div class="grid grid-cols-1 md:grid-cols-2">
      <!-- Left branding -->
      <div class="bg-gradient-to-br from-green-600 to-green-500 p-8 md:p-12 text-white flex flex-col justify-center">
        <div class="mb-8">
          <i data-lucide="shopping-cart" class="w-16 h-16 mb-4"></i>
          <h1 class="text-4xl font-bold mb-4"><?= e(brand_name()) ?></h1>
          <p class="text-green-50 text-lg">Empowering local businesses with digital solutions</p>
        </div>
        <div class="space-y-4">
          <?php foreach ([['user', 'For Customers', 'Order from nearby stores, delivered fast'], ['store', 'For Vendors', 'Grow your business online'], ['package', 'For Suppliers', 'Connect with multiple vendors']] as $perk): ?>
            <div class="flex items-start gap-3">
              <div class="bg-white/20 rounded-lg p-2"><i data-lucide="<?= $perk[0] ?>" class="w-6 h-6"></i></div>
              <div><h3 class="font-semibold mb-1"><?= e($perk[1]) ?></h3><p class="text-sm text-green-50"><?= e($perk[2]) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right form -->
      <div class="p-8 md:p-12">
        <a href="index.php" class="inline-block mb-6 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">← Back to Home</a>

        <div class="grid grid-cols-6 gap-1 mb-6 bg-gray-100 rounded-lg p-1">
          <?php foreach ($roleInfo as $role => $info): ?>
            <a href="login.php?role=<?= $role ?>&mode=<?= $mode ?>" title="<?= e($info['title']) ?>"
               class="flex flex-col items-center justify-center gap-0.5 py-2 rounded-md <?= $activeRole === $role ? 'bg-white shadow text-green-600' : 'text-gray-500 hover:text-gray-700' ?>">
              <i data-lucide="<?= $info['icon'] ?>" class="w-4 h-4"></i>
              <span class="text-[10px] leading-tight font-medium text-center"><?= e($role === 'delivery' ? 'Delivery' : ($role === 'superadmin' ? 'Super' : $info['title'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php $info = $roleInfo[$activeRole]; ?>
        <div class="text-center mb-6">
          <i data-lucide="<?= $info['icon'] ?>" class="w-12 h-12 text-green-600 mx-auto mb-3"></i>
          <h2 class="text-2xl font-bold text-gray-900"><?= $isSignup ? 'Create ' . e($info['title']) . ' account' : e($info['title']) . ' Login' ?></h2>
          <p class="text-gray-600 mt-1"><?= e($info['desc']) ?></p>
        </div>

        <form method="post" action="login.php" class="space-y-4">
          <input type="hidden" name="role" value="<?= e($activeRole) ?>" />
          <?php if ($isSignup): ?>
            <input type="hidden" name="signup" value="1" />
            <div>
              <label class="block text-sm font-medium mb-1" for="name"><?= $activeRole === 'supplier' ? 'Contact person' : 'Full name' ?></label>
              <input id="name" name="name" type="text" required placeholder="Enter your name" class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500" />
            </div>
            <?php if ($activeRole === 'vendor'): ?>
              <div><label class="block text-sm font-medium mb-1" for="shopName">Store name</label><input id="shopName" name="shopName" type="text" placeholder="e.g. Sharma Kirana Store" class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500" /></div>
            <?php elseif ($activeRole === 'supplier'): ?>
              <div><label class="block text-sm font-medium mb-1" for="company">Company name</label><input id="company" name="company" type="text" placeholder="e.g. Wholesale Foods India" class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500" /></div>
            <?php elseif ($activeRole === 'delivery'): ?>
              <div><label class="block text-sm font-medium mb-1" for="vehicle">Vehicle</label>
                <select id="vehicle" name="vehicle" class="w-full border rounded-lg px-3 py-2"><option>Bike</option><option>Scooter</option><option>Car</option><option>Van</option></select>
              </div>
            <?php endif; ?>
            <div><label class="block text-sm font-medium mb-1" for="phone">Phone <span class="text-xs text-gray-400">(optional)</span></label><input id="phone" name="phone" type="text" placeholder="+91 ..." class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500" /></div>
          <?php endif; ?>
          <div>
            <label class="block text-sm font-medium mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" required placeholder="Enter your email" class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1" for="password">Password</label>
            <div class="relative">
              <input id="password" name="password" type="password" required placeholder="Enter your password" class="w-full border rounded-lg px-3 py-2 pr-11 outline-none focus:ring-2 focus:ring-green-500" />
              <button type="button" data-toggle-password="#password" aria-label="Show password" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1">
                <i data-lucide="eye" class="icon-eye w-5 h-5"></i>
                <i data-lucide="eye-off" class="icon-eye-off w-5 h-5 hidden"></i>
              </button>
            </div>
          </div>
          <button class="w-full bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg font-medium"><?= $isSignup ? 'Create Account' : 'Sign In' ?></button>
          <div class="text-center text-sm text-gray-600">
            <?php if ($isSignup): ?>
              Already have an account? <a href="login.php?role=<?= e($activeRole) ?>&mode=signin" class="text-green-600 font-semibold">Sign In</a>
            <?php elseif (signup_allowed($activeRole)): ?>
              Don't have an account? <a href="login.php?role=<?= e($activeRole) ?>&mode=signup" class="text-green-600 font-semibold">Sign Up</a>
            <?php else: ?>
              <?= e($info['title']) ?> accounts are created by the administrator.
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
