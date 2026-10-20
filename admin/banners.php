<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

function clean_link(string $u): string
{
    $u = trim($u);
    if ($u === '') return '';
    if (preg_match('~^https?://~i', $u) || preg_match('~^[a-z0-9_\-./?=&%#]+$~i', $u) && !preg_match('~^(javascript|data|vbscript):~i', $u)) return $u;
    return '';
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $cur = $id ? q_one('SELECT * FROM banners WHERE id = ?', [$id]) : null;
        $d = [
            mb_substr((string)post('title'), 0, 120),
            mb_substr((string)post('subtitle'), 0, 160),
            clean_link((string)post('link_url')),
            mb_substr((string)post('button_text'), 0, 40),
            (int)post('sort_order'),
            post('is_active') || !$cur ? 1 : 0,
        ];
        if (mb_strlen($d[0]) < 2) { flash('error', 'Please enter a headline.'); redirect('admin/banners.php'); }
        $image = $cur['image'] ?? null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try { $new = save_uploaded_image($_FILES['image'], 'banners', 900); delete_upload($image, 'banners'); $image = $new; }
            catch (RuntimeException $e) { flash('error', $e->getMessage()); redirect('admin/banners.php'); }
        }
        $d[] = $image;
        if ($cur) {
            q('UPDATE banners SET title = ?, subtitle = ?, link_url = ?, button_text = ?, sort_order = ?, is_active = ?, image = ? WHERE id = ?', array_merge($d, [$id]));
            flash('success', 'Banner saved.');
        } else {
            q('INSERT INTO banners (title, subtitle, link_url, button_text, sort_order, is_active, image) VALUES (?, ?, ?, ?, ?, ?, ?)', $d);
            flash('success', 'Banner added to the homepage.');
        }
    } elseif ($action === 'delete' && $id) {
        $cur = q_one('SELECT * FROM banners WHERE id = ?', [$id]);
        if ($cur) { q('DELETE FROM banners WHERE id = ?', [$id]); delete_upload($cur['image'], 'banners'); flash('success', 'Banner deleted.'); }
    }
    redirect('admin/banners.php');
}

$banners = q('SELECT * FROM banners ORDER BY sort_order, id')->fetchAll();
$page_title = 'Home banners';
include __DIR__ . '/_top.php';
?>
<p class="help">These slides rotate at the top of your homepage. Use a product photo with a plain or transparent background for the best look. Links can be a page on your site (e.g. <code>shop.php?deals=1</code>) or a full web address.</p>

<div class="banner-admin">
  <?php foreach (array_merge($banners, [null]) as $b): $new = $b === null; ?>
  <form method="post" enctype="multipart/form-data" class="panel banner-card<?= $new ? ' new' : '' ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($b['id'] ?? 0) ?>">
    <h2><?= $new ? '+ Add a banner' : 'Slide ' . e($b['sort_order']) ?></h2>
    <div class="banner-preview">
      <div><small><?= e($b['subtitle'] ?? 'Subtitle') ?></small><b><?= e($b['title'] ?? 'Headline') ?></b></div>
      <?php if (!$new && $b['image']): ?><img src="<?= e(upload_url($b['image'], 'banners')) ?>" alt=""><?php endif; ?>
    </div>
    <label class="f"><span>Headline</span><input name="title" value="<?= e($b['title'] ?? '') ?>" maxlength="120" required placeholder="e.g. Latest smartphones"></label>
    <label class="f"><span>Small text above headline</span><input name="subtitle" value="<?= e($b['subtitle'] ?? '') ?>" maxlength="160" placeholder="e.g. Up to 30% off"></label>
    <div class="row2">
      <label class="f"><span>Button link</span><input name="link_url" value="<?= e($b['link_url'] ?? 'shop.php') ?>" maxlength="255"></label>
      <label class="f"><span>Button text</span><input name="button_text" value="<?= e($b['button_text'] ?? 'Shop now') ?>" maxlength="40"></label>
    </div>
    <div class="row2">
      <label class="f"><span>Image</span><input type="file" name="image" accept="image/*"></label>
      <label class="f"><span>Order</span><input type="number" name="sort_order" value="<?= (int)($b['sort_order'] ?? count($banners) + 1) ?>"></label>
    </div>
    <?php if (!$new): ?><label class="check"><input type="checkbox" name="is_active" value="1" <?= $b['is_active'] ? 'checked' : '' ?>> Show on homepage</label><?php endif; ?>
    <div class="btn-row">
      <button class="btn btn-sm" type="submit"><?= $new ? 'Add banner' : 'Save' ?></button>
      <?php if (!$new): ?><button class="btn btn-sm btn-ghost danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Delete this banner?')">Delete</button><?php endif; ?>
    </div>
  </form>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
