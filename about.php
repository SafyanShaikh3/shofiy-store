<?php
require __DIR__ . '/includes/bootstrap.php';
$page_title = 'About us';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="about">
      <img src="<?= e(url('assets/img/about.svg')) ?>" alt="" width="420" height="320">
      <div>
        <h1>Why choose <?= e(setting('store_name')) ?>?</h1>
        <div class="prose"><?= nl2p(setting('about_text')) ?></div>
        <a class="btn" href="<?= e(url('contact.php')) ?>">Contact us</a>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
