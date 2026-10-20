<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = (int)get('id');
$p = $id ? q_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }
$isNew = !$p;
$p = $p ?? ['name' => '', 'category_id' => null, 'details' => '', 'price' => '', 'mrp' => '', 'special_price' => '', 'special_ends_at' => '',
            'stock' => 10, 'track_stock' => 1, 'low_stock_threshold' => (int)setting('low_stock_default', '5'), 'is_active' => 1, 'is_featured' => 0,
            'image_1' => null, 'image_2' => null, 'image_3' => null];

$errors = [];
if (is_post()) {
    csrf_check();
    $d = [
        'name'        => mb_substr((string)post('name'), 0, 150),
        'category_id' => (int)post('category_id') ?: null,
        'details'     => mb_substr((string)post('details'), 0, 5000),
        'price'       => (float)post('price'),
        'mrp'         => post('mrp') === '' ? null : (float)post('mrp'),
        'stock'       => (int)post('stock'),
        'track_stock' => post('track_stock') ? 1 : 0,
        'low_stock_threshold' => max(0, (int)post('low_stock_threshold')),
        'is_active'   => post('is_active') ? 1 : 0,
        'is_featured' => post('is_featured') ? 1 : 0,
        'special_price'   => post('special_price') === '' ? null : (float)post('special_price'),
        'special_ends_at' => post('special_ends_at') === '' ? null : date('Y-m-d H:i:s', strtotime((string)post('special_ends_at'))),
    ];
    if (mb_strlen($d['name']) < 2) $errors[] = 'Please enter a product name.';
    if ($d['price'] <= 0) $errors[] = 'Price must be greater than 0.';
    if ($d['stock'] < 0) $errors[] = 'Stock cannot be negative.';
    if ($d['special_price'] !== null) {
        if ($d['special_price'] <= 0 || $d['special_price'] >= $d['price']) $errors[] = 'Special price must be lower than the regular price.';
        if (!$d['special_ends_at']) $errors[] = 'Choose when the special price ends.';
    } else {
        $d['special_ends_at'] = null;
    }

    // Images
    $images = ['image_1' => $p['image_1'], 'image_2' => $p['image_2'], 'image_3' => $p['image_3']];
    $toDelete = [];
    foreach ($images as $field => $cur) {
        if (post('remove_' . $field) && $cur) { $toDelete[] = $cur; $images[$field] = null; }
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $new = save_uploaded_image($_FILES[$field], 'products');
                if ($images[$field]) $toDelete[] = $images[$field];
                $images[$field] = $new;
            } catch (RuntimeException $e) {
                $errors[] = 'Photo ' . substr($field, -1) . ': ' . $e->getMessage();
            }
        }
    }
    // Shift images so there are no gaps (main image always filled first)
    $list = array_values(array_filter($images));
    $images = ['image_1' => $list[0] ?? null, 'image_2' => $list[1] ?? null, 'image_3' => $list[2] ?? null];

    if (!$errors) {
        $d += $images;
        if ($isNew) {
            $d['slug'] = unique_slug('products', $d['name']);
            $cols = array_keys($d);
            q('INSERT INTO products (' . implode(',', $cols) . ', created_at) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', ?)', array_merge(array_values($d), [now()]));
            $id = (int)db()->lastInsertId();
            flash('success', 'Product added. It is now live on your store.');
        } else {
            if ($d['name'] !== $p['name']) $d['slug'] = unique_slug('products', $d['name'], $id);
            $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($d)));
            q("UPDATE products SET $set WHERE id = ?", array_merge(array_values($d), [$id]));
            flash('success', 'Product saved.');
        }
        foreach ($toDelete as $f) delete_upload($f, 'products');
        redirect('admin/product_edit.php?id=' . $id);
    }
    $p = array_merge($p, $d, $images);
}

