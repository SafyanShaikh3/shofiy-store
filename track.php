<?php
require __DIR__ . '/includes/bootstrap.php';

$error = '';
if (is_post()) {
    csrf_check();
    $no = strtoupper(trim((string)post('order_no')));
    $contact = strtolower(trim((string)post('contact')));
    $ident = 'track:' . client_ip();
    if (too_many_attempts($ident, 10, 15)) {
        $error = 'Too many attempts. Please wait a few minutes.';
    } else {
        $o = q_one('SELECT * FROM orders WHERE order_no = ?', [$no]);
        $phoneMatch = $o && substr(preg_replace('/\D/', '', $o['phone']), -10) === substr(preg_replace('/\D/', '', $contact), -10) && strlen(preg_replace('/\D/', '', $contact)) >= 10;
        if ($o && (strtolower($o['email']) === $contact || $phoneMatch)) {
            clear_attempts($ident);
            redirect(order_view_url($o));
        }
        record_attempt($ident);
        $error = 'We couldn\'t find an order with those details.';
    }
}
$page_title = 'Track your order';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="card form-card">
      <h1>Track your order</h1>
      <p class="muted">Enter your order number (e.g. EW261016-AB2CD) and the email or mobile number used at checkout.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label class="field"><span>Order number</span><input name="order_no" required maxlength="32" value="<?= e(post('order_no')) ?>" autocapitalize="characters"></label>
        <label class="field"><span>Email or mobile</span><input name="contact" required maxlength="120" value="<?= e(post('contact')) ?>"></label>
        <button class="btn btn-lg btn-block" type="submit">Find my order</button>
      </form>
      <?php if (current_user()): ?><p class="center small"><a href="<?= e(url('orders.php')) ?>">See all my orders</a></p><?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
