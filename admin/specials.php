<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

function ends_from_preset(string $preset, string $custom): ?string
{
    return match ($preset) {
        'today' => date('Y-m-d 23:59:59'),
        'tomorrow' => date('Y-m-d 23:59:59', strtotime('+1 day')),
        '24' => date('Y-m-d H:i:s', time() + 86400),
        '72' => date('Y-m-d H:i:s', time() + 3 * 86400),
        '168' => date('Y-m-d H:i:s', time() + 7 * 86400),
        default => $custom !== '' && strtotime($custom) ? date('Y-m-d H:i:s', strtotime($custom)) : null,
    };
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('product_id');
    $p = q_one('SELECT * FROM products WHERE id = ?', [$id]);
    if (!$p) { flash('error', 'Please choose a product.'); redirect('admin/specials.php'); }

    if ($action === 'set') {
        $mode = post('mode', 'price');
        $val = (float)post('value');
        $special = $mode === 'percent' ? round((float)$p['price'] * (1 - $val / 100), 2) : $val;
        $ends = ends_from_preset((string)post('preset'), (string)post('ends_custom'));
        if ($special <= 0 || $special >= (float)$p['price']) flash('error', 'The special price must be lower than the regular price (' . money($p['price']) . ').');
        elseif (!$ends || strtotime($ends) <= time()) flash('error', 'Please choose an end time in the future.');
        else {
            q('UPDATE products SET special_price = ?, special_ends_at = ? WHERE id = ?', [$special, $ends, $id]);
            flash('success', '“' . $p['name'] . '” is now ' . money($special) . ' until ' . date('d M, h:i A', strtotime($ends)) . '.');
        }
    } elseif ($action === 'end') {
        q('UPDATE products SET special_ends_at = ? WHERE id = ?', [now(), $id]);
        flash('success', 'Special ended for “' . $p['name'] . '”.');
    } elseif ($action === 'rerun') {
        if ($p['special_price'] && (float)$p['special_price'] < (float)$p['price']) {
            q('UPDATE products SET special_ends_at = ? WHERE id = ?', [date('Y-m-d 23:59:59'), $id]);
            flash('success', '“' . $p['name'] . '” is on special again until midnight.');
        }
    } elseif ($action === 'clear') {
        q('UPDATE products SET special_price = NULL, special_ends_at = NULL WHERE id = ?', [$id]);
        flash('success', 'Removed from specials list.');
    }
    redirect('admin/specials.php');
}

$live = q(product_select_sql() . ' WHERE p.special_price IS NOT NULL AND p.special_ends_at > ? ORDER BY p.special_ends_at', [now()])->fetchAll();
$past = q(product_select_sql() . ' WHERE p.special_price IS NOT NULL AND p.special_ends_at <= ? ORDER BY p.special_ends_at DESC LIMIT 20', [now()])->fetchAll();
$all  = q('SELECT id, name, price FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$preselect = (int)get('product');

$page_title = "Today's specials";
include __DIR__ . '/_top.php';
?>
<p class="help">Specials show on the homepage under <b>Today's deals</b> with a live countdown, and the price returns to normal automatically when time runs out. Customers are always charged the price that is live at checkout.</p>

<div class="cols">
  <section class="panel">
    <h2>Add a special</h2>
    <form method="post" class="special-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="set">
      <label class="f"><span>Product</span>
        <select name="product_id" required data-price-select>
          <option value="">Choose a product…</option>
          <?php foreach ($all as $a): ?><option value="<?= (int)$a['id'] ?>" data-price="<?= e((float)$a['price']) ?>" <?= $preselect === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?> — <?= e(money($a['price'])) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="row2">
        <label class="f"><span>Discount type</span>
          <select name="mode" data-mode>
            <option value="price">Special price (₹)</option>
            <option value="percent">Percent off (%)</option>
          </select>
        </label>
        <label class="f"><span data-value-label>Special price (₹)</span><input type="number" name="value" step="0.01" min="0.01" required data-value></label>
      </div>
      <p class="muted small" data-preview-price></p>
      <fieldset class="f">
        <span>Runs until</span>
        <div class="radio-chips">
          <label><input type="radio" name="preset" value="today" checked> End of today</label>
          <label><input type="radio" name="preset" value="tomorrow"> End of tomorrow</label>
          <label><input type="radio" name="preset" value="24"> 24 hours</label>
          <label><input type="radio" name="preset" value="72"> 3 days</label>
          <label><input type="radio" name="preset" value="168"> 1 week</label>
          <label><input type="radio" name="preset" value="custom"> Custom</label>
        </div>
        <input type="datetime-local" name="ends_custom" class="mt" data-custom-ends>
      </fieldset>
      <button class="btn" type="submit">Start special</button>
    </form>
  </section>

  <section class="panel">
    <h2>Live now <span class="pill pill-deal"><?= count($live) ?></span></h2>
    <?php if (!$live): ?><p class="muted">No specials running. Add one to fill the “Today's deals” section on your homepage.</p><?php endif; ?>
    <ul class="special-list">
      <?php foreach ($live as $p): ?>
      <li>
        <img src="<?= e(upload_url($p['image_1'])) ?>" alt="">
        <div>
          <a href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
          <span><b><?= e(money($p['special_price'])) ?></b> <s class="muted"><?= e(money($p['price'])) ?></s> · <?= discount_percent($p) ?>% off</span>
          <small class="muted">Ends <?= e(date('d M, h:i A', strtotime($p['special_ends_at']))) ?> · <?= (int)$p['stock'] ?> in stock</small>
        </div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="end"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">End now</button></form>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($past): ?>
    <h2 class="mt">Recently ended</h2>
    <ul class="special-list past">
      <?php foreach ($past as $p): ?>
      <li>
        <img src="<?= e(upload_url($p['image_1'])) ?>" alt="">
        <div><span><?= e($p['name']) ?></span><small class="muted"><?= e(money($p['special_price'])) ?> · ended <?= e(time_ago($p['special_ends_at'])) ?></small></div>
        <div class="btn-row">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rerun"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm" type="submit" title="Same price until midnight">Run today</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove from list">×</button></form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
