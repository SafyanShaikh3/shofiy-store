<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$qText  = mb_substr(get('q'), 0, 80);
$catSlug = get('cat');
$deals  = get('deals') === '1';
$inStock = get('instock') === '1';
$sort   = get('sort', 'popular');
$page   = max(1, (int)get('page', '1'));

$where = ['p.is_active = 1'];
$params = [];
$cat = null;
if ($catSlug !== '') {
    $cat = q_one('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$catSlug]);
    if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat['id']; }
}
if ($qText !== '') {
    $where[] = '(p.name LIKE ? OR p.details LIKE ? OR c.name LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qText) . '%';
    array_push($params, $like, $like, $like);
}
if ($deals) {
    $where[] = 'p.special_price IS NOT NULL AND p.special_ends_at > ? AND p.special_price < p.price';
    $params[] = now();
}
if ($inStock) $where[] = '(p.track_stock = 0 OR p.stock > 0)';

$priceExpr = 'CASE WHEN p.special_price IS NOT NULL AND p.special_ends_at > ' . db()->quote(now()) . ' AND p.special_price < p.price THEN p.special_price ELSE p.price END';
$orderBy = match ($sort) {
    'price_asc'  => "$priceExpr ASC",
    'price_desc' => "$priceExpr DESC",
    'new'        => 'p.created_at DESC, p.id DESC',
    'name'       => 'p.name ASC',
    default      => '(p.track_stock = 1 AND p.stock <= 0) ASC, p.is_featured DESC, p.id DESC',
};

$whereSql = implode(' AND ', $where);
$total = (int)q_val("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereSql", $params);
$pg = paginate($total, 12, $page);
$products = q(product_select_sql() . " WHERE $whereSql ORDER BY $orderBy LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();
$cats = q('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$wish = wishlist_ids();

$heading = $deals ? "Today's deals" : ($cat['name'] ?? ($qText !== '' ? 'Results for “' . $qText . '”' : 'All products'));
$page_title = $heading;
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="page-head">
      <h1><?= e($heading) ?></h1>
      <p class="muted"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></p>
    </div>

    <form class="filters" method="get" action="">
      <?php if ($qText !== ''): ?><input type="hidden" name="q" value="<?= e($qText) ?>"><?php endif; ?>
      <label class="sr-only" for="f-cat">Category</label>
      <select id="f-cat" name="cat" data-autosubmit>
        <option value="">All categories</option>
        <?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <label class="sr-only" for="f-sort">Sort by</label>
      <select id="f-sort" name="sort" data-autosubmit>
        <?php foreach (['popular' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'new' => 'Newest', 'name' => 'Name A–Z'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <label class="chip"><input type="checkbox" name="deals" value="1" <?= $deals ? 'checked' : '' ?> data-autosubmit> Deals</label>
      <label class="chip"><input type="checkbox" name="instock" value="1" <?= $inStock ? 'checked' : '' ?> data-autosubmit> In stock only</label>
      <noscript><button class="btn btn-sm" type="submit">Apply</button></noscript>
    </form>

    <?php if ($products): ?>
      <div class="grid products-grid">
        <?php foreach ($products as $p) echo render_product_card($p, $wish); ?>
      </div>
      <?= render_pagination($pg) ?>
    <?php else: ?>
      <div class="empty-state">
        <?= icon('search', 40) ?>
        <h2>No products found</h2>
        <p class="muted">Try a different search or browse all products.</p>
        <a class="btn" href="<?= e(url('shop.php')) ?>">Browse all</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
