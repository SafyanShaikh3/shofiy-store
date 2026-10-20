<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (current_admin()) redirect('admin/');

$error = '';
if (is_post()) {
    csrf_check();
    $username = strtolower(trim((string)post('username')));
    $pass = (string)($_POST['password'] ?? '');
    $ident = 'admin:' . client_ip();
    if (too_many_attempts($ident, 5, 15)) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    } else {
        $a = q_one('SELECT * FROM admins WHERE username = ?', [$username]);
        if ($a && password_verify($pass, $a['password_hash'])) {
            clear_attempts($ident);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_seen'] = time();
            q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
            redirect('admin/');
        }
        record_attempt($ident);
        $error = 'Incorrect username or password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Admin login · <?= e(setting('store_name')) ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-page">
  <form class="login-card" method="post">
    <h1><?= e(setting('store_name')) ?><span>.</span></h1>
    <p class="muted">Sign in to manage your store</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <?php foreach (flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?= csrf_field() ?>
    <label class="f"><span>Username</span><input name="username" required autocomplete="username" autocapitalize="none" value="<?= e(post('username')) ?>"></label>
    <label class="f"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-block" type="submit">Sign in</button>
    <p class="muted small center"><a href="<?= e(url('')) ?>">← Back to store</a></p>
  </form>
</body>
</html>
