<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string)(get('next') ?: post('next')), 'account.php');
if (current_user()) redirect($next);

$error = '';
if (is_post()) {
    csrf_check();
    $email = strtolower((string)post('email'));
    $pass = (string)($_POST['password'] ?? '');
    $ident = 'user:' . $email . '|' . client_ip();
    if (too_many_attempts($ident)) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } else {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && password_verify($pass, $u['password_hash'])) {
            clear_attempts($ident);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            login_user((int)$u['id']);
            flash('success', 'Welcome back, ' . $u['name'] . '!');
            redirect($next);
        }
        record_attempt($ident);
        $error = 'Incorrect email or password.';
    }
}
$page_title = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="card form-card">
      <h1>Log in</h1>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label>
        <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-lg btn-block" type="submit">Log in</button>
      </form>
      <p class="center">New here? <a href="<?= e(url('register.php?next=' . urlencode($next))) ?>">Create an account</a></p>
      <p class="center small muted">You can also check out as a guest — no account needed.</p>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
