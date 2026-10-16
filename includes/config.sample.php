<?php
// ---------------------------------------------------------------------------
// TEMPLATE — copy this file to  includes/config.local.php  and paste your keys.
//
//   includes/config.local.php  is git-ignored, so your secret key stays private
//   and is never pushed to GitHub.
//
// How to get your keys:
//   Razorpay Dashboard -> Settings -> API Keys -> Generate Key
//   TEST mode gives keys that start with  rzp_test_  (no real money, no KYC).
//   LIVE mode gives keys that start with  rzp_live_  (real money, needs KYC).
//
// On the live server these keys are normally injected automatically by the
// deploy workflow from GitHub Actions secrets, so you only need this file for
// local testing. See RAZORPAY_SETUP.md for step-by-step instructions.
// ---------------------------------------------------------------------------

return [
  'razorpay_key_id'     => 'rzp_test_xxxxxxxxxxxxxx',
  'razorpay_key_secret' => 'your_key_secret_here',
];
