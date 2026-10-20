<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('cart.php');
csrf_check();

$action = post('action');
$pid = (int)post('product_id');
$qty = (int)post('qty', '1');
$res = ['ok' => false, 'msg' => 'Unknown action'];

switch ($action) {
    case 'add':
        $res = cart_add($pid, max(1, $qty));
        // "Buy now" with the item already in the cart should still go to checkout
        if (!$res['ok'] && post('buy_now') && q_val('SELECT 1 FROM cart_items WHERE cart_token = ? AND product_id = ?', [cart_token(), $pid])) {
            $res = ['ok' => true, 'msg' => 'Already in your cart'];
        }
        break;
    case 'set':
        $res = cart_set($pid, $qty);
        break;
    case 'remove':
        cart_remove($pid);
        $res = ['ok' => true, 'msg' => 'Removed from cart'];
        break;
}

if (wants_json()) {
    $lines = cart_lines();
    $t = cart_totals($lines);
    $line = null;
    foreach ($lines as $l) if ((int)$l['product']['id'] === $pid) $line = $l;
    json_out($res + [
        'count'    => $t['count'],
        'subtotal' => money($t['subtotal']),
        'shipping' => $t['shipping'] > 0 ? money($t['shipping']) : 'Free',
        'total'    => money($t['total']),
        'problems' => $t['problems'],
        'line'     => $line ? ['qty' => $line['qty'], 'total' => money($line['line_total']), 'problem' => $line['problem']] : null,
    ]);
}

flash($res['ok'] ? 'success' : 'error', $res['msg']);
if ($res['ok'] && post('buy_now')) redirect('checkout.php');
$back = $_SERVER['HTTP_REFERER'] ?? '';
redirect($back && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '') ? $back : url('cart.php'));
