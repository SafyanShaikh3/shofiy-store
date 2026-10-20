<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');
    $p = q_one('SELECT * FROM products WHERE id = ?', [$id]);
    $res = ['ok' => false, 'msg' => 'Product not found'];
    if ($p) {
        if ($action === 'quick') {
            $price = (float)post('price');
            $mrp = post('mrp') === '' ? null : (float)post('mrp');
            $stock = (int)post('stock');
            $active = post('is_active') ? 1 : 0;
            if ($price <= 0) $res = ['ok' => false, 'msg' => 'Price must be greater than 0'];
            elseif ($stock < 0) $res = ['ok' => false, 'msg' => 'Stock cannot be negative'];
            else {
                q('UPDATE products SET price = ?, mrp = ?, stock = ?, is_active = ? WHERE id = ?', [$price, $mrp, $stock, $active, $id]);
                $res = ['ok' => true, 'msg' => 'Saved “' . $p['name'] . '”'];
            }
        } elseif ($action === 'stock') {
            $stock = max(0, (int)post('stock'));
            q('UPDATE products SET stock = ? WHERE id = ?', [$stock, $id]);
            $res = ['ok' => true, 'msg' => 'Stock updated for “' . $p['name'] . '”'];
        } elseif ($action === 'delete') {
            $used = (int)q_val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);
            if ($used) {
                // Keep history intact: hide instead of deleting
                q('UPDATE products SET is_active = 0 WHERE id = ?', [$id]);
                $res = ['ok' => true, 'msg' => '“' . $p['name'] . '” has past orders, so it was hidden instead of deleted.'];
            } else {
                q('DELETE FROM products WHERE id = ?', [$id]);
                foreach (['image_1', 'image_2', 'image_3'] as $f) delete_upload($p[$f], 'products');
                $res = ['ok' => true, 'msg' => 'Deleted “' . $p['name'] . '”'];
            }
        }
    }
    if (wants_json()) json_out($res, $res['ok'] ? 200 : 422);
    flash($res['ok'] ? 'success' : 'error', $res['msg']);
    redirect($action === 'delete' ? url('admin/products.php') : ($_SERVER['HTTP_REFERER'] ?? url('admin/products.php')));
}

$qText = mb_substr(get('q'), 0, 80);
$cat = (int)get('cat');
$filter = get('filter');
$where = ['1=1']; $params = [];
if ($qText !== '') { $where[] = 'p.name LIKE ?'; $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qText) . '%'; }
if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
if ($filter === 'low') $where[] = 'p.track_stock = 1 AND p.stock <= p.low_stock_threshold';
if ($filter === 'out') $where[] = 'p.track_stock = 1 AND p.stock <= 0';
if ($filter === 'hidden') $where[] = 'p.is_active = 0';
if ($filter === 'deals') { $where[] = 'p.special_price IS NOT NULL AND p.special_ends_at > ?'; $params[] = now(); }
$w = implode(' AND ', $where);
$total = (int)q_val("SELECT COUNT(*) FROM products p WHERE $w", $params);
$pg = paginate($total, 50, (int)get('page', '1'));
$products = q(product_select_sql() . " WHERE $w ORDER BY p.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();
$cats = q('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();

$page_title = 'Products & stock';
$top_actions = '<a class="btn btn-sm" href="' . e(url('admin/product_edit.php')) . '">+ Add product</a>';
include __DIR__ . '/_top.php';
?>
<p class="help">Change a price or stock number and press <b>Save</b> on that row — customers see the change on the website within seconds. Use <b>Edit</b> for photos, description and deals.</p>

<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($qText) ?>" placeholder="Search products…">
  <select name="cat" onchange="this.form.submit()">
    <option value="">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="filter" onchange="this.form.submit()">
    <?php foreach (['' => 'All products', 'low' => 'Low stock', 'out' => 'Out of stock', 'deals' => 'On special', 'hidden' => 'Hidden'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm btn-ghost" type="submit">Filter</button>
</form>

<?php if (!$products): ?>
  <div class="panel empty">No products found. <a href="<?= e(url('admin/product_edit.php')) ?>">Add your first product</a>.</div>
<?php else: ?>
<div class="panel no-pad">
  <table class="table product-table">
    <thead><tr><th></th><th>Product</th><th>Price (₹)</th><th>MRP (₹)</th><th>Stock</th><th>Visible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $st = stock_state($p); $fid = 'qf' . $p['id']; ?>
      <tr>
        <td class="thumb"><img src="<?= e(upload_url($p['image_1'])) ?>" alt=""></td>
        <td class="pname">
          <a href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
          <small class="muted"><?= e($p['category_name'] ?? 'No category') ?></small>
          <span class="badges">
            <span class="stock stock-<?= $st['code'] ?>"><?= e($st['label']) ?></span>
            <?php if (special_active($p)): ?><span class="pill pill-deal">Special <?= e(money($p['special_price'])) ?> until <?= e(date('d M, h:i A', strtotime($p['special_ends_at']))) ?></span><?php endif; ?>
          </span>
        </td>
        <td data-label="Price"><input form="<?= $fid ?>" type="number" name="price" step="0.01" min="0.01" value="<?= e((float)$p['price']) ?>" required></td>
        <td data-label="MRP"><input form="<?= $fid ?>" type="number" name="mrp" step="0.01" min="0" value="<?= $p['mrp'] !== null ? e((float)$p['mrp']) : '' ?>" placeholder="—"></td>
        <td data-label="Stock"><input form="<?= $fid ?>" type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>" <?= $p['track_stock'] ? '' : 'title="Stock not tracked"' ?>></td>
        <td data-label="Visible"><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>><span></span></label></td>
        <td class="row-actions">
          <form id="<?= $fid ?>" method="post" data-ajax><?= csrf_field() ?><input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"></form>
          <button class="btn btn-sm" type="submit" form="<?= $fid ?>">Save</button>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>">Edit</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= render_pagination($pg) ?>
<?php endif; ?>
<?php include __DIR__ . '/_bottom.php'; ?>
