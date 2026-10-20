<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = (int)(get('id') ?: post('id'));
$o = q_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }

$statuses = ['placed' => 'Order placed (new)', 'processing' => 'Processing / packing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$payStatuses = ['pending' => 'Pending', 'paid' => 'Paid', 'cod' => 'Cash on delivery (to collect)', 'refunded' => 'Refunded', 'failed' => 'Failed'];

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'update') {
        $st = post('order_status');
        $ps = post('payment_status');
        $note = mb_substr((string)post('admin_note'), 0, 500);
        if (!isset($statuses[$st]) && $st !== $o['order_status']) $st = $o['order_status'];
        if (!isset($payStatuses[$ps])) $ps = $o['payment_status'];
        $paidAt = ($ps === 'paid' && !$o['paid_at']) ? now() : $o['paid_at'];
        q('UPDATE orders SET order_status = ?, payment_status = ?, admin_note = ?, paid_at = ? WHERE id = ?',
          [$st, $ps, $note ?: null, $paidAt, $id]);
        if ($st !== $o['order_status'] && post('notify') && in_array($st, ['processing', 'shipped', 'delivered'], true)) {
            $msg = ['processing' => 'is being packed', 'shipped' => 'has been shipped', 'delivered' => 'has been delivered'][$st];
            send_mail($o['email'], 'Your order ' . $o['order_no'] . ' ' . $msg,
                '<h2>Good news, ' . e($o['customer_name']) . '!</h2><p>Your order <b>' . e($o['order_no']) . '</b> ' . $msg . '.</p>'
                . ($note && post('note_to_customer') ? '<p>' . nl2br(e($note)) . '</p>' : '')
                . '<p><a href="' . e(abs_url(order_view_url($o))) . '">View your order</a></p>');
        }
        flash('success', 'Order updated.');
    } elseif ($action === 'cancel') {
        cancel_order($id, 'cancelled', true);
        flash('success', 'Order cancelled and items returned to stock.' . ($o['payment_status'] === 'paid' ? ' Remember to refund the payment from your Razorpay dashboard, then set payment to “Refunded”.' : ''));
    }
    redirect('admin/order_view.php?id=' . $id);
}

$items = order_items($id);
$waPhone = preg_replace('/\D/', '', $o['phone']);
if (strlen($waPhone) === 10) $waPhone = '91' . $waPhone;

$page_title = 'Order ' . $o['order_no'];
$top_actions = '<button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print packing slip</button>';
include __DIR__ . '/_top.php';
?>
<a class="back no-print" href="<?= e(url('admin/orders.php')) ?>">← All orders</a>
<?php if ($o['admin_note'] && str_contains($o['admin_note'], 'ATTENTION')): ?><div class="flash flash-error"><?= e($o['admin_note']) ?></div><?php endif; ?>

<div class="edit-grid">
  <div class="edit-main">
    <section class="panel print-area">
      <div class="panel-head">
        <h2><?= e($o['order_no']) ?></h2>
        <span class="muted"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></span>
      </div>
      <div class="order-meta">
        <span class="pill pill-<?= e($o['order_status']) ?>"><?= e(order_status_label($o['order_status'])) ?></span>
        <span class="pill pill-pay-<?= e($o['payment_status']) ?>"><?= e(payment_status_label($o['payment_status'])) ?></span>
        <span class="pill"><?= $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'Razorpay' ?></span>
      </div>
      <table class="table items">
        <thead><tr><th></th><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td class="thumb"><img src="<?= e(upload_url($it['image'])) ?>" alt=""></td>
            <td><?= $it['product_id'] ? '<a href="' . e(url('admin/product_edit.php?id=' . $it['product_id'])) . '">' . e($it['name']) . '</a>' : e($it['name']) ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td><?= e(money($it['unit_price'])) ?></td>
            <td><b><?= e(money($it['line_total'])) ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="r">Subtotal</td><td><?= e(money($o['subtotal'])) ?></td></tr>
          <tr><td colspan="4" class="r">Delivery</td><td><?= e(money($o['shipping_fee'])) ?></td></tr>
          <tr class="grand"><td colspan="4" class="r">Total</td><td><?= e(money($o['total'])) ?></td></tr>
        </tfoot>
      </table>
      <div class="row2 addr">
        <div>
          <h3>Ship to</h3>
          <p><b><?= e($o['customer_name']) ?></b><br><?= e($o['address_line1']) ?><?= $o['address_line2'] ? '<br>' . e($o['address_line2']) : '' ?><br><?= e($o['city'] . ', ' . $o['state'] . ' - ' . $o['pincode']) ?><br><?= e($o['country']) ?></p>
          <p><?= e($o['phone']) ?><br><?= e($o['email']) ?></p>
          <?php if ($o['notes']): ?><p><b>Customer note:</b> <?= e($o['notes']) ?></p><?php endif; ?>
        </div>
        <div class="no-print">
          <h3>Contact customer</h3>
          <p class="btn-row">
            <a class="btn btn-sm btn-ghost" href="tel:<?= e(clean_phone($o['phone'])) ?>">Call</a>
            <a class="btn btn-sm btn-ghost" href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode('Hi ' . $o['customer_name'] . ', about your order ' . $o['order_no'] . ' from ' . setting('store_name') . ': ') ?>" target="_blank" rel="noopener">WhatsApp</a>
            <a class="btn btn-sm btn-ghost" href="mailto:<?= e($o['email']) ?>?subject=<?= rawurlencode('Your order ' . $o['order_no']) ?>">Email</a>
          </p>
          <?php if ($o['razorpay_payment_id']): ?>
            <h3>Payment reference</h3>
            <p><code><?= e($o['razorpay_payment_id']) ?></code><br><small class="muted">Razorpay order <?= e($o['razorpay_order_id']) ?></small><?= $o['paid_at'] ? '<br><small class="muted">Paid ' . e(date('d M Y, h:i A', strtotime($o['paid_at']))) . '</small>' : '' ?></p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="edit-side no-print">
    <form method="post" class="panel">
      <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $id ?>">
      <h2>Update order</h2>
      <?php if (in_array($o['order_status'], ['pending_payment', 'expired', 'cancelled'], true)): ?>
        <p class="muted">Status: <b><?= e(order_status_label($o['order_status'])) ?></b></p>
        <input type="hidden" name="order_status" value="<?= e($o['order_status']) ?>">
      <?php else: ?>
      <label class="f"><span>Order status</span>
        <select name="order_status"><?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>" <?= $o['order_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <?php endif; ?>
      <label class="f"><span>Payment</span>
        <select name="payment_status"><?php foreach ($payStatuses as $k => $v): ?><option value="<?= $k ?>" <?= $o['payment_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label class="f"><span>Note (e.g. courier & tracking number)</span><textarea name="admin_note" rows="3" maxlength="500"><?= e($o['admin_note']) ?></textarea></label>
      <label class="check"><input type="checkbox" name="notify" value="1" checked> Email the customer about status changes</label>
      <label class="check"><input type="checkbox" name="note_to_customer" value="1"> Include the note in that email</label>
      <button class="btn btn-block" type="submit">Save</button>
    </form>
    <?php if (!in_array($o['order_status'], ['cancelled', 'expired', 'delivered'], true)): ?>
    <form method="post" class="panel" onsubmit="return confirm('Cancel this order and put the items back in stock?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $id ?>">
      <h2>Cancel order</h2>
      <p class="help">Items go back into stock.<?= $o['payment_status'] === 'paid' ? ' Refund the customer from your Razorpay dashboard.' : '' ?></p>
      <button class="btn btn-block btn-danger" type="submit">Cancel order</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
