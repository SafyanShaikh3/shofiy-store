<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'add') {
        $name = mb_substr((string)post('name'), 0, 80);
        $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)post('username')));
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($username) < 3) flash('error', 'Username must be at least 3 letters/numbers.');
        elseif (strlen($pass) < 10) flash('error', 'Admin passwords must be at least 10 characters.');
        elseif (q_one('SELECT id FROM admins WHERE username = ?', [$username])) flash('error', 'That username is taken.');
        else {
            q('INSERT INTO admins (name, username, password_hash, created_at) VALUES (?, ?, ?, ?)', [$name ?: $username, $username, password_hash($pass, PASSWORD_DEFAULT), now()]);
            flash('success', 'Admin “' . $username . '” added.');
        }
    } elseif ($action === 'password') {
        $row = q_one('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) flash('error', 'Current password is incorrect.');
        elseif (strlen($new) < 10) flash('error', 'New password must be at least 10 characters.');
        elseif ($new !== (string)($_POST['confirm_password'] ?? '')) flash('error', 'Passwords do not match.');
        else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        if ($id === (int)$admin['id']) flash('error', 'You cannot delete your own account.');
        elseif ((int)q_val('SELECT COUNT(*) FROM admins') <= 1) flash('error', 'At least one admin is required.');
        else { q('DELETE FROM admins WHERE id = ?', [$id]); flash('success', 'Admin removed.'); }
    }
    redirect('admin/admins.php');
}

$admins = q('SELECT id, name, username, last_login_at, created_at FROM admins ORDER BY id')->fetchAll();
$page_title = 'Admin users';
include __DIR__ . '/_top.php';
?>
<div class="cols">
  <section class="panel">
    <h2>Admins</h2>
    <ul class="admin-list">
      <?php foreach ($admins as $a): ?>
      <li>
        <div><b><?= e($a['name']) ?></b> <span class="muted">@<?= e($a['username']) ?></span><?= (int)$a['id'] === (int)$admin['id'] ? ' <span class="pill">you</span>' : '' ?><br>
          <small class="muted">Last login: <?= $a['last_login_at'] ? e(date('d M Y, h:i A', strtotime($a['last_login_at']))) : 'never' ?></small></div>
        <?php if ((int)$a['id'] !== (int)$admin['id']): ?>
        <form method="post" onsubmit="return confirm('Remove this admin?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-ghost danger">Remove</button></form>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <h2 class="mt">Add admin</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label class="f"><span>Name</span><input name="name" maxlength="80"></label>
      <label class="f"><span>Username</span><input name="username" required maxlength="50" autocapitalize="none"></label>
      <label class="f"><span>Password (min 10 characters)</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <button class="btn" type="submit">Add admin</button>
    </form>
  </section>
  <section class="panel">
    <h2>Change my password</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label class="f"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
      <label class="f"><span>New password</span><input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
      <label class="f"><span>Confirm new password</span><input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"></label>
      <button class="btn" type="submit">Change password</button>
    </form>
  </section>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
