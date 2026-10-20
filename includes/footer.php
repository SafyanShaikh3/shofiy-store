</main>
<?php $store = setting('store_name', 'Store'); ?>
<section class="trust">
  <div class="wrap trust-row">
    <div><?= icon('truck') ?><span><b>Fast delivery</b><?= setting('free_shipping_over') ? 'Free above ' . e(money(setting('free_shipping_over'))) : 'Across India' ?></span></div>
    <div><?= icon('shield') ?><span><b>Secure payments</b>UPI, cards & netbanking</span></div>
    <div><?= icon('refresh') ?><span><b>Easy returns</b>See our refund policy</span></div>
    <div><?= icon('phone') ?><span><b>Need help?</b><?= e(setting('store_phone')) ?></span></div>
  </div>
</section>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="logo" href="<?= e(url('')) ?>"><?= e($store) ?><span>.</span></a>
      <p class="muted"><?= e(setting('tagline')) ?></p>
      <div class="social">
        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X / Twitter', 'youtube' => 'YouTube'] as $k => $label): ?>
          <?php if (setting('social_' . $k)): ?><a href="<?= e(setting('social_' . $k)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h3>Shop</h3>
      <a href="<?= e(url('shop.php')) ?>">All products</a>
      <a href="<?= e(url('shop.php?deals=1')) ?>">Today's deals</a>
      <a href="<?= e(url('cart.php')) ?>">Cart</a>
      <a href="<?= e(url('track.php')) ?>">Track order</a>
    </div>
    <div>
      <h3>Help</h3>
      <a href="<?= e(url('about.php')) ?>">About us</a>
      <a href="<?= e(url('contact.php')) ?>">Contact us</a>
      <a href="<?= e(url('policy.php?p=shipping')) ?>">Shipping policy</a>
      <a href="<?= e(url('policy.php?p=refund')) ?>">Cancellation & refunds</a>
      <a href="<?= e(url('policy.php?p=terms')) ?>">Terms & conditions</a>
      <a href="<?= e(url('policy.php?p=privacy')) ?>">Privacy policy</a>
    </div>
    <div>
      <h3>Contact</h3>
      <?php if (setting('store_phone')): ?><a href="tel:<?= e(clean_phone(setting('store_phone'))) ?>"><?= icon('phone', 16) ?> <?= e(setting('store_phone')) ?></a><?php endif; ?>
      <?php if (setting('store_phone_2')): ?><a href="tel:<?= e(clean_phone(setting('store_phone_2'))) ?>"><?= icon('phone', 16) ?> <?= e(setting('store_phone_2')) ?></a><?php endif; ?>
      <?php if (setting('store_email')): ?><a href="mailto:<?= e(setting('store_email')) ?>"><?= icon('mail', 16) ?> <?= e(setting('store_email')) ?></a><?php endif; ?>
      <?php if (setting('store_address')): ?><span><?= icon('pin', 16) ?> <?= e(setting('store_address')) ?></span><?php endif; ?>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <span>© <?= date('Y') ?> <?= e($store) ?>. All rights reserved.</span>
    <span class="pay-note"><?= icon('lock', 14) ?> Payments secured by Razorpay</span>
  </div>
</footer>
<?php if (setting('whatsapp')): ?>
<a class="wa-float" href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?= icon('whatsapp', 26) ?></a>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
