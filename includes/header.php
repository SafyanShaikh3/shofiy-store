<?php
/** @var string $page_title */
$page_title = $page_title ?? '';
$page_desc  = $page_desc ?? setting('tagline');
$store      = setting('store_name', 'Store');
$brand      = preg_match('/^#[0-9a-fA-F]{6}$/', setting('theme_color', '')) ? setting('theme_color') : '#2f6fed';
$cartCount  = cart_count();
$user       = current_user();
$navCats    = q('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$script     = basename($_SERVER['SCRIPT_NAME'] ?? '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($page_title ? $page_title . ' · ' . $store : $store . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<meta name="theme-color" content="<?= e($brand) ?>">
<meta property="og:title" content="<?= e($page_title ?: $store) ?>">
<meta property="og:description" content="<?= e($page_desc) ?>">
<?php if (!empty($og_image)): ?><meta property="og:image" content="<?= e($og_image) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<style>:root{--brand:<?= e($brand) ?>}</style>
</head>
<body class="<?= e($body_class ?? '') ?>" data-base="<?= e(url('')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-poll="<?= (int)setting('stock_poll_seconds', '15') ?>" data-currency="<?= e(setting('currency_symbol', '₹')) ?>">
<a class="skip" href="#main">Skip to content</a>
<?php if (setting('announcement')): ?>
<div class="announce"><?= e(setting('announcement')) ?></div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-row">
    <button class="icon-btn menu-btn" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="drawer" data-open-drawer><?= icon('menu') ?></button>
    <a class="logo" href="<?= e(url('')) ?>"><?= e($store) ?><span>.</span></a>
    <form class="search" action="<?= e(url('shop.php')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="Search laptops, TVs, phones…" value="<?= e($script === 'shop.php' ? get('q') : '') ?>" aria-label="Search products">
      <button type="submit" aria-label="Search"><?= icon('search', 20) ?></button>
    </form>
    <nav class="header-icons" aria-label="Account">
      <a class="icon-btn hide-sm" href="<?= e(url('wishlist.php')) ?>" aria-label="Wishlist"><?= icon('heart') ?></a>
      <a class="icon-btn" href="<?= e(url($user ? 'account.php' : 'login.php')) ?>" aria-label="<?= $user ? 'My account' : 'Log in' ?>"><?= icon('user') ?></a>
      <a class="icon-btn cart-link" href="<?= e(url('cart.php')) ?>" aria-label="Cart">
        <?= icon('cart') ?><span class="badge" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
      </a>
    </nav>
  </div>
  <nav class="main-nav" aria-label="Main">
    <div class="wrap">
      <a href="<?= e(url('')) ?>" class="<?= $script === 'index.php' ? 'active' : '' ?>">Home</a>
      <a href="<?= e(url('shop.php')) ?>" class="<?= $script === 'shop.php' && !get('deals') ? 'active' : '' ?>">Shop all</a>
      <a href="<?= e(url('shop.php?deals=1')) ?>" class="deal-link <?= get('deals') ? 'active' : '' ?>"><?= icon('bolt', 16) ?> Today's deals</a>
      <?php foreach (array_slice($navCats, 0, 6) as $c): ?>
        <a href="<?= e(url('shop.php?cat=' . urlencode($c['slug']))) ?>" class="<?= get('cat') === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('track.php')) ?>" class="<?= $script === 'track.php' ? 'active' : '' ?>">Track order</a>
    </div>
  </nav>
</header>

<div class="drawer" id="drawer" hidden>
  <div class="drawer-backdrop" data-close-drawer></div>
  <aside class="drawer-panel" role="dialog" aria-label="Menu">
    <div class="drawer-head">
      <span class="logo"><?= e($store) ?><span>.</span></span>
      <button class="icon-btn" type="button" aria-label="Close menu" data-close-drawer><?= icon('close') ?></button>
    </div>
    <nav class="drawer-nav">
      <a href="<?= e(url('')) ?>">Home</a>
      <a href="<?= e(url('shop.php')) ?>">Shop all</a>
      <a href="<?= e(url('shop.php?deals=1')) ?>" class="deal-link"><?= icon('bolt', 16) ?> Today's deals</a>
      <p class="drawer-label">Categories</p>
      <?php foreach ($navCats as $c): ?>
        <a href="<?= e(url('shop.php?cat=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <p class="drawer-label">Account</p>
      <?php if ($user): ?>
        <a href="<?= e(url('account.php')) ?>">My account</a>
        <a href="<?= e(url('orders.php')) ?>">My orders</a>
        <a href="<?= e(url('wishlist.php')) ?>">Wishlist</a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Log in</a>
        <a href="<?= e(url('register.php')) ?>">Create account</a>
      <?php endif; ?>
      <a href="<?= e(url('track.php')) ?>">Track an order</a>
      <a href="<?= e(url('contact.php')) ?>">Contact us</a>
    </nav>
  </aside>
</div>

<div class="toasts" aria-live="polite">
<?php foreach (flashes() as $f): ?>
  <div class="toast toast-<?= e($f['type']) ?>"><?= e($f['msg']) ?><button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">×</button></div>
<?php endforeach; ?>
</div>

<main id="main">
