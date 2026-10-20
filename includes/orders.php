<?php
declare(strict_types=1);

/*
 * Order lifecycle
 * ----------------
 * Online (Razorpay):  pending_payment --(paid)--> placed --> processing --> shipped --> delivered
 *                     pending_payment --(reservation timeout)--> expired (stock returned)
 * Cash on delivery:   placed (payment_status=cod) --> ... --> delivered (admin marks paid)
 *
 * `stock_reserved = 1` means the order's quantities are currently deducted from product stock.
 * Stock is taken the moment the order is created, so two customers can never buy the last unit.
 */

function generate_order_no(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $suffix = '';
        for ($i = 0; $i < 5; $i++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $no = 'EW' . date('ymd') . '-' . $suffix;
    } while (q_val('SELECT 1 FROM orders WHERE order_no = ?', [$no]));
    return $no;
}

function order_items(int $orderId): array
{
    return q('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId])->fetchAll();
}

/** Return stock to products and mark the order with the given status. Safe to call repeatedly. */
function cancel_order(int $orderId, string $newStatus = 'cancelled', bool $evenIfPaid = false): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = q_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) { $pdo->rollBack(); return false; }
        if ($o['payment_status'] === 'paid' && !$evenIfPaid) { $pdo->rollBack(); return false; }
        if ($o['stock_reserved']) {
            foreach (order_items($orderId) as $it) {
                if ($it['product_id']) q('UPDATE products SET stock = stock + ? WHERE id = ? AND track_stock = 1', [$it['qty'], $it['product_id']]);
            }
        }
        q('UPDATE orders SET order_status = ?, stock_reserved = 0, reserved_until = NULL WHERE id = ?', [$newStatus, $orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Unpaid online orders hold stock for a few minutes; give it back when time runs out. */
function release_expired_reservations(): void
{
    $ids = q("SELECT id FROM orders WHERE order_status = 'pending_payment' AND payment_status <> 'paid'
              AND stock_reserved = 1 AND reserved_until IS NOT NULL AND reserved_until < ? LIMIT 50", [now()])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) cancel_order((int)$id, 'expired');
}

/**
 * Creates an order from the current cart. Prices and stock are read from the database inside
 * a transaction with row locks, never from the browser.
 * @throws RuntimeException with a customer-friendly message
 */
function create_order_from_cart(array $c, string $method): array
{
    release_expired_reservations();
    $token = cart_token();

    // Abandoned unpaid attempts from this same cart give their stock back first.
    foreach (q("SELECT id FROM orders WHERE cart_token = ? AND order_status = 'pending_payment' AND payment_status <> 'paid'", [$token])->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
        cancel_order((int)$oldId, 'expired');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $rows = q('SELECT c.qty AS cart_qty, p.* FROM cart_items c JOIN products p ON p.id = c.product_id
                   WHERE c.cart_token = ? ORDER BY p.id FOR UPDATE', [$token])->fetchAll();
        if (!$rows) throw new RuntimeException('Your cart is empty.');

        $subtotal = 0.0; $items = [];
        foreach ($rows as $p) {
            $qty = (int)$p['cart_qty'];
            if (!$p['is_active']) throw new RuntimeException($p['name'] . ' is no longer available. Please remove it from your cart.');
            if ($p['track_stock'] && (int)$p['stock'] < $qty) {
                throw new RuntimeException((int)$p['stock'] <= 0
                    ? $p['name'] . ' just sold out. Please remove it from your cart.'
                    : 'Only ' . (int)$p['stock'] . ' of ' . $p['name'] . ' left. Please update your cart.');
            }
            $price = effective_price($p);
            $line = round($price * $qty, 2);
            $subtotal += $line;
            $items[] = ['p' => $p, 'qty' => $qty, 'price' => $price, 'line' => $line];
        }
        $shipping = shipping_for($subtotal);
        $total = round($subtotal + $shipping, 2);

        if ($method === 'cod') {
            $codMax = (float)setting('cod_max_amount', '0');
            if ($codMax > 0 && $total > $codMax) throw new RuntimeException('Cash on delivery is available for orders up to ' . money($codMax) . '. Please pay online.');
        }

        $isCod = $method === 'cod';
        $minutes = max(5, (int)setting('reservation_minutes', '20'));
        $orderNo = generate_order_no();
        $access = bin2hex(random_bytes(16));

        q('INSERT INTO orders (order_no, access_token, cart_token, user_id, customer_name, email, phone, address_line1, address_line2,
              city, state, pincode, country, notes, subtotal, shipping_fee, total, payment_method, payment_status, order_status,
              stock_reserved, reserved_until, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $orderNo, $access, $token, current_user()['id'] ?? null, $c['name'], $c['email'], $c['phone'], $c['address1'], $c['address2'] ?: null,
            $c['city'], $c['state'], $c['pincode'], $c['country'] ?: 'India', $c['notes'] ?: null, $subtotal, $shipping, $total,
            $method, $isCod ? 'cod' : 'pending', $isCod ? 'placed' : 'pending_payment',
            1, $isCod ? null : date('Y-m-d H:i:s', time() + $minutes * 60), now(),
        ]);
        $orderId = (int)$pdo->lastInsertId();

        foreach ($items as $it) {
            q('INSERT INTO order_items (order_id, product_id, name, image, unit_price, qty, line_total) VALUES (?,?,?,?,?,?,?)',
              [$orderId, $it['p']['id'], $it['p']['name'], $it['p']['image_1'], $it['price'], $it['qty'], $it['line']]);
            if ($it['p']['track_stock']) {
                q('UPDATE products SET stock = stock - ? WHERE id = ?', [$it['qty'], $it['p']['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if ($isCod) {
        cart_clear($token);
        send_order_emails($order);
    }
    return $order;
}

/**
 * Marks an order as paid. Idempotent — called from the browser callback AND the webhook.
 */
function mark_order_paid(int $orderId, string $paymentId, string $source = 'checkout'): array
{
    $pdo = db();
    $pdo->beginTransaction();
    $justPaid = false;
    try {
        $o = q_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) throw new RuntimeException('Order not found');
        if ($o['payment_status'] !== 'paid') {
            $note = null;
            $reserved = (int)$o['stock_reserved'];
            if (!$reserved) {
                // Reservation had expired; try to take the stock again.
                $items = order_items($orderId); $ok = true;
                foreach ($items as $it) {
                    if (!$it['product_id']) continue;
                    $p = q_one('SELECT stock, track_stock FROM products WHERE id = ? FOR UPDATE', [$it['product_id']]);
                    if ($p && $p['track_stock'] && (int)$p['stock'] < (int)$it['qty']) $ok = false;
                }
                if ($ok) {
                    foreach ($items as $it) if ($it['product_id']) q('UPDATE products SET stock = stock - ? WHERE id = ? AND track_stock = 1', [$it['qty'], $it['product_id']]);
                    $reserved = 1;
                } else {
                    $note = 'ATTENTION: payment arrived after the stock hold expired and an item sold out. Contact the customer or refund.';
                }
            }
            q("UPDATE orders SET payment_status = 'paid', order_status = IF(order_status IN ('pending_payment','expired','cancelled'), 'placed', order_status),
                 razorpay_payment_id = ?, paid_at = ?, reserved_until = NULL, stock_reserved = ?,
                 admin_note = TRIM(CONCAT_WS(' ', admin_note, ?)) WHERE id = ?",
              [$paymentId, now(), $reserved, $note, $orderId]);
            $justPaid = true;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if ($justPaid) {
        // Remove the purchased products from the cart they came from
        if ($order['cart_token']) {
            $pids = array_filter(array_column(order_items($orderId), 'product_id'));
            if ($pids) {
                $in = implode(',', array_fill(0, count($pids), '?'));
                q("DELETE FROM cart_items WHERE cart_token = ? AND product_id IN ($in)", array_merge([$order['cart_token']], array_values($pids)));
            }
        }
        send_order_emails($order);
    }
    return $order;
}

function order_view_url(array $o): string
{
    return 'order.php?no=' . urlencode($o['order_no']) . '&t=' . $o['access_token'];
}

function send_order_emails(array $o): void
{
    $items = order_items((int)$o['id']);
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:6px 0">' . e($it['name']) . ' × ' . (int)$it['qty'] . '</td><td style="text-align:right">' . e(money($it['line_total'])) . '</td></tr>';
    }
    $table = '<table style="width:100%;border-collapse:collapse">' . $rows
        . '<tr><td style="padding-top:8px;border-top:1px solid #ddd">Shipping</td><td style="text-align:right;border-top:1px solid #ddd">' . e(money($o['shipping_fee'])) . '</td></tr>'
        . '<tr><td><b>Total</b></td><td style="text-align:right"><b>' . e(money($o['total'])) . '</b></td></tr></table>';
    $store = setting('store_name', 'Store');
    $link = abs_url(order_view_url($o));
    $pay = $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'Paid online (Razorpay)';

    send_mail($o['email'], "Order {$o['order_no']} confirmed — $store",
        '<h2>Thanks for your order, ' . e($o['customer_name']) . '!</h2><p>Order <b>' . e($o['order_no']) . '</b> · ' . e($pay) . '</p>' . $table
        . '<p>Delivering to: ' . e($o['address_line1'] . ', ' . $o['city'] . ', ' . $o['state'] . ' ' . $o['pincode']) . '</p>'
        . '<p><a href="' . e($link) . '" style="background:#2f6fed;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Track your order</a></p>');

    if (setting('order_email_notify', '1') === '1' && setting('store_email')) {
        send_mail(setting('store_email'), "New order {$o['order_no']} — " . money($o['total']),
            '<h2>New order received</h2><p><b>' . e($o['order_no']) . '</b> from ' . e($o['customer_name']) . ' (' . e($o['phone']) . ')</p>' . $table
            . '<p>' . e($pay) . '</p><p><a href="' . e(abs_url('admin/order_view.php?id=' . $o['id'])) . '">Open in admin</a></p>');
    }
}
