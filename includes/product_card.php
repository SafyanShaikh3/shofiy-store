<?php
declare(strict_types=1);

function product_url(array $p): string
{
    return url('product.php?id=' . (int)$p['id'] . '&s=' . urlencode($p['slug']));
}

function render_stock_badge(array $p): string
{
    $st = stock_state($p);
    return '<span class="stock stock-' . $st['code'] . '" data-stock-label="' . (int)$p['id'] . '">' . e($st['label']) . '</span>';
}

function render_price(array $p, string $class = 'price'): string
{
    $cmp = compare_price($p);
    $off = discount_percent($p);
    $h = '<div class="' . $class . '" data-price-for="' . (int)$p['id'] . '"><b>' . e(money(effective_price($p))) . '</b>';
    if ($cmp) $h .= ' <s>' . e(money($cmp)) . '</s>';
    if ($off > 0) $h .= ' <em>' . $off . '% off</em>';
    return $h . '</div>';
}

function render_product_card(array $p, array $wish = []): string
{
    $id = (int)$p['id'];
    $st = stock_state($p);
    $special = special_active($p);
    $inWish = in_array($id, $wish, true);
    ob_start(); ?>
<article class="card product-card<?= $st['code'] === 'out' ? ' is-out' : '' ?>" data-product="<?= $id ?>">
  <a class="card-img" href="<?= e(product_url($p)) ?>">
    <img src="<?= e(upload_url($p['image_1'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="300" height="300">
    <?php if ($special): ?><span class="tag tag-deal"><?= icon('bolt', 13) ?> Deal</span>
    <?php elseif (discount_percent($p) > 0): ?><span class="tag"><?= discount_percent($p) ?>% off</span><?php endif; ?>
  </a>
  <form class="wish-form" method="post" action="<?= e(url('wishlist.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle">
    <button class="wish-btn<?= $inWish ? ' on' : '' ?>" type="submit" aria-label="<?= $inWish ? 'Remove from wishlist' : 'Add to wishlist' ?>" aria-pressed="<?= $inWish ? 'true' : 'false' ?>"><?= icon('heart', 20) ?></button>
  </form>
  <div class="card-body">
    <a class="card-title" href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a>
    <?= render_price($p) ?>
    <?php if ($special): ?><div class="countdown" data-ends="<?= e(date('c', strtotime($p['special_ends_at']))) ?>"><?= icon('clock', 14) ?> <span>Ends soon</span></div><?php endif; ?>
    <?= render_stock_badge($p) ?>
    <form class="add-form" method="post" action="<?= e(url('cart_action.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $id ?>"><input type="hidden" name="qty" value="1">
      <button class="btn btn-block" type="submit" data-add-btn="<?= $id ?>" <?= $st['code'] === 'out' ? 'disabled' : '' ?>><?= $st['code'] === 'out' ? 'Sold out' : 'Add to cart' ?></button>
    </form>
  </div>
</article>
<?php
    return (string)ob_get_clean();
}

/** Common SELECT for listing products with category info */
function product_select_sql(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id';
}
