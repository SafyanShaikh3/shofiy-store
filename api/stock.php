<?php
/**
 * Live stock & price feed. The storefront polls this every few seconds so customers always see
 * current availability (and price changes / deal endings) before they reach checkout.
 * GET api/stock.php?ids=1,2,3
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
session_write_close(); // don't block other requests from this visitor

release_expired_reservations();

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)get('ids'))))));
$ids = array_slice($ids, 0, 60);
$out = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q("SELECT * FROM products WHERE id IN ($in)", $ids)->fetchAll() as $p) {
        $st = stock_state($p);
        $cmp = compare_price($p);
        $out[(int)$p['id']] = [
            'state' => $st['code'],
            'label' => $st['label'],
            'max'   => max_orderable($p),
            'price' => money(effective_price($p)),
            'compare' => $cmp ? money($cmp) : null,
            'off'   => discount_percent($p),
            'deal_ends' => special_active($p) ? date('c', strtotime($p['special_ends_at'])) : null,
        ];
    }
    foreach ($ids as $id) if (!isset($out[$id])) $out[$id] = ['state' => 'out', 'label' => 'Unavailable', 'max' => 0, 'price' => null];
}
json_out(['ok' => true, 'at' => date('c'), 'products' => $out]);
