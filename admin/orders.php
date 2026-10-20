<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
release_expired_reservations();

$status = get('status');
$pay = get('pay');
$qText = mb_substr(get('q'), 0, 80);
$showAbandoned = get('abandoned') === '1';

$where = [$showAbandoned ? "o.order_status IN ('pending_payment','expired')" : "o.order_status NOT IN ('pending_payment','expired')"];
$params = [];
if ($status !== '' && !$showAbandoned) { $where[] = 'o.order_status = ?'; $params[] = $status; }
if ($pay !== '') { $where[] = 'o.payment_status = ?'; $params[] = $pay; }
if ($qText !== '') {
    $where[] = '(o.order_no LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ? OR o.email LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qText) . '%';
    array_push($params, $like, $like, $like, $like);
}
$w = implode(' AND ', $where);

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Customer', 'Phone', 'Email', 'Address', 'City', 'State', 'PIN', 'Items', 'Subtotal', 'Shipping', 'Total', 'Payment method', 'Payment status', 'Order status', 'Razorpay payment']);
    $rows = q("SELECT o.*, (SELECT GROUP_CONCAT(CONCAT(i.name, ' x', i.qty) SEPARATOR '; ') FROM order_items i WHERE i.order_id = o.id) AS items FROM orders o WHERE $w ORDER BY o.id DESC", $params);
    while ($o = $rows->fetch()) {
        $safe = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v; // avoid spreadsheet formula injection
        fputcsv($out, array_map($safe, [$o['order_no'], $o['created_at'], $o['customer_name'], $o['phone'], $o['email'], trim($o['address_line1'] . ' ' . $o['address_line2']), $o['city'], $o['state'], $o['pincode'],
            $o['items'], $o['subtotal'], $o['shipping_fee'], $o['total'], $o['payment_method'], $o['payment_status'], $o['order_status'], $o['razorpay_payment_id']]));
    }
    exit;
}

$total = (int)q_val("SELECT COUNT(*) FROM orders o WHERE $w", $params);
$pg = paginate($total, 30, (int)get('page', '1'));
$orders = q("SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items i WHERE i.order_id = o.id) AS units FROM orders o WHERE $w ORDER BY o.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();
$counts = [];
foreach (q("SELECT order_status, COUNT(*) n FROM orders GROUP BY order_status")->fetchAll() as $r) $counts[$r['order_status']] = (int)$r['n'];

$page_title = 'Orders';
$exportQ = $_GET; $exportQ['export'] = 'csv';
$top_actions = '<a class="btn btn-sm btn-ghost" href="?' . e(http_build_query($exportQ)) . '">Export CSV</a>';
include __DIR__ . '/_top.php';
?>
<nav class="status-tabs">
  <a href="<?= e(url('admin/orders.php')) ?>" class="<?= $status === '' && !$showAbandoned ? 'active' : '' ?>">All</a>
  <?php foreach (['placed' => 'New', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $label): ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $label ?> <small><?= $counts[$k] ?? 0 ?></small></a>
  <?php endforeach; ?>
  <a href="?abandoned=1" class="<?= $showAbandoned ? 'active' : '' ?>" title="Checkouts where payment was not completed">Unpaid attempts <small><?= ($counts['pending_payment'] ?? 0) + ($counts['expired'] ?? 0) ?></small></a>
</nav>

<form class="toolbar" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <?php if ($showAbandoned): ?><input type="hidden" name="abandoned" value="1"><?php endif; ?>
  <input type="search" name="q" value="<?= e($qText) ?>" placeholder="Order no., name, phone or email">
  <select name="pay" onchange="this.form.submit()">
    <option value="">Any payment</option>
    <?php foreach (['paid' => 'Paid', 'cod' => 'Cash on delivery', 'pending' => 'Pending', 'refunded' => 'Refunded'] as $k => $v): ?><option value="<?= $k ?>" <?= $pay === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-sm btn-ghost" type="submit">Search</button>
</form>

<?php if ($showAbandoned): ?><p class="help">These customers started an online payment but didn't finish. Their items were automatically put back in stock. You may want to call them.</p><?php endif; ?>

<?php if (!$orders): ?>
  <div class="panel empty">No orders here yet.</div>
<?php else: ?>
<div class="panel no-pad">
  <table class="table orders-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): $link = url('admin/order_view.php?id=' . $o['id']); ?>
      <tr class="clickable" onclick="location.href='<?= e($link) ?>'">
        <td><a href="<?= e($link) ?>"><b><?= e($o['order_no']) ?></b></a><br><small class="muted"><?= e(date('d M, h:i A', strtotime($o['created_at']))) ?></small></td>
        <td data-label="Customer"><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['phone']) ?> · <?= e($o['city']) ?></small></td>
        <td data-label="Items"><?= (int)$o['units'] ?></td>
        <td data-label="Total"><b><?= e(money($o['total'])) ?></b></td>
        <td data-label="Payment"><span class="pill pill-pay-<?= e($o['payment_status']) ?>"><?= e(payment_status_label($o['payment_status'])) ?></span></td>
        <td data-label="Status"><span class="pill pill-<?= e($o['order_status']) ?>"><?= e(order_status_label($o['order_status'])) ?></span><?= $o['admin_note'] && str_contains($o['admin_note'], 'ATTENTION') ? ' <span class="pill pill-cancelled">!</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= render_pagination($pg) ?>
<?php endif; ?>
<?php include __DIR__ . '/_bottom.php'; ?>