$cats = q('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$endsVal = $p['special_ends_at'] ? date('Y-m-d\TH:i', strtotime($p['special_ends_at'])) : '';
$page_title = $isNew ? 'Add product' : 'Edit product';
$top_actions = $isNew ? '' : '<a class="btn btn-sm btn-ghost" href="' . e(url('product.php?id=' . $id)) . '" target="_blank" rel="noopener">View on store ↗</a>';
include __DIR__ . '/_top.php';
?>
<a class="back" href="<?= e(url('admin/products.php')) ?>">← All products</a>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="edit-grid">
  <?= csrf_field() ?>
  <div class="edit-main">
    <section class="panel">
      <label class="f"><span>Product name</span><input name="name" value="<?= e($p['name']) ?>" required maxlength="150" placeholder="e.g. 43&quot; Full HD Smart LED TV"></label>
      <label class="f"><span>Category</span>
        <select name="category_id">
          <option value="">— No category —</option>
          <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="f"><span>Description</span><textarea name="details" rows="7" maxlength="5000" placeholder="Key features, warranty, what's in the box…"><?= e($p['details']) ?></textarea><small class="muted">Leave a blank line between paragraphs.</small></label>
    </section>

    <section class="panel">
      <h2>Photos</h2>
      <p class="help">JPG, PNG or WebP. Photos are resized and optimised automatically. The first photo is the main one.</p>
      <div class="img-grid">
        <?php foreach (['image_1' => 'Main photo', 'image_2' => 'Photo 2', 'image_3' => 'Photo 3'] as $f => $label): ?>
        <div class="img-slot">
          <div class="img-preview" data-preview="<?= $f ?>"><?php if ($p[$f]): ?><img src="<?= e(upload_url($p[$f])) ?>" alt=""><?php else: ?><span><?= e($label) ?></span><?php endif; ?></div>
          <label class="btn btn-sm btn-ghost file-btn">Choose…<input type="file" name="<?= $f ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-preview-input="<?= $f ?>"></label>
          <?php if ($p[$f]): ?><label class="check small"><input type="checkbox" name="remove_<?= $f ?>" value="1"> Remove</label><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <div class="edit-side">
    <section class="panel">
      <h2>Price</h2>
      <label class="f"><span>Selling price (₹)</span><input type="number" name="price" step="0.01" min="0.01" value="<?= e($p['price']) ?>" required></label>
      <label class="f"><span>MRP (₹, optional)</span><input type="number" name="mrp" step="0.01" min="0" value="<?= e($p['mrp']) ?>" placeholder="Shown crossed out"></label>
    </section>

    <section class="panel special-panel">
      <h2>⚡ Daily special</h2>
      <p class="help">A lower price for a limited time. It appears in “Today's deals” with a countdown and switches back automatically.</p>
      <label class="f"><span>Special price (₹)</span><input type="number" name="special_price" step="0.01" min="0" value="<?= e($p['special_price']) ?>" placeholder="Leave empty for no special"></label>
      <label class="f"><span>Ends at</span><input type="datetime-local" name="special_ends_at" value="<?= e($endsVal) ?>" data-ends-input></label>
      <div class="chips">
        <button type="button" class="chip-btn" data-ends-preset="today">End of today</button>
        <button type="button" class="chip-btn" data-ends-preset="24">24 hours</button>
        <button type="button" class="chip-btn" data-ends-preset="72">3 days</button>
        <button type="button" class="chip-btn" data-ends-preset="clear">Clear</button>
      </div>
    </section>

    <section class="panel">
      <h2>Stock</h2>
      <label class="f"><span>Units in stock</span><input type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>"></label>
      <label class="f"><span>Show “Only X left” at or below</span><input type="number" name="low_stock_threshold" min="0" value="<?= (int)$p['low_stock_threshold'] ?>"></label>
      <label class="check"><input type="checkbox" name="track_stock" value="1" <?= $p['track_stock'] ? 'checked' : '' ?>> Track stock (untick for always-available items)</label>
    </section>

    <section class="panel">
      <h2>Visibility</h2>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>> Visible on store</label>
      <label class="check"><input type="checkbox" name="is_featured" value="1" <?= $p['is_featured'] ? 'checked' : '' ?>> Feature on homepage</label>
    </section>

    <div class="sticky-save">
      <button class="btn btn-block" type="submit"><?= $isNew ? 'Add product' : 'Save changes' ?></button>
    </div>
  </div>
</form>

<?php if (!$isNew): ?>
<form method="post" action="<?= e(url('admin/products.php')) ?>" class="danger-zone" onsubmit="return confirm('Delete this product? This cannot be undone.');">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
  <button class="btn btn-sm btn-danger" type="submit">Delete product</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/_bottom.php'; ?>
