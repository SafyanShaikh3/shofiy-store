<?php
/**
 * Razorpay Checkout success callback. The signature proves the payment belongs to the
 * Razorpay order we created (and therefore to the exact amount we set on the server).
 */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('');
csrf_check();

$o = q_one('SELECT * FROM orders WHERE order_no = ?', [post('order_no')]);
if (!$o || !hash_equals($o['access_token'], (string)post('t'))) {
    flash('error', 'Order not found.');
    redirect('cart.php');
}

$paymentId = (string)post('razorpay_payment_id');
$rzpOrder  = (string)post('razorpay_order_id');
$signature = (string)post('razorpay_signature');

if ($rzpOrder !== (string)$o['razorpay_order_id'] || !rzp_verify_payment_signature($rzpOrder, $paymentId, $signature)) {
    error_log('[EWC] Signature verification failed for order ' . $o['order_no']);
    flash('error', 'We could not verify your payment. If money was deducted, it will be confirmed automatically within a few minutes or refunded.');
    redirect('pay.php?no=' . urlencode($o['order_no']) . '&t=' . $o['access_token']);
}

$order = mark_order_paid((int)$o['id'], $paymentId, 'checkout');
unset($_SESSION['checkout_form']);
redirect(order_view_url($order) . '&new=1');
