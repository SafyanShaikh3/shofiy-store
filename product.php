<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$id = (int)(get('id') ?: get('pid')); // pid = old quick_view.php links
$p = $id ? q_one(product_select_sql() . ' WHERE p.id = ? AND p.is_active = 1', [$id]) : null;
if (!$p) {
    http_response_code(404);
    $page_title = 'Product not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="wrap empty-state"><h1>Product not found</h1><p class="muted">It may have been removed.</p><a class="btn" href="' . e(url('shop.php')) . '">Continue shopping</a></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$images = array_values(array_filter([$p['image_1'], $p['image_2'], $p['image_3']]));
if (!$images) $images = [null];
$st = stock_state($p);
$max = max_orderable($p);
$wish = wishlist_ids();
$inWish = in_array((int)$p['id'], $wish, true);
$related = $p['category_id']
    ? q(product_select_sql() . ' WHERE p.is_active = 1 AND p.category_id = ? AND p.id <> ? ORDER BY RAND() LIMIT 4', [$p['category_id'], $p['id']])->fetchAll()
    : [];

$page_title = $p['name'];
$page_desc = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$p['details'])), 0, 155);
$og_image = abs_url('uploads/products/' . ($p['image_1'] ?? ''));
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <?= icon('chevron', 14) ?>
      <?php if ($p['category_slug']): ?><a href="<?= e(url('shop.php?cat=' . urlencode($p['category_slug']))) ?>"><?= e($p['category_name']) ?></a> <?= icon('chevron', 14) ?><?php endif; ?>
      <span><?= e($p['name']) ?></span>
    </nav>

    <div class="pdp" data-product="<?= (int)$p['id'] ?>">
      <div class="gallery">
        <div class="gallery-main"><img src="<?= e(upload_url($images[0])) ?>" alt="<?= e($p['name']) ?>" id="mainImg" width="600" height="600"></div>
        <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-thumb="<?= e(upload_url($img)) ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e(upload_url($img)) ?>" alt=""></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="pdp-info">
        <?php if ($p['category_name']): ?><p class="eyebrow"><?= e($p['category_name']) ?></p><?php endif; ?>
        <h1><?= e($p['name']) ?></h1>
        <?= render_price($p, 'price price-lg') ?>
        <p class="muted small">Inclusive of all taxes</p>
        <?php if (special_active($p)): ?>
          <div class="deal-box"><?= icon('bolt', 18) ?> <b>Deal of the day</b> — ends in <span class="countdown" data-ends="<?= e(date('c', strtotime($p['special_ends_at']))) ?>"><span>…</span></span></div>
        <?php endif; ?>

        <div class="stock-line">
          <?= render_stock_badge($p) ?>
          <span class="live-dot" title="Stock updates live">Live</span>
        </div>

        <form class="add-form pdp-form" method="post" action="<?= e(url('cart_action.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <div class="qty" data-qty>
            <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= max(1, $max) ?>" inputmode="numeric" aria-label="Quantity" data-max-for="<?= (int)$p['id'] ?>">
            <button type="button" data-step="1" aria-label="Increase quantity">+</button>
          </div>
          <button class="btn btn-lg" type="submit" data-add-btn="<?= (int)$p['id'] ?>" <?= $st['code'] === 'out' ? 'disabled' : '' ?>><?= $st['code'] === 'out' ? 'Sold out' : 'Add to cart' ?></button>
          <button class="btn btn-lg btn-dark" type="submit" name="buy_now" value="1" data-buy-now <?= $st['code'] === 'out' ? 'disabled' : '' ?>>Buy now</button>
        </form>

        <form class="wish-form inline" method="post" action="<?= e(url('wishlist.php')) ?>">
          <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="toggle">
          <button class="link-btn wish-btn-text<?= $inWish ? ' on' : '' ?>" type="submit" aria-pressed="<?= $inWish ? 'true' : 'false' ?>"><?= icon('heart', 18) ?> <span><?= $inWish ? 'Saved to wishlist' : 'Add to wishlist' ?></span></button>
        </form>

        <ul class="perks">
          <li><?= icon('truck', 18) ?> <?= setting('free_shipping_over') ? 'Free delivery on orders above ' . e(money(setting('free_shipping_over'))) : 'Fast delivery across India' ?></li>
          <li><?= icon('shield', 18) ?> Secure payment — UPI, cards, netbanking<?= setting('enable_cod') === '1' ? ', cash on delivery' : '' ?></li>
          <li><?= icon('refresh', 18) ?> <a href="<?= e(url('policy.php?p=refund')) ?>">Easy returns</a></li>
        </ul>

        <?php if (trim((string)$p['details']) !== ''): ?>
        <div class="details">
          <h2>About this product</h2>
          <?= nl2p((string)$p['details']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><h2>You may also like</h2></div>
    <div class="grid products-grid">
      <?php foreach ($related as $r) echo render_product_card($r, $wish); ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
