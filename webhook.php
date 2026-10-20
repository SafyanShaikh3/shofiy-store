<?php
/**
 * Razorpay webhook endpoint.
 * In Razorpay Dashboard -> Settings -> Webhooks, add:  https://YOUR-DOMAIN/webhook.php
 * Events: payment.captured, order.paid, payment.failed   Secret: same as in Admin -> Settings.
 *
 * This confirms payments even if the customer closes the browser before returning to the site.
 */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) { http_response_code(405); exit('Method not allowed'); }

$raw = (string)file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if (!rzp_verify_webhook_signature($raw, $sig)) {
    error_log('[EWC] Webhook signature mismatch');
    http_response_code(400);
    exit('Invalid signature');
}

$data = json_decode($raw, true);
$event = (string)($data['event'] ?? '');
$eventId = (string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? ($event . ':' . hash('sha256', $raw)));

$payment = $data['payload']['payment']['entity'] ?? [];
$rzpOrderId = (string)($data['payload']['order']['entity']['id'] ?? ($payment['order_id'] ?? ''));

// Idempotency: ignore events we've already processed
try {
    q('INSERT INTO payment_events (event_id, event, razorpay_order_id, payload, created_at) VALUES (?, ?, ?, ?, ?)',
      [mb_substr($eventId, 0, 80), mb_substr($event, 0, 60), $rzpOrderId ?: null, $raw, now()]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1062) { http_response_code(200); exit('Duplicate'); }
    throw $e;
}

if (in_array($event, ['payment.captured', 'order.paid'], true) && $rzpOrderId !== '') {
    $o = q_one('SELECT * FROM orders WHERE razorpay_order_id = ?', [$rzpOrderId]);
    if ($o) {
        $paidPaise = (int)($payment['amount'] ?? 0);
        $expected = (int)round((float)$o['total'] * 100);
        if ($paidPaise && $paidPaise !== $expected) {
            q("UPDATE orders SET admin_note = TRIM(CONCAT_WS(' ', admin_note, ?)) WHERE id = ?",
              ['ATTENTION: paid amount ' . ($paidPaise / 100) . ' differs from order total.', $o['id']]);
        }
        mark_order_paid((int)$o['id'], (string)($payment['id'] ?? ''), 'webhook');
    }
} elseif ($event === 'payment.failed' && $rzpOrderId !== '') {
    // Customer can retry on the payment page; nothing to change. Logged in payment_events.
}

http_response_code(200);
echo 'OK';
