<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$text = ['store_name' => 80, 'tagline' => 160, 'announcement' => 200, 'store_phone' => 20, 'store_phone_2' => 20, 'store_email' => 120, 'store_address' => 250,
         'whatsapp' => 20, 'social_facebook' => 255, 'social_instagram' => 255, 'social_twitter' => 255, 'social_youtube' => 255, 'razorpay_key_id' => 64,
         'about_text' => 5000, 'policy_terms' => 20000, 'policy_privacy' => 20000, 'policy_refund' => 20000, 'policy_shipping' => 20000];
$numbers = ['shipping_fee', 'free_shipping_over', 'cod_max_amount', 'reservation_minutes', 'low_stock_default', 'stock_poll_seconds'];
$bools = ['enable_cod', 'enable_razorpay', 'order_email_notify'];
$secrets = ['razorpay_key_secret', 'razorpay_webhook_secret'];

if (is_post()) {
    csrf_check();
    if (post('action') === 'test_rzp') {
        try {
            rzp_api('GET', '/orders?count=1');
            flash('success', 'Razorpay connection works (' . (rzp_test_mode() ? 'TEST' : 'LIVE') . ' mode).');
        } catch (Throwable $e) {
            flash('error', 'Razorpay test failed: ' . $e->getMessage() . ' — check your Key ID and Key Secret.');
        }
        redirect('admin/settings.php#payments');
    }
    $errors = [];
    foreach ($text as $k => $max) if (array_key_exists($k, $_POST)) setting_set($k, mb_substr(trim((string)$_POST[$k]), 0, $max));
    foreach ($numbers as $k) if (array_key_exists($k, $_POST)) setting_set($k, (string)max(0, (float)$_POST[$k]));
    foreach ($bools as $k) setting_set($k, !empty($_POST[$k]) ? '1' : '0');
    foreach ($secrets as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($v !== '') setting_set($k, $v); // blank = keep current
        if (!empty($_POST['clear_' . $k])) setting_set($k, '');
    }
    $color = (string)post('theme_color');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) setting_set('theme_color', $color);
    if (post('store_email') !== '' && !valid_email((string)post('store_email'))) flash('error', 'The store email looks invalid.');
    $kid = (string)post('razorpay_key_id');
    if ($kid !== '' && !preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/', $kid)) flash('error', 'Razorpay Key ID should start with rzp_test_ or rzp_live_.');
    settings_all(true);
    flash('success', 'Settings saved.');
    redirect('admin/settings.php' . (post('_section') ? '#' . preg_replace('/[^a-z]/', '', (string)post('_section')) : ''));
}

$s = settings_all();
$v = fn($k) => e($s[$k] ?? '');
$page_title = 'Settings & payments';
include __DIR__ . '/_top.php';
?>
<nav class="status-tabs settings-tabs">
  <a href="#store">Store</a><a href="#payments">Payments</a><a href="#delivery">Delivery</a><a href="#stock">Stock</a><a href="#pages">About & policies</a>
</nav>

