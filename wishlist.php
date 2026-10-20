<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    $user = current_user();
    if (!$user) {
        if (wants_json()) json_out(['ok' => false, 'login' => url('login.php'), 'msg' => 'Please log in to save items.'], 401);
        flash('info', 'Please log in to save items to your wishlist.');
        redirect('login.php?next=' . urlencode(url('wishlist.php')));
    }
    $pid = (int)post('product_id');
    $exists = q_one('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?', [$user['id'], $pid]);
    $action = post('action', 'toggle');
    if ($exists && in_array($action, ['toggle', 'remove'], true)) {
        q('DELETE FROM wishlist WHERE id = ?', [$exists['id']]);
        $res = ['ok' => true, 'on' => false, 'msg' => 'Removed from wishlist'];
    } elseif (!$exists && $action !== 'remove' && q_one('SELECT id FROM products WHERE id = ?', [$pid])) {
        q('INSERT INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, ?)', [$user['id'], $pid, now()]);
        $res = ['ok' => true, 'on' => true, 'msg' => 'Saved to wishlist'];
    } else {
        $res = ['ok' => true, 'on' => (bool)$exists, 'msg' => 'Wishlist updated'];
    }
    if (wants_json()) json_out($res);
    flash('success', $res['msg']);
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($back && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '') ? $back : url('wishlist.php'));
}

$user = require_user();
$products = q(product_select_sql() . ' JOIN wishlist w ON w.product_id = p.id WHERE w.user_id = ? ORDER BY w.id DESC', [$user['id']])->fetchAll();
$wish = array_map(fn($p) => (int)$p['id'], $products);

$page_title = 'Wishlist';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="page-head"><h1>Your wishlist</h1></div>
    <?php if ($products): ?>
      <div class="grid products-grid">
        <?php foreach ($products as $p) echo render_product_card($p, $wish); ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><?= icon('heart', 44) ?><h2>No saved items</h2><p class="muted">Tap the heart on any product to save it here.</p><a class="btn" href="<?= e(url('shop.php')) ?>">Browse products</a></div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
