# Razorpay payment setup — step by step

Your site now has **real online payments** built in (UPI, cards, net banking, wallets)
powered by **Razorpay**, plus **Cash on Delivery** which already works.

Online payment only needs one thing from you: **your own Razorpay keys**. Until you add
them, the site keeps working and customers can pay by **Cash on Delivery**. Once you add
the keys, the online "Pay" button starts working automatically — no code changes needed.

Follow the parts below in order. **Part 1 + 2 + 3 get you working TEST payments today**
(no real money, no documents). **Part 4 switches on real money later.**

---

## Part 1 — Create a Razorpay account and get TEST keys

1. Go to **https://razorpay.com** and click **Sign Up**. Use your email and create a password.
2. After signing in you land on the **Dashboard**.
3. At the **top-left**, make sure the mode switch says **Test Mode** (not Live). Test mode
   needs no documents and uses pretend money.
4. In the left menu click **Settings** (gear icon) → **API Keys**.
5. Click **Generate Test Key**.
6. A box shows two values — **copy both** somewhere safe (e.g. Notepad):
   - **Key Id** — looks like `rzp_test_AbCdEf123456`
   - **Key Secret** — a long random string (shown **only once** — if you lose it, generate a new key)

> Keep the **Key Secret** private. Never paste it into a public chat, email, or a file you
> commit to GitHub. The steps below keep it safe.

---

## Part 2 — Put the keys on your LIVE website (recommended way)

Your site auto-deploys to Hostinger whenever you push to GitHub. We add the keys as
**GitHub secrets** (the same place you added `FTP_SERVER` etc.), and the deploy writes
them onto the server for you — without ever putting the secret in your public code.

1. Open your repository on **github.com** → **Darshu03-cell/LocalKirana** (or your repo).
2. Click **Settings** (top menu of the repo).
3. In the left menu: **Secrets and variables** → **Actions**.
4. Click **New repository secret**. Add the first one:
   - **Name:** `RAZORPAY_KEY_ID`
   - **Secret:** paste your `rzp_test_...` Key Id
   - Click **Add secret**.
5. Click **New repository secret** again. Add the second one:
   - **Name:** `RAZORPAY_KEY_SECRET`
   - **Secret:** paste your Key Secret
   - Click **Add secret**.
6. Now trigger a deploy so the keys reach the server. Either:
   - push any small change to `main`, **or**
   - go to the repo's **Actions** tab → open **Deploy to Hostinger (FTP)** →
     click **Run workflow** → **Run workflow**.
7. Wait for the green tick (about 1–2 minutes). Done — your live site can now take online payments.

---

## Part 3 — Test a payment (no real money)

1. Open your live site and **sign in as a customer**. Make sure the customer profile has a
   delivery address (Profile page).
2. Add a product to the cart → **Cart** → **Proceed to Checkout**.
3. Pick any online method (**UPI / Card / Net Banking / Wallet**) and tap **Pay … securely**.
4. The **Razorpay window** opens. Use a **test card**:
   - Card number: `4111 1111 1111 1111`
   - Expiry: any future date (e.g. `12/30`)   •   CVV: any 3 digits (e.g. `123`)
   - Name/OTP: anything; on the OTP screen click **Success**.
   - (Or choose **UPI** in the window and type `success@razorpay` to simulate a paid UPI.)
5. After "Success", you are returned to **My Orders** with **"Payment successful! Order #ORD… confirmed."**
6. Sign in as **Super Admin → Orders**, open that order — it shows **Paid** and the
   **Razorpay ref** (`pay_...`). That confirms the whole flow end to end.

If the window says *"Authentication failed"* or you get *"Could not start online payment"*,
the keys aren't set correctly — re-check Part 2 (names must be **exactly**
`RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET`) and re-run the deploy.

---

## Part 4 — Switch on REAL money (when you're ready)

1. In the Razorpay Dashboard, complete **KYC / Activation** (business details + bank account).
   Razorpay reviews and activates your account.
2. Switch the dashboard to **Live Mode** → **Settings → API Keys → Generate Live Key**.
   You get `rzp_live_...` Key Id + a new Secret.
3. Go back to **GitHub → Settings → Secrets and variables → Actions** and **update** the two
   secrets with the **live** values (click each secret → **Update**).
4. Re-run the deploy (Part 2, step 6). Real customers now pay for real.

> Tip: keep using test keys until you've placed a few successful test orders and are happy.

---

## How it behaves (quick reference)

| Situation | What customers see |
|---|---|
| No keys set yet | Cash on Delivery works. Online buttons still place the order (so the site never blocks). |
| Test keys set | Real Razorpay window, **pretend** money. Perfect for trying it out. |
| Live keys set | Real Razorpay window, **real** money into your Razorpay account. |

**Cash on Delivery always works**, with or without Razorpay.

---

## Testing on your own computer (optional, for developers)

Instead of GitHub secrets you can create a local file (it is **git-ignored**, so it never
gets committed):

1. Copy `includes/config.sample.php` to `includes/config.local.php`.
2. Paste your test Key Id and Key Secret into it.
3. Run the site locally with XAMPP PHP and test as in Part 3.

---

## Security notes (why it's done this way)

- The **Key Secret is never committed to GitHub**. It lives only in GitHub Actions secrets
  and is written onto the server at deploy time (`includes/config.local.php`, which is
  git-ignored).
- The site **never stores card numbers/CVV** — those are typed inside Razorpay's own secure
  window. (The old demo checkout collected them in plain boxes; that's now removed.)
- Every payment is **verified on the server** using Razorpay's signature before the order is
  marked paid, so a fake "success" from the browser cannot create a paid order.
