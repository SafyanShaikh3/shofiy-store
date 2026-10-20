<?php
/**
 * Loaded at the top of every page. Sets up config, errors, session and DB.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '2.0.0');

if (!is_file(APP_ROOT . '/config.php')) {
    $inAdmin = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false
            || strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false;
    header('Location: ' . ($inAdmin ? '../' : '') . 'install.php');
    exit;
}

$GLOBALS['config'] = require APP_ROOT . '/config.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Asia/Kolkata');

if (!empty($GLOBALS['config']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/razorpay.php';
require_once __DIR__ . '/product_card.php';

// ---- Session (secure cookie flags) ----
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    $secure = is_https();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7));
    session_name('EWCSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path(),
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Security headers ----
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

set_exception_handler(function (Throwable $e) {
    error_log('[EWC] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    if (!empty($GLOBALS['config']['debug'])) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><div style="font-family:system-ui;max-width:480px;margin:15vh auto;padding:24px;text-align:center"><h1>Something went wrong</h1><p>Please try again in a moment.</p><p><a href="' . htmlspecialchars(url('')) . '">Back to home</a></p></div>';
    }
    exit;
});
