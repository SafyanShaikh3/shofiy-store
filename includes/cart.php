<?php
declare(strict_types=1);

/**
 * Cart is stored in the database, keyed by a long random cookie token (works for guests,
 * survives browser restarts for 30 days). When a customer logs in, the cart follows them.
 */

const CART_COOKIE = 'ewc_cart';

function cart_token(): string
{
    static $tok = null;
    if ($tok !== null) return $tok;
    $c = $_COOKIE[CART_COOKIE] ?? '';
    if (!is_string($c) || !preg_match('/^[a-f0-9]{48}$/', $c)) {
        $c = bin2hex(random_bytes(24));
        if (!headers_sent() && PHP_SAPI !== 'cli') {
            setcookie(CART_COOKIE, $c, [
                'expires' => time() + 60 * 60 * 24 * 30, 'path' => base_path(),
                'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[CART_COOKIE] = $c;
    }
    return $tok = $c;
}

/** Move this browser's cart to the user and pull in items saved on their account earlier. */
function cart_attach_user(int $userId): void
{
    $tok = cart_token();
    // Bring over items the user left in another browser
    $old = q('SELECT product_id, qty, cart_token FROM cart_items WHERE user_id = ? AND cart_token <> ?', [$userId, $tok])->fetchAll();
    foreach ($old as $row) {
        q('INSERT INTO cart_items (cart_token, user_id, product_id, qty) VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE qty = GREATEST(qty, VALUES(qty))', [$tok, $userId, $row['product_id'], $row['qty']]);
    }
    q('DELETE FROM cart_items WHERE user_id = ? AND cart_token <> ?', [$userId, $tok]);
    q('UPDATE cart_items SET user_id = ? WHERE cart_token = ?', [$userId, $tok]);
}

/** Cart lines joined with live product data. Prices always come from the products table. */
function cart_lines(): array
{
    $rows = q('SELECT c.id AS line_id, c.qty, p.* FROM cart_items c
               JOIN products p ON p.id = c.product_id
               WHERE c.cart_token = ? ORDER BY c.id', [cart_token()])->fetchAll();
    $lines = [];
    foreach ($rows as $r) {
        $price = effective_price($r);
        $st = stock_state($r);
        $max = max_orderable($r);
        $lines[] = [
            'product'    => $r,
            'qty'        => (int)$r['qty'],
            'unit_price' => $price,
            'line_total' => $price * (int)$r['qty'],
            'stock'      => $st,
            'max'        => $max,
            'problem'    => $max === 0 ? 'This item is currently unavailable.' : ((int)$r['qty'] > $max ? 'Only ' . $max . ' available — please reduce the quantity.' : null),
        ];
    }
    return $lines;
}

function cart_totals(array $lines): array
{
    $sub = 0.0; $count = 0; $problems = 0;
    foreach ($lines as $l) { $sub += $l['line_total']; $count += $l['qty']; if ($l['problem']) $problems++; }
    $ship = shipping_for($sub);
    return ['subtotal' => $sub, 'shipping' => $ship, 'total' => $sub + $ship, 'count' => $count, 'problems' => $problems];
}

function cart_count(): int
{
    if (empty($_COOKIE[CART_COOKIE])) return 0;
    return (int)q_val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_token = ?', [cart_token()]);
}

/** @return array{ok:bool, msg:string} */
function cart_add(int $productId, int $qty): array
{
    $p = q_one('SELECT * FROM products WHERE id = ?', [$productId]);
    if (!$p || !$p['is_active']) return ['ok' => false, 'msg' => 'This product is no longer available.'];
    $qty = max(1, min(99, $qty));
    $max = max_orderable($p);
    if ($max === 0) return ['ok' => false, 'msg' => 'Sorry, ' . $p['name'] . ' is out of stock.'];
    $existing = (int)q_val('SELECT qty FROM cart_items WHERE cart_token = ? AND product_id = ?', [cart_token(), $productId]);
    $newQty = $existing + $qty;
    if ($newQty > $max) {
        return ['ok' => false, 'msg' => 'Only ' . $max . ' of this item available' . ($existing ? ' (you already have ' . $existing . ' in your cart).' : '.')];
    }
    $uid = current_user()['id'] ?? null;
    q('INSERT INTO cart_items (cart_token, user_id, product_id, qty) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE qty = ?, user_id = VALUES(user_id)', [cart_token(), $uid, $productId, $newQty, $newQty]);
    return ['ok' => true, 'msg' => 'Added to cart'];
}

function cart_set(int $productId, int $qty): array
{
    if ($qty <= 0) { cart_remove($productId); return ['ok' => true, 'msg' => 'Removed from cart']; }
    $p = q_one('SELECT * FROM products WHERE id = ?', [$productId]);
    if (!$p) { cart_remove($productId); return ['ok' => false, 'msg' => 'Product not found.']; }
    $max = max_orderable($p);
    if ($max === 0) return ['ok' => false, 'msg' => 'This item is out of stock.'];
    $clamped = min($qty, $max);
    q('UPDATE cart_items SET qty = ? WHERE cart_token = ? AND product_id = ?', [$clamped, cart_token(), $productId]);
    return $clamped < $qty
        ? ['ok' => false, 'msg' => 'Only ' . $max . ' available — quantity adjusted.']
        : ['ok' => true, 'msg' => 'Cart updated'];
}

function cart_remove(int $productId): void
{
    q('DELETE FROM cart_items WHERE cart_token = ? AND product_id = ?', [cart_token(), $productId]);
}

function cart_clear(?string $token = null): void
{
    q('DELETE FROM cart_items WHERE cart_token = ?', [$token ?? cart_token()]);
}

function wishlist_ids(): array
{
    $u = current_user();
    if (!$u) return [];
    return array_map('intval', q('SELECT product_id FROM wishlist WHERE user_id = ?', [$u['id']])->fetchAll(PDO::FETCH_COLUMN));
}
