<?php
require __DIR__ . '/includes/bootstrap.php';

$o = q_one('SELECT * FROM orders WHERE order_no = ?', [get('no')]);
$user = current_user();
$allowed = $o && (hash_equals($o['access_token'], (string)get('t')) || ($user && (int)$o['user_id'] === (int)$user['id']));
if (!$allowed) {
    http_response_code(404);
    $page_title = 'Order not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="wrap empty-state"><h1>Order not found</h1><p class="muted">Check the link in your confirmation email, or use Track order.</p><a class="btn" href="' . e(url('track.php')) . '">Track order</a></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}
$items = order_items((int)$o['id']);
$isNew = get('new') === '1';

$steps = ['placed' => 'Order placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$keys = array_keys($steps);
$currentIdx = array_search($o['order_status'], $keys, true);

$page_title = 'Order ' . $o['order_no'];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <?php if ($isNew && in_array($o['order_status'], ['placed', 'processing'], true)): ?>
      <div class="success-hero">
        <div class="success-icon"><?= icon('check', 34) ?></div>
        <h1>Thank you, <?= e(explode(' ', $o['customer_name'])[0]) ?>!</h1>
        <p class="muted">Your order <b><?= e($o['order_no']) ?></b> is confirmed. A confirmation has been sent to <?= e($o['email']) ?>.</p>
      </div>
    <?php else: ?>
      <div class="page-head"><h1>Order <?= e($o['order_no']) ?></h1><p class="muted">Placed <?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></p></div>
    <?php endif; ?>

    <div class="card">
      <div class="order-meta">
        <span class="pill pill-<?= e($o['order_status']) ?>"><?= e(order_status_label($o['order_status'])) ?></span>
        <span class="pill pill-pay-<?= e($o['payment_status']) ?>"><?= e(payment_status_label($o['payment_status'])) ?></span>
      </div>

      <?php if ($currentIdx !== false): ?>
      <ol class="tracker">
        <?php foreach ($steps as $k => $label): $i = array_search($k, $keys, true); ?>
          <li class="<?= $i <= $currentIdx ? 'done' : '' ?><?= $i === $currentIdx ? ' current' : '' ?>"><span></span><?= e($label) ?></li>
        <?php endforeach; ?>
      </ol>
      <?php elseif ($o['order_status'] === 'pending_payment'): ?>
        <p class="alert alert-info">Payment not completed yet. <a href="<?= e(url('pay.php?no=' . urlencode($o['order_no']) . '&t=' . $o['access_token'])) ?>">Complete payment</a></p>
      <?php endif; ?>

      <ul class="mini-lines">
        <?php foreach ($items as $it): ?>
        <li><img src="<?= e(upload_url($it['image'])) ?>" alt=""><span><?= e($it['name']) ?> <small>× <?= (int)$it['qty'] ?> @ <?= e(money($it['unit_price'])) ?></small></span><b><?= e(money($it['line_total'])) ?></b></li>
        <?php endforeach; ?>
      </ul>
      <dl class="totals">
        <div><dt>Subtotal</dt><dd><?= e(money($o['subtotal'])) ?></dd></div>
        <div><dt>Delivery</dt><dd><?= (float)$o['shipping_fee'] > 0 ? e(money($o['shipping_fee'])) : 'Free' ?></dd></div>
        <div class="total"><dt>Total</dt><dd><?= e(money($o['total'])) ?></dd></div>
      </dl>
      <div class="two-col">
        <div>
          <h3>Delivery to</h3>
          <p><?= e($o['customer_name']) ?><br><?= e($o['address_line1']) ?><?= $o['address_line2'] ? '<br>' . e($o['address_line2']) : '' ?><br><?= e($o['city'] . ', ' . $o['state'] . ' ' . $o['pincode']) ?><br><?= e($o['phone']) ?></p>
        </div>
        <div>
          <h3>Payment</h3>
          <p><?= $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online (Razorpay)' ?><?= $o['razorpay_payment_id'] ? '<br><small class="muted">Ref: ' . e($o['razorpay_payment_id']) . '</small>' : '' ?></p>
        </div>
      </div>
    </div>
    <p class="center"><a class="btn btn-outline" href="<?= e(url('shop.php')) ?>">Continue shopping</a></p>
    <p class="center muted small">Questions? Call <?= e(setting('store_phone')) ?> or <a href="<?= e(url('contact.php')) ?>">message us</a> with your order number.</p>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
