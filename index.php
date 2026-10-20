<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$banners  = q('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$cats     = q('SELECT c.*, (SELECT image_1 FROM products p WHERE p.category_id = c.id AND p.is_active = 1 ORDER BY p.id LIMIT 1) AS fallback_img
               FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name')->fetchAll();
$deals    = q(product_select_sql() . ' WHERE p.is_active = 1 AND p.special_price IS NOT NULL AND p.special_ends_at > ? AND p.special_price < p.price
               ORDER BY p.special_ends_at ASC LIMIT 8', [now()])->fetchAll();
$featured = q(product_select_sql() . ' WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.updated_at DESC LIMIT 8')->fetchAll();
$latest   = q(product_select_sql() . ' WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT 8')->fetchAll();
$wish     = wishlist_ids();

$page_title = '';
include __DIR__ . '/includes/header.php';
?>

<?php if ($banners): ?>
<section class="hero" aria-label="Featured offers">
  <div class="wrap">
    <div class="slider" data-slider>
      <div class="slides">
        <?php foreach ($banners as $i => $b): ?>
        <div class="slide" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($banners) ?>">
          <div class="slide-text">
            <?php if ($b['subtitle']): ?><span class="eyebrow"><?= e($b['subtitle']) ?></span><?php endif; ?>
            <h2><?= e($b['title']) ?></h2>
            <?php if ($b['link_url']): ?><a class="btn btn-light" href="<?= e($b['link_url']) ?>"><?= e($b['button_text'] ?: 'Shop now') ?></a><?php endif; ?>
          </div>
          <?php if ($b['image']): ?><img src="<?= e(upload_url($b['image'], 'banners')) ?>" alt="" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($banners) > 1): ?>
      <div class="dots" role="tablist"><?php foreach ($banners as $i => $b): ?><button type="button" aria-label="Show slide <?= $i + 1 ?>" data-dot="<?= $i ?>"></button><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($cats): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><h2>Shop by category</h2><a href="<?= e(url('shop.php')) ?>">View all</a></div>
    <div class="cat-row">
      <?php foreach ($cats as $c): ?>
      <a class="cat" href="<?= e(url('shop.php?cat=' . urlencode($c['slug']))) ?>">
        <span class="cat-img"><img src="<?= e($c['image'] ? upload_url($c['image'], 'categories') : upload_url($c['fallback_img'])) ?>" alt="" loading="lazy"></span>
        <span><?= e($c['name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($deals): ?>
<section class="section deals">
  <div class="wrap">
    <div class="section-head">
      <h2><?= icon('bolt') ?> Today's deals</h2>
      <a href="<?= e(url('shop.php?deals=1')) ?>">See all deals</a>
    </div>
    <div class="grid products-grid scroll-sm">
      <?php foreach ($deals as $p) echo render_product_card($p, $wish); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><h2>Featured</h2><a href="<?= e(url('shop.php')) ?>">Shop all</a></div>
    <div class="grid products-grid">
      <?php foreach ($featured as $p) echo render_product_card($p, $wish); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap">
    <div class="section-head"><h2>New arrivals</h2><a href="<?= e(url('shop.php?sort=new')) ?>">See more</a></div>
    <?php if ($latest): ?>
    <div class="grid products-grid">
      <?php foreach ($latest as $p) echo render_product_card($p, $wish); ?>
    </div>
    <?php else: ?>
    <p class="empty">No products yet. Add some from the admin dashboard.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
