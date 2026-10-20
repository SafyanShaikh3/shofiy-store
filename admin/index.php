<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
release_expired_reservations();

$today = date('Y-m-d 00:00:00');
$month = date('Y-m-01 00:00:00');
$valid = "order_status NOT IN ('pending_payment','expired','cancelled')";

$salesToday  = (float)q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND created_at >= ?", [$today]);
$ordersToday = (int)q_val("SELECT COUNT(*) FROM orders WHERE $valid AND created_at >= ?", [$today]);
$salesMonth  = (float)q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND created_at >= ?", [$month]);
$toProcess   = (int)q_val("SELECT COUNT(*) FROM orders WHERE order_status = 'placed'");
$codDue      = (float)q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'cod' AND order_status NOT IN ('cancelled','expired')");
$lowStock    = q("SELECT id, name, stock, low_stock_threshold, image_1 FROM products WHERE is_active = 1 AND track_stock = 1 AND stock <= low_stock_threshold ORDER BY stock ASC, name LIMIT 12")->fetchAll();
$liveDeals   = (int)q_val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND special_price IS NOT NULL AND special_ends_at > ? AND special_price < price', [now()]);
$recent      = q("SELECT * FROM orders WHERE order_status <> 'expired' ORDER BY id DESC LIMIT 8")->fetchAll();
$attention   = q("SELECT id, order_no FROM orders WHERE admin_note LIKE 'ATTENTION%' OR admin_note LIKE '% ATTENTION%' ORDER BY id DESC LIMIT 5")->fetchAll();

$page_title = 'Dashboard';
$top_actions = '<a class="btn btn-sm" href="' . e(url('admin/product_edit.php')) . '">+ Add product</a>';
include __DIR__ . '/_top.php';
?>
<?php foreach ($attention as $a): ?>
  <div class="flash flash-error">Order <a href="<?= e(url('admin/order_view.php?id=' . $a['id'])) ?>"><?= e($a['order_no']) ?></a> needs your attention.</div>
<?php endforeach; ?>

<div class="stats">
  <a class="stat" href="<?= e(url('admin/orders.php')) ?>"><span>Sales today</span><b><?= e(money($salesToday)) ?></b><small><?= $ordersToday ?> order<?= $ordersToday === 1 ? '' : 's' ?></small></a>
  <a class="stat accent" href="<?= e(url('admin/orders.php?status=placed')) ?>"><span>New orders to process</span><b><?= $toProcess ?></b><small>Placed, not yet processed</small></a>
  <a class="stat" href="<?= e(url('admin/orders.php')) ?>"><span>This month</span><b><?= e(money($salesMonth)) ?></b><small><?= e(date('F Y')) ?></small></a>
  <a class="stat" href="<?= e(url('admin/orders.php?pay=cod')) ?>"><span>COD to collect</span><b><?= e(money($codDue)) ?></b><small>Cash on delivery</small></a>
</div>

<div class="quick">
  <a class="qa" href="<?= e(url('admin/specials.php')) ?>"><b>⚡ Today's specials</b><span><?= $liveDeals ?> live deal<?= $liveDeals === 1 ? '' : 's' ?> · set or change</span></a>
  <a class="qa" href="<?= e(url('admin/products.php')) ?>"><b>✎ Update prices & stock</b><span>Quick-edit all products in one table</span></a>
  <a class="qa" href="<?= e(url('admin/banners.php')) ?>"><b>🖼 Home banners</b><span>Change the homepage slides</span></a>
</div>

<div class="cols">
  <section class="panel">
    <div class="panel-head"><h2>Recent orders</h2><a href="<?= e(url('admin/orders.php')) ?>">View all</a></div>
    <?php if ($recent): ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr onclick="location.href='<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>'" class="clickable">
          <td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a><br><small class="muted"><?= e(time_ago($o['created_at'])) ?></small></td>
          <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['city']) ?></small></td>
          <td><?= e(money($o['total'])) ?><br><small class="muted"><?= e(payment_status_label($o['payment_status'])) ?></small></td>
          <td><span class="pill pill-<?= e($o['order_status']) ?>"><?= e(order_status_label($o['order_status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p class="muted">No orders yet. They'll show up here the moment a customer checks out.</p><?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Low stock</h2><a href="<?= e(url('admin/products.php?filter=low')) ?>">Manage</a></div>
    <?php if ($lowStock): ?>
    <ul class="low-list">
      <?php foreach ($lowStock as $p): ?>
      <li>
        <img src="<?= e(upload_url($p['image_1'])) ?>" alt="">
        <a href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
        <form method="post" action="<?= e(url('admin/products.php')) ?>" class="inline-stock" data-ajax>
          <?= csrf_field() ?><input type="hidden" name="action" value="stock"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="number" name="stock" value="<?= (int)$p['stock'] ?>" min="0" aria-label="Stock for <?= e($p['name']) ?>" class="<?= (int)$p['stock'] <= 0 ? 'zero' : '' ?>">
          <button class="btn btn-sm" type="submit">Save</button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="muted">All products are well stocked.</p><?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
