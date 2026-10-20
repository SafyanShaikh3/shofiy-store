<?php
require __DIR__ . '/includes/bootstrap.php';
release_expired_reservations();

$lines = cart_lines();
if (!$lines) { flash('info', 'Your cart is empty.'); redirect('cart.php'); }
$t = cart_totals($lines);
if ($t['problems']) { flash('error', 'Some items in your cart need attention.'); redirect('cart.php'); }

$user = current_user();
$codMax = (float)setting('cod_max_amount', '0');
$codOn  = setting('enable_cod') === '1';
$codOk  = $codOn && ($codMax <= 0 || $t['total'] <= $codMax);
$rzpOn  = rzp_enabled();

// Prefill: last order of this user, else profile
$prefill = ['name' => '', 'email' => '', 'phone' => '', 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'pincode' => '', 'country' => 'India', 'notes' => ''];
if ($user) {
    $prefill = array_merge($prefill, ['name' => $user['name'], 'email' => $user['email'], 'phone' => (string)$user['phone']]);
    $last = q_one('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$user['id']]);
    if ($last) {
        $prefill = array_merge($prefill, ['phone' => $last['phone'], 'address1' => $last['address_line1'], 'address2' => (string)$last['address_line2'],
            'city' => $last['city'], 'state' => $last['state'], 'pincode' => $last['pincode'], 'country' => $last['country']]);
    }
}
$prefill = array_merge($prefill, $_SESSION['checkout_form'] ?? []);

$errors = [];
if (is_post()) {
    csrf_check();
    $c = [];
    foreach (array_keys($prefill) as $k) $c[$k] = mb_substr((string)post($k), 0, $k === 'notes' ? 500 : 150);
    $c['email'] = strtolower($c['email']);
    $c['phone'] = clean_phone($c['phone']);
    $method = post('payment_method');

    if (mb_strlen($c['name']) < 2) $errors['name'] = 'Please enter your full name.';
    if (!valid_email($c['email'])) $errors['email'] = 'Please enter a valid email address.';
    if (!valid_phone($c['phone'])) $errors['phone'] = 'Please enter a valid 10-digit mobile number.';
    if (mb_strlen($c['address1']) < 5) $errors['address1'] = 'Please enter your house / flat and street.';
    if ($c['city'] === '') $errors['city'] = 'Please enter your city.';
    if ($c['state'] === '') $errors['state'] = 'Please enter your state.';
    if (!preg_match('/^\d{6}$/', $c['pincode']) && strtolower($c['country']) === 'india') $errors['pincode'] = 'Please enter a valid 6-digit PIN code.';
    if (!in_array($method, ['razorpay', 'cod'], true) || ($method === 'razorpay' && !$rzpOn) || ($method === 'cod' && !$codOk)) $errors['payment_method'] = 'Please choose a payment method.';

    $_SESSION['checkout_form'] = $c;
    $prefill = $c;

    if (!$errors) {
        try {
            $order = create_order_from_cart($c, $method);
            if ($method === 'razorpay') {
                try {
                    rzp_create_order($order);
                } catch (RuntimeException $e) {
                    cancel_order((int)$order['id'], 'cancelled');
                    error_log('[EWC] Razorpay order create failed: ' . $e->getMessage());
                    throw new RuntimeException('We could not start the payment right now. Please try again in a minute' . ($codOk ? ' or choose cash on delivery.' : '.'));
                }
                $_SESSION['last_order'] = $order['order_no'];
                redirect('pay.php?no=' . urlencode($order['order_no']) . '&t=' . $order['access_token']);
            }
            flash('success', 'Order placed! We\'ll call you to confirm delivery.');
            redirect(order_view_url($order) . '&new=1');
        } catch (RuntimeException $e) {
            $errors['form'] = $e->getMessage();
        }
    }
}

$page_title = 'Checkout';
$body_class = 'page-checkout';
include __DIR__ . '/includes/header.php';

function field(string $name, string $label, array $prefill, array $errors, string $type = 'text', string $extra = ''): void
{
    $err = $errors[$name] ?? '';
    echo '<label class="field' . ($err ? ' has-error' : '') . '"><span>' . e($label) . '</span>'
        . '<input type="' . $type . '" name="' . $name . '" value="' . e($prefill[$name] ?? '') . '" ' . $extra . '>'
        . ($err ? '<small class="error">' . e($err) . '</small>' : '') . '</label>';
}
?>
<section class="section">
  <div class="wrap">
    <div class="page-head"><h1>Checkout</h1></div>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?> <a href="<?= e(url('cart.php')) ?>">Review cart</a></div><?php endif; ?>
    <?php if (!$user): ?><p class="muted">Checking out as a guest. <a href="<?= e(url('login.php?next=' . urlencode(url('checkout.php')))) ?>">Log in</a> to save your order history.</p><?php endif; ?>

    <form class="checkout-layout" method="post" action="" novalidate data-checkout-form>
      <?= csrf_field() ?>
      <div class="checkout-main">
        <fieldset class="card">
          <legend>Contact</legend>
          <div class="form-grid">
            <?php field('name', 'Full name', $prefill, $errors, 'text', 'autocomplete="name" required maxlength="80"'); ?>
            <?php field('phone', 'Mobile number', $prefill, $errors, 'tel', 'autocomplete="tel" inputmode="tel" required maxlength="15" placeholder="10-digit mobile"'); ?>
            <?php field('email', 'Email (for order updates)', $prefill, $errors, 'email', 'autocomplete="email" required maxlength="120"'); ?>
          </div>
        </fieldset>
        <fieldset class="card">
          <legend>Delivery address</legend>
          <div class="form-grid">
            <?php field('address1', 'House / flat no., street', $prefill, $errors, 'text', 'autocomplete="address-line1" required maxlength="150"'); ?>
            <?php field('address2', 'Area, landmark (optional)', $prefill, $errors, 'text', 'autocomplete="address-line2" maxlength="150"'); ?>
            <?php field('city', 'City', $prefill, $errors, 'text', 'autocomplete="address-level2" required maxlength="60"'); ?>
            <?php field('state', 'State', $prefill, $errors, 'text', 'autocomplete="address-level1" required maxlength="60"'); ?>
            <?php field('pincode', 'PIN code', $prefill, $errors, 'text', 'autocomplete="postal-code" inputmode="numeric" required maxlength="6" pattern="[0-9]{6}"'); ?>
            <?php field('country', 'Country', $prefill, $errors, 'text', 'autocomplete="country-name" maxlength="60"'); ?>
          </div>
          <label class="field"><span>Delivery notes (optional)</span><textarea name="notes" rows="2" maxlength="500"><?= e($prefill['notes']) ?></textarea></label>
        </fieldset>
        <fieldset class="card">
          <legend>Payment</legend>
          <?php if (!empty($errors['payment_method'])): ?><p class="error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
          <?php if (!$rzpOn && !$codOk): ?>
            <div class="alert alert-error">Online payment isn't available right now. Please contact us at <?= e(setting('store_phone')) ?> to place your order.</div>
          <?php endif; ?>
          <?php $sel = post('payment_method') ?: ($rzpOn ? 'razorpay' : 'cod'); ?>
          <?php if ($rzpOn): ?>
          <label class="pay-option">
            <input type="radio" name="payment_method" value="razorpay" <?= $sel === 'razorpay' ? 'checked' : '' ?>>
            <span><b>Pay online</b><small>UPI, cards, netbanking & wallets — secured by Razorpay</small></span>
          </label>
          <?php endif; ?>
          <?php if ($codOn): ?>
          <label class="pay-option<?= $codOk ? '' : ' disabled' ?>">
            <input type="radio" name="payment_method" value="cod" <?= $sel === 'cod' && $codOk ? 'checked' : '' ?> <?= $codOk ? '' : 'disabled' ?>>
            <span><b>Cash on delivery</b><small><?= $codOk ? 'Pay when your order arrives' : 'Available for orders up to ' . e(money($codMax)) ?></small></span>
          </label>
          <?php endif; ?>
        </fieldset>
      </div>

      <aside class="summary card">
        <h2>Your order</h2>
        <ul class="mini-lines">
          <?php foreach ($lines as $l): ?>
          <li><img src="<?= e(upload_url($l['product']['image_1'])) ?>" alt=""><span><?= e($l['product']['name']) ?> <small>× <?= $l['qty'] ?></small></span><b><?= e(money($l['line_total'])) ?></b></li>
          <?php endforeach; ?>
        </ul>
        <dl>
          <div><dt>Subtotal</dt><dd><?= e(money($t['subtotal'])) ?></dd></div>
          <div><dt>Delivery</dt><dd><?= $t['shipping'] > 0 ? e(money($t['shipping'])) : 'Free' ?></dd></div>
          <div class="total"><dt>Total</dt><dd><?= e(money($t['total'])) ?></dd></div>
        </dl>
        <button class="btn btn-lg btn-block" type="submit" <?= (!$rzpOn && !$codOk) ? 'disabled' : '' ?> data-place-order>Place order · <?= e(money($t['total'])) ?></button>
        <p class="secure-note"><?= icon('lock', 14) ?> Items are held for you for <?= (int)setting('reservation_minutes', '20') ?> minutes while you pay.</p>
        <p class="tiny muted">By placing your order you agree to our <a href="<?= e(url('policy.php?p=terms')) ?>">terms</a> and <a href="<?= e(url('policy.php?p=refund')) ?>">refund policy</a>.</p>
      </aside>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
