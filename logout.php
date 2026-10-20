<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    unset($_SESSION['user_id'], $_SESSION['checkout_form']);
    session_regenerate_id(true);
    // Fresh cart for this browser so the next person on a shared device doesn't see it
    if (!headers_sent()) setcookie(CART_COOKIE, '', ['expires' => time() - 3600, 'path' => base_path()]);
    flash('success', 'You have been logged out.');
}
redirect('');
