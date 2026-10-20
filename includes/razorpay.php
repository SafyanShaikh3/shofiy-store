<?php
declare(strict_types=1);

/**
 * Razorpay integration using the official REST API (no Composer needed).
 * Docs: https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/
 *
 * Flow: server creates a Razorpay Order for the exact amount -> Razorpay Checkout opens in the
 * browser -> on success we verify the HMAC signature server-side -> order is marked paid.
 * A webhook (webhook.php) also confirms payments in case the customer closes the browser early.
 */

function rzp_key_id(): string
{
    return (string)(cfg('razorpay_key_id') ?: setting('razorpay_key_id', ''));
}

function rzp_key_secret(): string
{
    return (string)(cfg('razorpay_key_secret') ?: setting('razorpay_key_secret', ''));
}

function rzp_webhook_secret(): string
{
    return (string)(cfg('razorpay_webhook_secret') ?: setting('razorpay_webhook_secret', ''));
}

function rzp_enabled(): bool
{
    return setting('enable_razorpay', '1') === '1' && rzp_key_id() !== '' && rzp_key_secret() !== '';
}

function rzp_test_mode(): bool
{
    return str_starts_with(rzp_key_id(), 'rzp_test_');
}

/** @throws RuntimeException */
function rzp_api(string $method, string $path, array $body = []): array
{
    $base = rtrim((string)cfg('razorpay_api_base', 'https://api.razorpay.com/v1'), '/');
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => rzp_key_id() . ':' . rzp_key_secret(),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Could not reach the payment gateway: ' . $err);
    $data = json_decode((string)$raw, true);
    if ($code >= 400 || !is_array($data)) {
        $msg = $data['error']['description'] ?? ('HTTP ' . $code);
        throw new RuntimeException('Payment gateway error: ' . $msg);
    }
    return $data;
}

/** Creates a Razorpay order for an EWC order. Amount is taken from the DB, in paise. */
function rzp_create_order(array $order): string
{
    $res = rzp_api('POST', '/orders', [
        'amount'   => (int)round((float)$order['total'] * 100),
        'currency' => 'INR',
        'receipt'  => $order['order_no'],
        'notes'    => ['order_no' => $order['order_no'], 'order_id' => (string)$order['id']],
    ]);
    if (empty($res['id'])) throw new RuntimeException('Payment gateway did not return an order id.');
    q('UPDATE orders SET razorpay_order_id = ? WHERE id = ?', [$res['id'], $order['id']]);
    return $res['id'];
}

function rzp_verify_payment_signature(string $rzpOrderId, string $paymentId, string $signature): bool
{
    if ($rzpOrderId === '' || $paymentId === '' || $signature === '' || rzp_key_secret() === '') return false;
    $expected = hash_hmac('sha256', $rzpOrderId . '|' . $paymentId, rzp_key_secret());
    return hash_equals($expected, $signature);
}

function rzp_verify_webhook_signature(string $rawBody, string $signature): bool
{
    $secret = rzp_webhook_secret();
    if ($secret === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}
