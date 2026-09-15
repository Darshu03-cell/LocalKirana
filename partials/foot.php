<!-- Shared modal shell (used by [data-modal] triggers and inline forms) -->
<div id="modal-overlay" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/40 p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 id="modal-title" class="font-bold text-lg">Details</h3>
      <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-700"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div id="modal-body" class="p-6"></div>
  </div>
</div>

<!-- LocalKirana assistant -->
<div id="kirana-chat" class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[80] flex flex-col items-end gap-3" data-open="false">
  <section id="kirana-chat-panel" class="hidden w-[calc(100vw-2rem)] max-w-[22rem] overflow-hidden rounded-2xl border border-green-100 bg-white shadow-2xl shadow-green-950/15" aria-labelledby="kirana-chat-title">
    <div class="flex items-start justify-between gap-3 bg-green-700 px-4 py-4 text-white">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-amber-300"><i data-lucide="message-circle" class="h-5 w-5"></i></span>
        <div>
          <h2 id="kirana-chat-title" class="font-bold leading-tight">Kirana Assistant</h2>
          <p class="mt-0.5 text-xs text-green-100">Here to help with your order</p>
        </div>
      </div>
      <button type="button" data-chat-close aria-label="Close chat" class="rounded-lg p-1.5 text-green-100 transition-colors hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/70"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div id="kirana-chat-messages" class="max-h-[min(22rem,46vh)] min-h-[12rem] space-y-3 overflow-y-auto bg-gray-50 p-4" aria-live="polite">
      <div class="flex items-start gap-2">
        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><i data-lucide="store" class="h-4 w-4"></i></span>
        <p class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-sm leading-relaxed text-gray-700 shadow-sm">Hi! I can help you find products, check offers, or understand delivery and orders.</p>
      </div>
      <div class="flex flex-wrap gap-2 pl-9" data-chat-quick-actions>
        <button type="button" data-chat-prompt="Show offers" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition-colors hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400">Show offers</button>
        <button type="button" data-chat-prompt="How do I order?" class="rounded-full border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-800 transition-colors hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400">How do I order?</button>
        <button type="button" data-chat-prompt="Delivery help" class="rounded-full border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-800 transition-colors hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400">Delivery help</button>
      </div>
    </div>
    <form id="kirana-chat-form" class="flex items-center gap-2 border-t border-gray-200 bg-white p-3">
      <label for="kirana-chat-input" class="sr-only">Ask the Kirana Assistant</label>
      <input id="kirana-chat-input" type="text" autocomplete="off" placeholder="Ask about shopping..." class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:ring-2 focus:ring-green-100" />
      <button type="submit" aria-label="Send message" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-1"><i data-lucide="send" class="h-4 w-4"></i></button>
    </form>
  </section>
  <button type="button" data-chat-toggle aria-expanded="false" aria-controls="kirana-chat-panel" aria-label="Open Kirana Assistant" class="group relative inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-green-600 text-white shadow-lg shadow-green-900/20 transition-all hover:-translate-y-0.5 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
    <span class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-white bg-amber-400" aria-hidden="true"></span>
    <i data-lucide="message-circle" class="h-6 w-6 transition-transform group-hover:scale-105"></i>
  </button>
</div>

