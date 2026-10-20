<?php
require __DIR__ . '/includes/bootstrap.php';

$pages = [
    'terms'    => 'Terms & conditions',
    'privacy'  => 'Privacy policy',
    'refund'   => 'Cancellation & refund policy',
    'shipping' => 'Shipping & delivery policy',
];
$key = get('p');
if (!isset($pages[$key])) $key = 'terms';

$store = setting('store_name');
$email = setting('store_email');
$phone = setting('store_phone');
$addr  = setting('store_address');

// Sensible starting templates. Edit them in Admin → Settings → Policies (have them reviewed for your business).
$defaults = [
'terms' => "## Overview
This website is operated by $store. By using this site and placing an order you agree to these terms.
## Products and pricing
- All prices are in Indian Rupees and include applicable taxes unless stated otherwise.
- We try to show product details and stock accurately. If an error is found after you order, we will contact you and you may cancel for a full refund.
- Deal prices are valid only until the time shown on the product.
## Orders
- An order is confirmed once payment is successful (or, for cash on delivery, once we confirm by phone).
- We may cancel an order if a product is unavailable, if the delivery address is not serviceable, or if we suspect fraud. Any payment made will be refunded in full.
## Payments
Online payments are processed securely by Razorpay. We do not store your card or bank details.
## Contact
$store · $addr · $phone · $email",

'privacy' => "## What we collect
When you place an order or create an account we collect your name, email, phone number and delivery address. Payment details are handled directly by Razorpay and never reach our servers.
## How we use it
- To process and deliver your orders and send order updates.
- To respond to your messages.
- We do not sell or rent your personal information.
## Cookies
We use essential cookies to keep you logged in and to remember your cart.
## Your choices
You can update your profile at any time or ask us to delete your account by contacting $email.",

'refund' => "## Cancellations
- You can cancel an order before it is shipped by calling $phone or emailing $email with your order number.
- Prepaid orders cancelled before shipping are refunded in full.
## Returns
- Products that arrive damaged, defective or different from what you ordered can be returned within 7 days of delivery.
- Items must be unused and in original packaging with all accessories.
## Refunds
- Approved refunds are issued to the original payment method within 5–7 working days.
- Cash on delivery orders are refunded by bank transfer or UPI.",

'shipping' => "## Delivery areas
We deliver across India. Delivery to remote areas may take longer.
## Delivery time
- Orders are usually dispatched within 1–2 working days.
- Typical delivery time is 3–7 working days after dispatch.
## Charges
" . ((float)setting('free_shipping_over') > 0 ? 'Delivery is free on orders above ' . money(setting('free_shipping_over')) . '. ' : '') . ((float)setting('shipping_fee') > 0 ? 'A delivery fee of ' . money(setting('shipping_fee')) . ' applies to smaller orders.' : 'Delivery is free.') . "
## Tracking
You can track your order any time from the Track order page using your order number.",
];

$text = setting('policy_' . $key) ?: $defaults[$key];
$page_title = $pages[$key];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <nav class="tabs" aria-label="Policies">
      <?php foreach ($pages as $k => $label): ?><a href="<?= e(url('policy.php?p=' . $k)) ?>" class="<?= $k === $key ? 'active' : '' ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <article class="card prose">
      <h1><?= e($pages[$key]) ?></h1>
      <?= render_text_block($text) ?>
    </article>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
