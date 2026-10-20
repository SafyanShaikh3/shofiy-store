<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $name = mb_substr((string)post('name'), 0, 80);
        $sort = (int)post('sort_order');
        $active = post('is_active') ? 1 : 0;
        if (mb_strlen($name) < 2) { flash('error', 'Please enter a category name.'); redirect('admin/categories.php'); }
        $cur = $id ? q_one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
        $image = $cur['image'] ?? null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try { $new = save_uploaded_image($_FILES['image'], 'categories', 300); delete_upload($image, 'categories'); $image = $new; }
            catch (RuntimeException $e) { flash('error', $e->getMessage()); redirect('admin/categories.php'); }
        }
        if (post('remove_image') && $image) { delete_upload($image, 'categories'); $image = null; }
        if ($cur) {
            $slug = $name !== $cur['name'] ? unique_slug('categories', $name, $id) : $cur['slug'];
            q('UPDATE categories SET name = ?, slug = ?, sort_order = ?, is_active = ?, image = ? WHERE id = ?', [$name, $slug, $sort, $active, $image, $id]);
            flash('success', 'Category saved.');
        } else {
            q('INSERT INTO categories (name, slug, sort_order, is_active, image) VALUES (?, ?, ?, ?, ?)', [$name, unique_slug('categories', $name), $sort, 1, $image]);
            flash('success', 'Category added.');
        }
    } elseif ($action === 'delete' && $id) {
        $cur = q_one('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($cur) {
            q('DELETE FROM categories WHERE id = ?', [$id]);
            delete_upload($cur['image'], 'categories');
            flash('success', 'Category deleted. Its products are now uncategorised.');
        }
    }
    redirect('admin/categories.php');
}

$cats = q('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY c.sort_order, c.name')->fetchAll();
$page_title = 'Categories';
include __DIR__ . '/_top.php';
?>
<p class="help">Categories appear in the menu and the “Shop by category” row. Lower sort numbers appear first. The icon is optional — a product photo is used if none is set.</p>

<section class="panel">
  <h2>Add category</h2>
  <form method="post" enctype="multipart/form-data" class="inline-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <label class="f"><span>Name</span><input name="name" required maxlength="80" placeholder="e.g. Air conditioners"></label>
    <label class="f small-f"><span>Sort</span><input type="number" name="sort_order" value="<?= count($cats) + 1 ?>"></label>
    <label class="f"><span>Icon (optional)</span><input type="file" name="image" accept="image/*"></label>
    <button class="btn" type="submit">Add</button>
  </form>
</section>

<div class="cat-admin">
  <?php foreach ($cats as $c): ?>
  <form method="post" enctype="multipart/form-data" class="panel cat-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="cat-icon"><?php if ($c['image']): ?><img src="<?= e(upload_url($c['image'], 'categories')) ?>" alt=""><?php else: ?><span>No icon</span><?php endif; ?></div>
    <div class="cat-fields">
      <label class="f"><span>Name</span><input name="name" value="<?= e($c['name']) ?>" required maxlength="80"></label>
      <div class="row2">
        <label class="f"><span>Sort</span><input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>"></label>
        <label class="f"><span>Icon</span><input type="file" name="image" accept="image/*"></label>
      </div>
      <div class="cat-foot">
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>> Visible</label>
        <?php if ($c['image']): ?><label class="check"><input type="checkbox" name="remove_image" value="1"> Remove icon</label><?php endif; ?>
        <span class="muted small"><?= (int)$c['n'] ?> products</span>
      </div>
      <div class="btn-row">
        <button class="btn btn-sm" type="submit">Save</button>
        <button class="btn btn-sm btn-ghost danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Delete this category? Products stay but become uncategorised.')">Delete</button>
      </div>
    </div>
  </form>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
