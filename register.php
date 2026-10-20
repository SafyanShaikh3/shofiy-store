<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string)(get('next') ?: post('next')), 'account.php');
if (current_user()) redirect($next);

$errors = [];
if (is_post()) {
    csrf_check();
    $name = mb_substr((string)post('name'), 0, 80);
    $email = strtolower((string)post('email'));
    $phone = clean_phone((string)post('phone'));
    $pass = (string)($_POST['password'] ?? '');

    if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
    if (!valid_email($email)) $errors[] = 'Please enter a valid email.';
    if ($phone !== '' && !valid_phone($phone)) $errors[] = 'Please enter a valid mobile number.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$errors && q_one('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'An account with this email already exists. Please log in.';

    if (!$errors) {
        q('INSERT INTO users (name, email, phone, password_hash, created_at) VALUES (?, ?, ?, ?, ?)',
          [$name, $email, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT), now()]);
        login_user((int)db()->lastInsertId());
        flash('success', 'Welcome, ' . $name . '! Your account is ready.');
        redirect($next);
    }
}
$page_title = 'Create account';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <div class="card form-card">
      <h1>Create account</h1>
      <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field"><span>Full name</span><input name="name" required maxlength="80" autocomplete="name" value="<?= e(post('name')) ?>"></label>
        <label class="field"><span>Email</span><input type="email" name="email" required maxlength="120" autocomplete="email" value="<?= e(post('email')) ?>"></label>
        <label class="field"><span>Mobile (optional)</span><input type="tel" name="phone" maxlength="15" autocomplete="tel" value="<?= e(post('phone')) ?>"></label>
        <label class="field"><span>Password (min 8 characters)</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-lg btn-block" type="submit">Create account</button>
      </form>
      <p class="center">Already have an account? <a href="<?= e(url('login.php?next=' . urlencode($next))) ?>">Log in</a></p>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