<script>
  function closeModal() {
    var o = document.getElementById('modal-overlay');
    o.classList.add('hidden'); o.classList.remove('flex');
  }
  function openModal(title, html) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = html;
    var o = document.getElementById('modal-overlay');
    o.classList.remove('hidden'); o.classList.add('flex');
    if (window.lucide) lucide.createIcons();
  }
  document.addEventListener('click', function (e) {
    // Open a modal from a trigger that carries data-modal-title + data-modal-body,
    // or that points at a hidden template via data-modal-target.
    var t = e.target.closest('[data-modal]');
    if (t) {
      e.preventDefault();
      var title = t.getAttribute('data-modal-title') || 'Details';
      var tgt = t.getAttribute('data-modal-target');
      var html = tgt ? (document.querySelector(tgt) ? document.querySelector(tgt).innerHTML : '')
                     : (t.getAttribute('data-modal-body') || '');
      openModal(title, html);
      return;
    }
    // Click on the dark overlay (but not the dialog) closes it.
    if (e.target.id === 'modal-overlay') closeModal();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

  // Notification bell dropdown toggle.
  document.addEventListener('click', function (e) {
    var bell = e.target.closest('[data-bell]');
    var panel = document.getElementById('notif-panel');
    if (bell) { e.preventDefault(); if (panel) panel.classList.toggle('hidden'); return; }
    if (panel && !e.target.closest('#notif-panel')) panel.classList.add('hidden');
  });

  // Confirm before destructive form submits.
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Lightweight local help that works without an API or account state.
  (function () {
    var chat = document.getElementById('kirana-chat');
    var panel = document.getElementById('kirana-chat-panel');
    var toggle = document.querySelector('[data-chat-toggle]');
    var close = document.querySelector('[data-chat-close]');
    var form = document.getElementById('kirana-chat-form');
    var input = document.getElementById('kirana-chat-input');
    var messages = document.getElementById('kirana-chat-messages');
    if (!chat || !panel || !toggle || !form || !input || !messages) return;

    var replies = [
      { words: ['offer', 'discount', 'deal'], html: 'You can find the latest local deals in <a href="index.php#products" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">Offers and Products</a>. Add an item to your cart to get started.' },
      { words: ['order status', 'track', 'my order', 'orders'], html: 'You can review recent purchases and their status on the <a href="orders.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">My Orders</a> page.' },
      { words: ['order', 'buy', 'shop', 'purchase'], html: 'Browse <a href="index.php#products" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">products</a>, choose Add to Cart, then review your items in the <a href="cart.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">cart</a> and continue to checkout.' },
      { words: ['delivery', 'deliver', 'shipping', 'arrive'], html: 'Local stores offer quick delivery. Your delivery details and status are shown during checkout and in <a href="orders.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">My Orders</a>.' },
      { words: ['cart', 'basket'], html: 'Your cart is ready whenever you are. <a href="cart.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">Open your cart</a> to review quantities and continue shopping.' },
      { words: ['login', 'profile', 'account', 'address'], html: 'Manage your account and saved details from <a href="profile.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">Profile</a>. Need an account first? <a href="login.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">Log in or sign up</a>.' },
      { words: ['help', 'contact', 'support', 'problem'], html: 'I can help with products, offers, cart, delivery, and orders. For anything else, please use the account pages or contact your local store.' }
    ];

    function setOpen(open) {
      panel.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Minimise Kirana Assistant' : 'Open Kirana Assistant');
      chat.setAttribute('data-open', open ? 'true' : 'false');
      if (open) setTimeout(function () { input.focus(); }, 0);
    }

    function addMessage(text, fromUser, html) {
      var row = document.createElement('div');
      row.className = fromUser ? 'flex justify-end' : 'flex items-start gap-2';
      row.innerHTML = fromUser
        ? '<p class="max-w-[85%] rounded-2xl rounded-tr-sm bg-green-600 px-3 py-2 text-sm leading-relaxed text-white">' + escapeHtml(text) + '</p>'
        : '<span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><i data-lucide="store" class="h-4 w-4"></i></span><p class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-sm leading-relaxed text-gray-700 shadow-sm">' + html + '</p>';
      messages.appendChild(row);
      messages.scrollTop = messages.scrollHeight;
      if (window.lucide) lucide.createIcons();
    }

    function escapeHtml(value) {
      return value.replace(/[&<>'"]/g, function (character) { return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]; });
    }

    function answer(text) {
      var normalized = text.toLowerCase();
      var match = replies.find(function (reply) { return reply.words.some(function (word) { return normalized.indexOf(word) !== -1; }); });
      return match ? match.html : 'I can help with <a href="index.php#products" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">products and offers</a>, your <a href="cart.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">cart</a>, delivery, orders, or your <a href="profile.php" class="font-semibold text-green-700 underline decoration-green-300 underline-offset-2">profile</a>. What would you like to know?';
    }

    function send(text) {
      text = text.trim();
      if (!text) return;
      addMessage(text, true);
      input.value = '';
      window.setTimeout(function () { addMessage('', false, answer(text)); }, 180);
    }

    toggle.addEventListener('click', function () { setOpen(panel.classList.contains('hidden')); });
    if (close) close.addEventListener('click', function () { setOpen(false); });
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    document.querySelectorAll('[data-chat-prompt]').forEach(function (button) {
      button.addEventListener('click', function () { send(button.getAttribute('data-chat-prompt')); });
    });
  }());

  // Show/hide password (eye toggle).
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    e.preventDefault();
    var input = document.querySelector(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    var reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    var eye = btn.querySelector('.icon-eye'), off = btn.querySelector('.icon-eye-off');
    if (eye) eye.classList.toggle('hidden', reveal);
    if (off) off.classList.toggle('hidden', !reveal);
  });

  // Render Lucide icons.
  if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
