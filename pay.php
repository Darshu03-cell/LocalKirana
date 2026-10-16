<?php
require_once __DIR__ . '/includes/init.php';

$user = require_role('customer');

$pending = $_SESSION['pending_order'] ?? null;

// Guard: need a live pending payment, keys configured, and a non-empty cart.
if (!$pending || empty($pending['rzp_order_id']) || !razorpay_enabled()) {
  set_flash('Your payment session expired. Please try again.', 'error');
  header('Location: checkout.php');
  exit;
}
if (!cart_items()) {
  unset($_SESSION['pending_order']);
  set_flash('Your cart is empty.', 'error');
  header('Location: cart.php');
  exit;
}
// Expire stale sessions (older than 20 minutes) so a left-open tab can't pay late.
if (!empty($pending['created_at']) && (time() - (int) $pending['created_at']) > 1200) {
  unset($_SESSION['pending_order']);
  set_flash('Your payment session expired. Please try again.', 'error');
  header('Location: checkout.php');
  exit;
}

$brand      = brand_name();
$amount     = (int) $pending['amount'];          // rupees
$page_title = 'Secure payment · ' . $brand;
require __DIR__ . '/partials/head.php';
?>
<div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl border shadow-sm w-full max-w-md p-8 text-center">
    <i data-lucide="shield-check" class="w-14 h-14 text-green-600 mx-auto mb-4"></i>
    <h1 class="text-2xl font-bold mb-1">Secure payment</h1>
    <p class="text-gray-600 mb-6">Complete your payment of <span class="font-semibold text-gray-900">₹<?= e($amount) ?></span> in the Razorpay window.</p>

    <div id="pay-status" class="text-sm text-gray-500 mb-6">Opening the secure Razorpay window…</div>

    <button id="pay-btn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium">
      Pay ₹<?= e($amount) ?> securely
    </button>
    <a href="checkout.php?cancelled=1" class="block mt-4 text-sm text-gray-500 hover:text-gray-700">Cancel and go back</a>

    <p class="text-xs text-gray-400 mt-6 flex items-center justify-center gap-1">
      <i data-lucide="lock" class="w-3.5 h-3.5"></i> Payments are processed securely by Razorpay.
    </p>
  </div>
</div>

<!-- Hidden form: submitted with the payment result for server-side verification. -->
<form id="verify-form" method="post" action="actions.php" class="hidden">
  <input type="hidden" name="do" value="razorpay_verify" />
  <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" />
  <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" />
  <input type="hidden" name="razorpay_signature" id="razorpay_signature" />
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
  var options = {
    key:      <?= json_encode(razorpay_key_id()) ?>,
    order_id: <?= json_encode($pending['rzp_order_id']) ?>,
    amount:   <?= (int) $amount * 100 ?>,
    currency: "INR",
    name:     <?= json_encode($brand) ?>,
    description: "Order payment",
    prefill: {
      name:    <?= json_encode($user['name'] ?? '') ?>,
      email:   <?= json_encode($user['email'] ?? '') ?>,
      contact: <?= json_encode($user['phone'] ?? '') ?>
    },
    theme: { color: "#16a34a" },
    handler: function (response) {
      document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id || '';
      document.getElementById('razorpay_order_id').value   = response.razorpay_order_id || '';
      document.getElementById('razorpay_signature').value  = response.razorpay_signature || '';
      document.getElementById('pay-status').textContent = 'Verifying your payment…';
      document.getElementById('verify-form').submit();
    },
    modal: {
      ondismiss: function () {
        document.getElementById('pay-status').textContent = 'Payment window closed. You can try again below.';
      }
    }
  };

  var rzp = new Razorpay(options);
  rzp.on('payment.failed', function (resp) {
    var msg = (resp && resp.error && resp.error.description) ? resp.error.description : 'Payment failed. Please try again.';
    document.getElementById('pay-status').textContent = msg;
  });

  function openRzp() { rzp.open(); }
  document.getElementById('pay-btn').addEventListener('click', openRzp);
  // Auto-open once the script is ready.
  window.addEventListener('load', openRzp);
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
