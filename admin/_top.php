<?php
/** Admin layout header. Expects $admin (from require_admin()) and $page_title. */
$store = setting('store_name', 'Store');
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$unread = (int)q_val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
$toProcess = (int)q_val("SELECT COUNT(*) FROM orders WHERE order_status IN ('placed')");
$nav = [
    'index.php'       => ['Dashboard', 'grid'],
    'orders.php'      => ['Orders', 'box', $toProcess],
    'products.php'    => ['Products & stock', 'tag'],
    'specials.php'    => ["Today's specials", 'bolt'],
    'categories.php'  => ['Categories', 'folder'],
    'banners.php'     => ['Home banners', 'image'],
    'messages.php'    => ['Messages', 'mail', $unread],
    'customers.php'   => ['Customers', 'users'],
    'settings.php'    => ['Settings & payments', 'gear'],
    'admins.php'      => ['Admin users', 'shield'],
];
$aliases = ['product_edit.php' => 'products.php', 'order_view.php' => 'orders.php'];
$active = $aliases[$cur] ?? $cur;
function aicon(string $n): string {
    $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'folder' => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c1.8.8 3 2.5 3.5 5.2"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.2 8 9 4.6-.8 8-4.5 8-9V6z"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    ][$n] ?? '';
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($page_title ?? 'Admin') . ' · ' . $store . ' Admin') ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body data-csrf="<?= e(csrf_token()) ?>" data-base="<?= e(url('')) ?>">
<div class="admin">
  <aside class="sidebar" id="sidebar">
    <div class="side-head">
      <a class="brand" href="<?= e(url('admin/')) ?>"><?= e($store) ?><span>.</span> <small>Admin</small></a>
    </div>
    <nav>
      <?php foreach ($nav as $file => $n): ?>
        <a href="<?= e(url('admin/' . $file)) ?>" class="<?= $active === $file ? 'active' : '' ?>"><?= aicon($n[1]) ?><span><?= e($n[0]) ?></span><?php if (!empty($n[2])): ?><b class="count"><?= (int)$n[2] ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= aicon('ext') ?> View store</a>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit">Log out (<?= e($admin['username']) ?>)</button></form>
    </div>
  </aside>
  <div class="side-backdrop" data-close-side></div>
  <div class="main">
    <header class="topbar">
      <button class="menu" type="button" aria-label="Menu" data-open-side><?= aicon('menu') ?></button>
      <h1><?= e($page_title ?? '') ?></h1>
      <?php if (!empty($top_actions)) echo $top_actions; ?>
    </header>
    <?php if (!rzp_enabled() && $cur !== 'settings.php'): ?>
      <div class="banner-warn">Online payments are not active yet. <a href="<?= e(url('admin/settings.php#payments')) ?>">Add your Razorpay keys</a> to accept UPI & card payments.</div>
    <?php elseif (rzp_test_mode() && $cur === 'index.php'): ?>
      <div class="banner-info">Razorpay is in <b>test mode</b> — no real money is collected. Switch to live keys in <a href="<?= e(url('admin/settings.php#payments')) ?>">Settings</a> when you're ready.</div>
    <?php endif; ?>
    <div class="flash-area">
      <?php foreach (flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?><button type="button" onclick="this.parentElement.remove()" aria-label="Dismiss">×</button></div>
      <?php endforeach; ?>
    </div>
    <div class="content">
