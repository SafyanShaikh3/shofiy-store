<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    if (post('action') === 'delete') {
        $id = (int)post('id');
        q('UPDATE orders SET user_id = NULL WHERE user_id = ?', [$id]); // keep order history
        q('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', 'Customer account deleted (their past orders are kept).');
    }
    redirect('admin/customers.php');
}

$qText = mb_substr(get('q'), 0, 80);
$params = [];
$w = '1=1';
if ($qText !== '') { $w = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $like = '%' . $qText . '%'; $params = [$like, $like, $like]; }
$total = (int)q_val("SELECT COUNT(*) FROM users u WHERE $w", $params);
$pg = paginate($total, 40, (int)get('page', '1'));
$users = q("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.order_status NOT IN ('pending_payment','expired','cancelled')) AS orders,
                  (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.order_status NOT IN ('pending_payment','expired','cancelled')) AS spent
           FROM users u WHERE $w ORDER BY u.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();

$page_title = 'Customers';
include __DIR__ . '/_top.php';
?>
<p class="help">Registered customers. Guests who check out without an account appear only in Orders.</p>
<form class="toolbar" method="get"><input type="search" name="q" value="<?= e($qText) ?>" placeholder="Name, email or phone"><button class="btn btn-sm btn-ghost">Search</button></form>
<?php if (!$users): ?><div class="panel empty">No customers yet.</div><?php else: ?>
<div class="panel no-pad">
  <table class="table">
    <thead><tr><th>Name</th><th>Contact</th><th>Orders</th><th>Spent</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><b><?= e($u['name']) ?></b></td>
        <td data-label="Contact"><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a><br><small class="muted"><?= e($u['phone']) ?></small></td>
        <td data-label="Orders"><a href="<?= e(url('admin/orders.php?q=' . urlencode($u['email']))) ?>"><?= (int)$u['orders'] ?></a></td>
        <td data-label="Spent"><?= e(money($u['spent'])) ?></td>
        <td data-label="Joined"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
        <td><form method="post" onsubmit="return confirm('Delete this customer account?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-ghost danger">Delete</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= render_pagination($pg) ?>
<?php endif; ?>
<?php include __DIR__ . '/_bottom.php'; ?>
