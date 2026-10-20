<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_user();

$orders = q("SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
             FROM orders o WHERE o.user_id = ? AND o.order_status NOT IN ('expired') ORDER BY o.id DESC LIMIT 100", [$user['id']])->fetchAll();

$page_title = 'My orders';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="page-head"><h1>My orders</h1></div>
    <?php if (!$orders): ?>
      <div class="empty-state"><?= icon('box', 44) ?><h2>No orders yet</h2><a class="btn" href="<?= e(url('shop.php')) ?>">Start shopping</a></div>
    <?php else: ?>
      <div class="order-list">
      <?php foreach ($orders as $o): ?>
        <a class="card order-row" href="<?= e(url(order_view_url($o))) ?>">
          <div>
            <b><?= e($o['order_no']) ?></b>
            <span class="muted small"><?= e(date('d M Y', strtotime($o['created_at']))) ?> · <?= (int)$o['item_count'] ?> item<?= $o['item_count'] == 1 ? '' : 's' ?></span>
          </div>
          <div class="order-row-right">
            <b><?= e(money($o['total'])) ?></b>
            <span class="pill pill-<?= e($o['order_status']) ?>"><?= e(order_status_label($o['order_status'])) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