<form method="post" class="settings">
  <?= csrf_field() ?>

  <section class="panel" id="store">
    <h2>Store details</h2>
    <div class="row2">
      <label class="f"><span>Store name</span><input name="store_name" value="<?= $v('store_name') ?>" required maxlength="80"></label>
      <label class="f"><span>Brand colour</span><input type="color" name="theme_color" value="<?= $v('theme_color') ?: '#2f6fed' ?>"></label>
    </div>
    <label class="f"><span>Tagline</span><input name="tagline" value="<?= $v('tagline') ?>" maxlength="160"></label>
    <label class="f"><span>Announcement bar (top of every page — leave empty to hide)</span><input name="announcement" value="<?= $v('announcement') ?>" maxlength="200"></label>
    <div class="row2">
      <label class="f"><span>Phone</span><input name="store_phone" value="<?= $v('store_phone') ?>" maxlength="20"></label>
      <label class="f"><span>Second phone (optional)</span><input name="store_phone_2" value="<?= $v('store_phone_2') ?>" maxlength="20"></label>
    </div>
    <div class="row2">
      <label class="f"><span>Email (orders & messages are sent here)</span><input type="email" name="store_email" value="<?= $v('store_email') ?>" maxlength="120"></label>
      <label class="f"><span>WhatsApp number (shows a chat button)</span><input name="whatsapp" value="<?= $v('whatsapp') ?>" maxlength="20" placeholder="e.g. 919876543210"></label>
    </div>
    <label class="f"><span>Address</span><input name="store_address" value="<?= $v('store_address') ?>" maxlength="250"></label>
    <div class="row2">
      <label class="f"><span>Facebook URL</span><input type="url" name="social_facebook" value="<?= $v('social_facebook') ?>"></label>
      <label class="f"><span>Instagram URL</span><input type="url" name="social_instagram" value="<?= $v('social_instagram') ?>"></label>
    </div>
    <div class="row2">
      <label class="f"><span>X / Twitter URL</span><input type="url" name="social_twitter" value="<?= $v('social_twitter') ?>"></label>
      <label class="f"><span>YouTube URL</span><input type="url" name="social_youtube" value="<?= $v('social_youtube') ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="order_email_notify" value="1" <?= ($s['order_email_notify'] ?? '1') === '1' ? 'checked' : '' ?>> Email me when a new order arrives</label>
    <button class="btn" type="submit" name="_section" value="store">Save</button>
  </section>

  <section class="panel" id="payments">
    <h2>Payments</h2>
    <div class="status-line">
      <?php if (rzp_enabled()): ?>
        <span class="pill pill-delivered">Razorpay active · <?= rzp_test_mode() ? 'TEST mode' : 'LIVE' ?></span>
      <?php else: ?>
        <span class="pill pill-cancelled">Razorpay not active</span>
      <?php endif; ?>
    </div>
    <ol class="steps">
      <li>Sign up / log in at <a href="https://dashboard.razorpay.com" target="_blank" rel="noopener">dashboard.razorpay.com</a> and complete KYC to accept real payments.</li>
      <li>Go to <b>Account & Settings → API Keys</b>, generate a key, and paste the Key ID and Key Secret below. Use <code>rzp_test_…</code> keys to try it out first.</li>
      <li>Go to <b>Account & Settings → Webhooks → Add new webhook</b>. URL: <code class="copy" data-copy><?= e(abs_url('webhook.php')) ?></code> — choose a secret, tick <b>payment.captured</b>, <b>order.paid</b> and <b>payment.failed</b>, and paste the same secret below.</li>
    </ol>
    <label class="check"><input type="checkbox" name="enable_razorpay" value="1" <?= ($s['enable_razorpay'] ?? '1') === '1' ? 'checked' : '' ?>> Accept online payments (UPI, cards, netbanking, wallets)</label>
    <label class="f"><span>Key ID</span><input name="razorpay_key_id" value="<?= $v('razorpay_key_id') ?>" placeholder="rzp_test_xxxxxxxx or rzp_live_xxxxxxxx" autocomplete="off"></label>
    <div class="row2">
      <label class="f"><span>Key Secret <?= !empty($s['razorpay_key_secret']) ? '<em class="ok">✓ saved</em>' : '' ?></span><input type="password" name="razorpay_key_secret" placeholder="<?= !empty($s['razorpay_key_secret']) ? '•••••••• (leave blank to keep)' : 'Paste Key Secret' ?>" autocomplete="new-password"></label>
      <label class="f"><span>Webhook Secret <?= !empty($s['razorpay_webhook_secret']) ? '<em class="ok">✓ saved</em>' : '' ?></span><input type="password" name="razorpay_webhook_secret" placeholder="<?= !empty($s['razorpay_webhook_secret']) ? '•••••••• (leave blank to keep)' : 'Paste webhook secret' ?>" autocomplete="new-password"></label>
    </div>
    <h3>Cash on delivery</h3>
    <label class="check"><input type="checkbox" name="enable_cod" value="1" <?= ($s['enable_cod'] ?? '0') === '1' ? 'checked' : '' ?>> Offer cash on delivery</label>
    <label class="f"><span>Maximum order value for COD (₹, 0 = no limit)</span><input type="number" name="cod_max_amount" min="0" step="1" value="<?= $v('cod_max_amount') ?>"></label>
    <div class="btn-row">
      <button class="btn" type="submit" name="_section" value="payments">Save</button>
    </div>
  </section>

  <section class="panel" id="delivery">
    <h2>Delivery charges</h2>
    <div class="row2">
      <label class="f"><span>Delivery fee (₹)</span><input type="number" name="shipping_fee" min="0" step="1" value="<?= $v('shipping_fee') ?>"></label>
      <label class="f"><span>Free delivery for orders above (₹, 0 = never)</span><input type="number" name="free_shipping_over" min="0" step="1" value="<?= $v('free_shipping_over') ?>"></label>
    </div>
    <button class="btn" type="submit" name="_section" value="delivery">Save</button>
  </section>

  <section class="panel" id="stock">
    <h2>Stock & live updates</h2>
    <div class="row2">
      <label class="f"><span>Hold items for unpaid online orders (minutes)</span><input type="number" name="reservation_minutes" min="5" max="120" value="<?= $v('reservation_minutes') ?>"></label>
      <label class="f"><span>Default “Only X left” threshold</span><input type="number" name="low_stock_default" min="0" value="<?= $v('low_stock_default') ?>"></label>
    </div>
    <label class="f"><span>Refresh stock on customers' screens every (seconds)</span><input type="number" name="stock_poll_seconds" min="5" max="120" value="<?= $v('stock_poll_seconds') ?>"></label>
    <button class="btn" type="submit" name="_section" value="stock">Save</button>
  </section>

  <section class="panel" id="pages">
    <h2>About page & policies</h2>
    <p class="help">Razorpay requires Terms, Privacy, Refund and Shipping policies on your website. Sensible templates are shown automatically until you write your own. Format: start a line with <code>## </code> for a heading and <code>- </code> for a bullet.</p>
    <label class="f"><span>About us</span><textarea name="about_text" rows="5"><?= $v('about_text') ?></textarea></label>
    <?php foreach (['policy_terms' => 'Terms & conditions', 'policy_privacy' => 'Privacy policy', 'policy_refund' => 'Cancellation & refund policy', 'policy_shipping' => 'Shipping & delivery policy'] as $k => $label): ?>
      <label class="f"><span><?= e($label) ?> <a href="<?= e(url('policy.php?p=' . substr($k, 7))) ?>" target="_blank" rel="noopener">view ↗</a></span><textarea name="<?= $k ?>" rows="5" placeholder="Leave empty to use the built-in template"><?= $v($k) ?></textarea></label>
    <?php endforeach; ?>
    <button class="btn" type="submit" name="_section" value="pages">Save</button>
  </section>
</form>

<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="action" value="test_rzp">
  <h2>Test Razorpay connection</h2>
  <p class="help">Checks that your saved keys work. Save the keys above first.</p>
  <button class="btn btn-ghost" type="submit" <?= rzp_key_id() && rzp_key_secret() ? '' : 'disabled' ?>>Run test</button>
</form>
<?php include __DIR__ . '/_bottom.php'; ?>
