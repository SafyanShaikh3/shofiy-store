<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
    session_regenerate_id(true);
    flash('success', 'Logged out.');
}
redirect('admin/login.php');
