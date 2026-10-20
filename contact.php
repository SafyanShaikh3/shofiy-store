<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$errors = [];
$sent = false;
if (is_post()) {
    csrf_check();
    // Honeypot field catches most spam bots
    if (post('website') !== '') redirect('contact.php');
    $name = mb_substr((string)post('name'), 0, 80);
    $email = strtolower((string)post('email'));
    $phone = clean_phone((string)post('phone'));
    $msg = mb_substr((string)post('message'), 0, 2000);
    if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
    if (!valid_email($email)) $errors[] = 'Please enter a valid email.';
    if (mb_strlen($msg) < 5) $errors[] = 'Please write a message.';
    if (too_many_attempts('contact:' . client_ip(), 5, 60)) $errors[] = 'You have sent several messages recently. Please wait a while or call us.';
    if (!$errors) {
        q('INSERT INTO messages (user_id, name, email, phone, message, created_at) VALUES (?, ?, ?, ?, ?, ?)',
          [$user['id'] ?? null, $name, $email, $phone ?: null, $msg, now()]);
        record_attempt('contact:' . client_ip());
        if (setting('store_email')) send_mail(setting('store_email'), 'New message from ' . $name, '<p><b>' . e($name) . '</b> (' . e($email) . ', ' . e($phone) . ')</p>' . nl2p($msg));
        $sent = true;
    }
}

$page_title = 'Contact us';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="contact-layout">
      <div>
        <h1>Get in touch</h1>
        <p class="muted">Questions about a product or an order? We usually reply within a few hours.</p>
        <ul class="contact-list">
          <?php if (setting('store_phone')): ?><li><?= icon('phone') ?> <a href="tel:<?= e(clean_phone(setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li><?php endif; ?>
          <?php if (setting('whatsapp')): ?><li><?= icon('whatsapp') ?> <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener">WhatsApp us</a></li><?php endif; ?>
          <?php if (setting('store_email')): ?><li><?= icon('mail') ?> <a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li><?php endif; ?>
          <?php if (setting('store_address')): ?><li><?= icon('pin') ?> <span><?= e(setting('store_address')) ?></span></li><?php endif; ?>
        </ul>
      </div>
      <div class="card form-card">
        <?php if ($sent): ?>
          <div class="success-hero"><div class="success-icon"><?= icon('check', 30) ?></div><h2>Message sent</h2><p class="muted">Thanks, we'll get back to you soon.</p></div>
        <?php else: ?>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
          <label class="field"><span>Name</span><input name="name" required maxlength="80" value="<?= e(post('name') ?: ($user['name'] ?? '')) ?>"></label>
          <label class="field"><span>Email</span><input type="email" name="email" required maxlength="120" value="<?= e(post('email') ?: ($user['email'] ?? '')) ?>"></label>
          <label class="field"><span>Mobile (optional)</span><input type="tel" name="phone" maxlength="15" value="<?= e(post('phone') ?: ($user['phone'] ?? '')) ?>"></label>
          <label class="field"><span>Message</span><textarea name="message" rows="5" required maxlength="2000"><?= e(post('message')) ?></textarea></label>
          <button class="btn btn-lg btn-block" type="submit">Send message</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
