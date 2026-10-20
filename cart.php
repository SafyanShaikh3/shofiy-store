<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$lines = cart_lines();
$t = cart_totals($lines);
$freeOver = (float)setting('free_shipping_over', '0');

$page_title = 'Your cart';
$body_class = 'page-cart';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="page-head"><h1>Your cart</h1></div>

    <?php if (!$lines): ?>
      <div class="empty-state">
        <?= icon('cart', 44) ?>
        <h2>Your cart is empty</h2>
        <p class="muted">Find something you love — today's deals are a good place to start.</p>
        <a class="btn" href="<?= e(url('shop.php?deals=1')) ?>">See today's deals</a>
      </div>
    <?php else: ?>
    <div class="cart-layout">
      <div class="cart-lines">
        <?php foreach ($lines as $l): $p = $l['product']; $id = (int)$p['id']; ?>
        <div class="cart-line<?= $l['problem'] ? ' has-problem' : '' ?>" data-line="<?= $id ?>" data-product="<?= $id ?>">
          <a href="<?= e(product_url($p)) ?>" class="cart-img"><img src="<?= e(upload_url($p['image_1'])) ?>" alt="" loading="lazy"></a>
          <div class="cart-info">
            <a class="cart-name" href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a>
            <?= render_price($p, 'price price-sm') ?>
            <?= render_stock_badge($p) ?>
            <p class="line-problem" data-line-problem><?= e($l['problem'] ?? '') ?></p>
            <div class="cart-actions">
              <form method="post" action="<?= e(url('cart_action.php')) ?>" class="qty-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="set"><input type="hidden" name="product_id" value="<?= $id ?>">
                <div class="qty qty-sm" data-qty data-autoupdate>
                  <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
                  <input type="number" name="qty" value="<?= $l['qty'] ?>" min="0" max="<?= max(1, $l['max']) ?>" inputmode="numeric" aria-label="Quantity" data-max-for="<?= $id ?>">
                  <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                </div>
                <noscript><button class="btn btn-sm" type="submit">Update</button></noscript>
              </form>
              <form method="post" action="<?= e(url('cart_action.php')) ?>" class="remove-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= $id ?>">
                <button class="link-btn danger" type="submit"><?= icon('trash', 16) ?> Remove</button>
              </form>
            </div>
          </div>
          <div class="cart-line-total" data-line-total><?= e(money($l['line_total'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <aside class="summary card">
        <h2>Order summary</h2>
        <dl>
          <div><dt>Subtotal (<span data-cart-items><?= $t['count'] ?></span> items)</dt><dd data-subtotal><?= e(money($t['subtotal'])) ?></dd></div>
          <div><dt>Delivery</dt><dd data-shipping><?= $t['shipping'] > 0 ? e(money($t['shipping'])) : 'Free' ?></dd></div>
          <div class="total"><dt>Total</dt><dd data-total><?= e(money($t['total'])) ?></dd></div>
        </dl>
        <?php if ($freeOver > 0 && $t['subtotal'] < $freeOver): ?>
          <p class="hint">Add <?= e(money($freeOver - $t['subtotal'])) ?> more for free delivery.</p>
        <?php endif; ?>
        <p class="alert alert-error" data-cart-problem <?= $t['problems'] ? '' : 'hidden' ?>>Some items need attention before checkout.</p>
        <a class="btn btn-lg btn-block <?= $t['problems'] ? 'is-disabled' : '' ?>" href="<?= e(url('checkout.php')) ?>" data-checkout-btn <?= $t['problems'] ? 'aria-disabled="true"' : '' ?>>Proceed to checkout</a>
        <p class="secure-note"><?= icon('lock', 14) ?> Secure checkout with Razorpay</p>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
