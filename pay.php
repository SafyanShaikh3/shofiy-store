<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$o = q_one('SELECT * FROM orders WHERE order_no = ?', [get('no')]);
if (!$o || !hash_equals($o['access_token'], (string)get('t'))) {
    http_response_code(404);
    $page_title = 'Order not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="wrap empty-state"><h1>Order not found</h1><a class="btn" href="' . e(url('cart.php')) . '">Back to cart</a></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}
if ($o['payment_status'] === 'paid' || $o['payment_method'] === 'cod') redirect(order_view_url($o));

$canPay = $o['order_status'] === 'pending_payment' && $o['razorpay_order_id'] && rzp_enabled();
$items = order_items((int)$o['id']);
$brand = preg_match('/^#[0-9a-fA-F]{6}$/', setting('theme_color', '')) ? setting('theme_color') : '#2f6fed';

$page_title = 'Complete payment';
$body_class = 'page-pay';
if ($canPay) {
    $opts = [
        'key'         => rzp_key_id(),
        'amount'      => (int)round((float)$o['total'] * 100),
        'currency'    => 'INR',
        'name'        => setting('store_name'),
        'description' => 'Order ' . $o['order_no'],
        'order_id'    => $o['razorpay_order_id'],
        'prefill'     => ['name' => $o['customer_name'], 'email' => $o['email'], 'contact' => $o['phone']],
        'notes'       => ['order_no' => $o['order_no']],
        'theme'       => ['color' => $brand],
    ];
    $extra_scripts = '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>'
        . '<script>window.RZP_OPTS=' . json_encode($opts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
        . '<script src="' . e(asset('js/pay.js')) . '" defer></script>';
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="card pay-card">
      <?php if ($canPay): ?>
        <p class="eyebrow">Order <?= e($o['order_no']) ?></p>
        <h1>Complete your payment</h1>
        <p class="pay-amount"><?= e(money($o['total'])) ?></p>
        <p class="muted">Your items are reserved for <b class="countdown" data-ends="<?= e(date('c', strtotime((string)$o['reserved_until']))) ?>" data-expire-reload><span>…</span></b></p>
        <div class="alert alert-error" id="payError" hidden></div>
        <button class="btn btn-lg btn-block" type="button" id="payBtn"><?= icon('lock', 18) ?> Pay <?= e(money($o['total'])) ?> securely</button>
        <p class="secure-note">UPI · Cards · Netbanking · Wallets — processed by Razorpay. We never see your card details.</p>
        <?php if (rzp_test_mode()): ?><p class="alert alert-info">Test mode: no real money will be charged. Use Razorpay test cards/UPI.</p><?php endif; ?>

        <form id="verifyForm" method="post" action="<?= e(url('payment_verify.php')) ?>" hidden>
          <?= csrf_field() ?>
          <input type="hidden" name="order_no" value="<?= e($o['order_no']) ?>">
          <input type="hidden" name="t" value="<?= e($o['access_token']) ?>">
          <input type="hidden" name="razorpay_payment_id">
          <input type="hidden" name="razorpay_order_id">
          <input type="hidden" name="razorpay_signature">
        </form>
      <?php else: ?>
        <h1>Payment time ran out</h1>
        <p class="muted">We couldn't confirm payment for order <?= e($o['order_no']) ?> in time, so the items were released. Nothing was charged — if money was deducted, it will be confirmed automatically or refunded by Razorpay.</p>
        <a class="btn btn-lg btn-block" href="<?= e(url('checkout.php')) ?>">Try again</a>
      <?php endif; ?>

      <details class="pay-items">
        <summary><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> in this order</summary>
        <ul class="mini-lines">
          <?php foreach ($items as $it): ?>
          <li><img src="<?= e(upload_url($it['image'])) ?>" alt=""><span><?= e($it['name']) ?> <small>× <?= (int)$it['qty'] ?></small></span><b><?= e(money($it['line_total'])) ?></b></li>
          <?php endforeach; ?>
        </ul>
      </details>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
