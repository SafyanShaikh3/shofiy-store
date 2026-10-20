<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_user();

$errors = [];
if (is_post()) {
    csrf_check();
    if (post('form') === 'profile') {
        $name = mb_substr((string)post('name'), 0, 80);
        $phone = clean_phone((string)post('phone'));
        if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
        if ($phone !== '' && !valid_phone($phone)) $errors[] = 'Please enter a valid mobile number.';
        if (!$errors) {
            q('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone ?: null, $user['id']]);
            flash('success', 'Profile updated.');
            redirect('account.php');
        }
    } elseif (post('form') === 'password') {
        $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) $errors[] = 'Your current password is incorrect.';
        elseif (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        elseif ($new !== (string)($_POST['confirm_password'] ?? '')) $errors[] = 'New passwords do not match.';
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
            redirect('account.php');
        }
    }
}
$orderCount = (int)q_val("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status <> 'expired'", [$user['id']]);

$page_title = 'My account';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="page-head"><h1>Hi, <?= e($user['name']) ?></h1><p class="muted"><?= e($user['email']) ?></p></div>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

    <div class="account-links">
      <a class="card" href="<?= e(url('orders.php')) ?>"><?= icon('box') ?><span><b>My orders</b><small><?= $orderCount ?> order<?= $orderCount === 1 ? '' : 's' ?></small></span></a>
      <a class="card" href="<?= e(url('wishlist.php')) ?>"><?= icon('heart') ?><span><b>Wishlist</b><small>Saved items</small></span></a>
      <a class="card" href="<?= e(url('cart.php')) ?>"><?= icon('cart') ?><span><b>Cart</b><small><?= cart_count() ?> item(s)</small></span></a>
    </div>

    <div class="card">
      <h2>Profile</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="form" value="profile">
        <label class="field"><span>Name</span><input name="name" value="<?= e($user['name']) ?>" required maxlength="80"></label>
        <label class="field"><span>Mobile</span><input type="tel" name="phone" value="<?= e($user['phone']) ?>" maxlength="15"></label>
        <button class="btn" type="submit">Save profile</button>
      </form>
    </div>

    <div class="card">
      <h2>Change password</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="form" value="password">
        <label class="field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
        <label class="field"><span>New password</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
        <label class="field"><span>Confirm new password</span><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn" type="submit">Change password</button>
      </form>
    </div>

    <form method="post" action="<?= e(url('logout.php')) ?>" class="center">
      <?= csrf_field() ?><button class="btn btn-outline" type="submit">Log out</button>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
