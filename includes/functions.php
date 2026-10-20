<?php
declare(strict_types=1);

/* =========================================================
 *  Core helpers
 * ======================================================= */

function cfg(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config']['db'];
        $dsn = 'mysql:host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL's clock in the same timezone as PHP
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function base_path(): string
{
    $b = (string)cfg('base_path', '/');
    return '/' . trim($b, '/') . (trim($b, '/') === '' ? '' : '/');
}

/** Site-relative URL, e.g. url('shop.php') => /shop.php or /store/shop.php */
function url(string $path = ''): string
{
    return base_path() . ltrim($path, '/');
}

/** Absolute URL (for emails, webhooks) */
function abs_url(string $path = ''): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? parse_url((string)cfg('site_url', 'http://localhost'), PHP_URL_HOST);
    if (cfg('site_url')) {
        return rtrim((string)cfg('site_url'), '/') . '/' . ltrim($path, '/');
    }
    return $scheme . '://' . $host . url($path);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

function upload_url(?string $file, string $dir = 'products'): string
{
    if (!$file) return url('assets/img/placeholder.svg');
    return url('uploads/' . $dir . '/' . rawurlencode($file));
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~', $path) && $path[0] !== '/') $path = url($path);
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $k, $default = '')
{
    $v = $_POST[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $k, $default = '')
{
    $v = $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function wants_json(): bool
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
 *  Settings (editable from admin)
 * ======================================================= */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (q('SELECT k, v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function setting_set(string $key, $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, (string)$value]);
}

function default_settings(): array
{
    return [
        'store_name'          => 'Shofiy',
        'tagline'             => 'Electronics & home appliances, delivered.',
        'theme_color'         => '#2f6fed',
        'announcement'        => 'Free delivery on orders above ₹999 · Secure payments by Razorpay',
        'store_phone'         => '+91 70695 00011',
        'store_phone_2'       => '',
        'store_email'         => 'safyanshaikh009@gmail.com',
        'store_address'       => 'Vesma, India',
        'whatsapp'            => '',
        'social_facebook'     => '',
        'social_instagram'    => '',
        'social_twitter'      => '',
        'social_youtube'      => '',
        'currency_symbol'     => '₹',
        'shipping_fee'        => '49',
        'free_shipping_over'  => '999',
        'enable_cod'          => '1',
        'cod_max_amount'      => '20000',
        'enable_razorpay'     => '1',
        'razorpay_key_id'     => '',
        'razorpay_key_secret' => '',
        'razorpay_webhook_secret' => '',
        'reservation_minutes' => '20',
        'low_stock_default'   => '5',
        'stock_poll_seconds'  => '15',
        'about_text'          => "We are a team of passionate engineers and innovators dedicated to bringing you quality electronics and home appliances at honest prices.\n\nEvery product we sell is genuine, comes with the manufacturer's warranty, and is shipped quickly and safely to your door.",
        'policy_terms'        => '',
        'policy_privacy'      => '',
        'policy_refund'       => '',
        'policy_shipping'     => '',
        'order_email_notify'  => '1',
    ];
}

/* =========================================================
 *  Money & pricing
 * ======================================================= */

/** Indian number formatting: 1,23,456.50 */
function inr_format(float $n, bool $forceDecimals = false): string
{
    $neg = $n < 0; $n = abs($n);
    $whole = (int)floor($n + 0.0000001);
    $dec = (int)round(($n - $whole) * 100);
    if ($dec === 100) { $whole++; $dec = 0; }
    $s = (string)$whole;
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $s = $rest . ',' . $last3;
    }
    if ($dec > 0 || $forceDecimals) $s .= '.' . str_pad((string)$dec, 2, '0', STR_PAD_LEFT);
    return ($neg ? '-' : '') . $s;
}

function money($n): string
{
    return setting('currency_symbol', '₹') . inr_format((float)$n);
}

function special_active(array $p): bool
{
    return $p['special_price'] !== null && $p['special_price'] !== ''
        && (float)$p['special_price'] > 0
        && (float)$p['special_price'] < (float)$p['price']
        && !empty($p['special_ends_at'])
        && strtotime($p['special_ends_at']) > time();
}

/** The price the customer actually pays right now (always computed server-side). */
function effective_price(array $p): float
{
    return special_active($p) ? (float)$p['special_price'] : (float)$p['price'];
}

/** The "was" price to strike through, if any. */
function compare_price(array $p): ?float
{
    $eff = effective_price($p);
    $cands = [];
    if (special_active($p)) $cands[] = (float)$p['price'];
    if (!empty($p['mrp']) && (float)$p['mrp'] > $eff) $cands[] = (float)$p['mrp'];
    return $cands ? max($cands) : null;
}

function discount_percent(array $p): int
{
    $cmp = compare_price($p);
    if (!$cmp) return 0;
    return (int)round((1 - effective_price($p) / $cmp) * 100);
}

function shipping_for(float $subtotal): float
{
    $fee = (float)setting('shipping_fee', '0');
    $free = (float)setting('free_shipping_over', '0');
    if ($subtotal <= 0) return 0.0;
    if ($free > 0 && $subtotal >= $free) return 0.0;
    return $fee;
}

/* =========================================================
 *  Stock
 * ======================================================= */

/** Returns ['code' => in|low|out, 'label' => string, 'available' => int|null] */
function stock_state(array $p): array
{
    if (empty($p['is_active'])) return ['code' => 'out', 'label' => 'Unavailable', 'available' => 0];
    if (empty($p['track_stock'])) return ['code' => 'in', 'label' => 'In stock', 'available' => null];
    $s = (int)$p['stock'];
    if ($s <= 0) return ['code' => 'out', 'label' => 'Out of stock', 'available' => 0];
    $th = (int)($p['low_stock_threshold'] ?? 5);
    if ($s <= $th) return ['code' => 'low', 'label' => 'Only ' . $s . ' left', 'available' => $s];
    return ['code' => 'in', 'label' => 'In stock', 'available' => $s];
}

function max_orderable(array $p): int
{
    $st = stock_state($p);
    if ($st['code'] === 'out') return 0;
    return $st['available'] === null ? 99 : min(99, (int)$st['available']);
}

/* =========================================================
 *  CSRF & flash messages
 * ======================================================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        if (wants_json()) json_out(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419);
        http_response_code(419);
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? url(''));
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* =========================================================
 *  Auth (customers & admins)
 * ======================================================= */

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = !empty($_SESSION['user_id'])
            ? q_one('SELECT id, name, email, phone FROM users WHERE id = ?', [$_SESSION['user_id']])
            : null;
    }
    return $u;
}

function require_user(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $u;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    cart_attach_user($id);
}

function current_admin(): ?array
{
    static $a = false;
    if ($a === false) {
        $a = !empty($_SESSION['admin_id'])
            ? q_one('SELECT id, name, username FROM admins WHERE id = ?', [$_SESSION['admin_id']])
            : null;
    }
    return $a;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) redirect('admin/login.php');
    // Idle timeout: 2 hours
    if (!empty($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 7200) {
        unset($_SESSION['admin_id']);
        flash('info', 'You were logged out after inactivity.');
        redirect('admin/login.php');
    }
    $_SESSION['admin_seen'] = time();
    return $a;
}

/** Only allow redirecting to paths on this site. */
function safe_next(string $next, string $fallback = ''): string
{
    if ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\')) return $next;
    return url($fallback);
}

/* ---- Brute-force protection ---- */

function too_many_attempts(string $ident, int $max = 6, int $minutes = 15): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    return (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE ident = ? AND attempted_at > ?', [$ident, $since]) >= $max;
}

function record_attempt(string $ident): void
{
    q('INSERT INTO login_attempts (ident, attempted_at) VALUES (?, ?)', [$ident, now()]);
    if (random_int(1, 50) === 1) q('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

function clear_attempts(string $ident): void
{
    q('DELETE FROM login_attempts WHERE ident = ?', [$ident]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* =========================================================
 *  Validation helpers
 * ======================================================= */

function valid_email(string $e): bool
{
    return (bool)filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 120;
}

function clean_phone(string $p): string
{
    return preg_replace('/[^0-9+]/', '', $p);
}

function valid_phone(string $p): bool
{
    $digits = preg_replace('/\D/', '', $p);
    return strlen($digits) >= 10 && strlen($digits) <= 13;
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? substr($s, 0, 150) : 'item';
}

function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base); $i = 1; $try = $slug;
    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$try, $ignoreId] : [$try];
        if (!q_one($sql, $params)) return $try;
        $try = $slug . '-' . (++$i);
    }
}

/* =========================================================
 *  Image uploads (validated, resized and re-encoded)
 * ======================================================= */

/**
 * Validates an uploaded image, resizes it and saves it as WebP (or the original type
 * if WebP isn't available). Returns the stored filename or throws RuntimeException.
 */
function save_uploaded_image(array $file, string $dir, int $maxW = 1200): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $map = [UPLOAD_ERR_INI_SIZE => 'Image is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'Image is too large.', UPLOAD_ERR_PARTIAL => 'Upload was interrupted.'];
        throw new RuntimeException($map[$file['error']] ?? 'Image upload failed.');
    }
    if ($file['size'] > 8 * 1024 * 1024) throw new RuntimeException('Image must be under 8 MB.');
    if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') throw new RuntimeException('Invalid upload.');

    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($allowed[$info[2]])) throw new RuntimeException('Please upload a JPG, PNG, WebP or GIF image.');

    $target = APP_ROOT . '/uploads/' . $dir;
    if (!is_dir($target)) mkdir($target, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));

    if (function_exists('imagecreatetruecolor')) {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            default => false,
        };
        if ($src) {
            [$w, $h] = [imagesx($src), imagesy($src)];
            $scale = min(1, $maxW / max($w, $h));
            $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if (function_exists('imagewebp')) {
                $out = $name . '.webp';
                imagewebp($dst, "$target/$out", 84);
            } else {
                $out = $name . '.png';
                imagepng($dst, "$target/$out", 7);
            }
            imagedestroy($src); imagedestroy($dst);
            return $out;
        }
    }
    // Fallback: no GD — store the validated original with a safe name
    $out = $name . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$target/$out") && !copy($file['tmp_name'], "$target/$out")) {
        throw new RuntimeException('Could not save the image. Check folder permissions for uploads/.');
    }
    return $out;
}

function delete_upload(?string $file, string $dir): void
{
    if (!$file) return;
    $path = APP_ROOT . '/uploads/' . $dir . '/' . basename($file);
    if (is_file($path)) @unlink($path);
}

/* =========================================================
 *  Email (uses PHP mail(); works on most shared hosts)
 * ======================================================= */

function send_mail(string $to, string $subject, string $html): bool
{
    if (!valid_email($to)) return false;
    $from = setting('store_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $name = setting('store_name', 'Store');
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($name) . ' <' . $from . '>',
        'Reply-To: ' . $from,
    ];
    $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1f2937">' . $html . '</div>';
    try {
        return @mail($to, mb_encode_mimeheader($subject), $body, implode("\r\n", $headers));
    } catch (Throwable $e) {
        return false;
    }
}

/* =========================================================
 *  Misc UI helpers
 * ======================================================= */

function icon(string $name, int $size = 22): string
{
    $paths = [
        'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2.5 3h2.6l2.4 12.2a1 1 0 0 0 1 .8h9.4a1 1 0 0 0 1-.8L20.8 7H6"/>',
        'heart'   => '<path d="M12 20.5s-7.5-4.6-9.3-9.4C1.4 7.6 3.6 4 7.2 4c2 0 3.6 1.1 4.8 2.8C13.2 5.1 14.8 4 16.8 4c3.6 0 5.8 3.6 4.5 7.1-1.8 4.8-9.3 9.4-9.3 9.4z"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.2-4 4.4-6 8-6s6.8 2 8 6"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'truck'   => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'shield'  => '<path d="M12 3 4 6v6c0 4.5 3.4 8.2 8 9 4.6-.8 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.7-4.3L3 9M4 13a8 8 0 0 0 14.7 4.3L21 15"/><path d="M3 4v5h5M21 20v-5h-5"/>',
        'phone'   => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pin'     => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'bolt'    => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'box'     => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
        'check'   => '<path d="m5 12 5 5 9-10"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'whatsapp'=> '<path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3.2 3.1z"/><path d="M9 9.5c.3 2 2.5 4.3 4.8 4.8l1.2-1.2 1.8.8c-.2 1-1 1.7-2 1.6-3.4-.3-6.7-3.6-7-7-.1-1 .6-1.8 1.6-2l.8 1.8z"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ic ic-' . $name . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function order_status_label(string $s): string
{
    return [
        'pending_payment' => 'Awaiting payment',
        'placed'          => 'Order placed',
        'processing'      => 'Processing',
        'shipped'         => 'Shipped',
        'delivered'       => 'Delivered',
        'cancelled'       => 'Cancelled',
        'expired'         => 'Payment not completed',
    ][$s] ?? ucfirst($s);
}

function payment_status_label(string $s): string
{
    return [
        'pending'  => 'Pending',
        'paid'     => 'Paid',
        'failed'   => 'Failed',
        'refunded' => 'Refunded',
        'cod'      => 'Pay on delivery',
    ][$s] ?? ucfirst($s);
}

function nl2p(string $text): string
{
    $out = '';
    foreach (preg_split("/\R{2,}/", trim($text)) as $para) {
        if ($para === '') continue;
        $out .= '<p>' . nl2br(e($para)) . '</p>';
    }
    return $out;
}

/** Minimal markdown-ish rendering for policy pages: "## Heading", "- bullet", paragraphs. */
function render_text_block(string $text): string
{
    $html = ''; $list = false;
    foreach (preg_split("/\R/", trim($text)) as $line) {
        $t = trim($line);
        if (str_starts_with($t, '- ')) {
            if (!$list) { $html .= '<ul>'; $list = true; }
            $html .= '<li>' . e(substr($t, 2)) . '</li>';
            continue;
        }
        if ($list) { $html .= '</ul>'; $list = false; }
        if ($t === '') continue;
        if (str_starts_with($t, '## ')) $html .= '<h2>' . e(substr($t, 3)) . '</h2>';
        else $html .= '<p>' . e($t) . '</p>';
    }
    if ($list) $html .= '</ul>';
    return $html;
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function page_link(int $p): string
{
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

function render_pagination(array $pg): string
{
    if ($pg['pages'] <= 1) return '';
    $h = '<nav class="pagination" aria-label="Pages">';
    if ($pg['page'] > 1) $h .= '<a href="' . e(page_link($pg['page'] - 1)) . '">&lsaquo; Prev</a>';
    for ($i = max(1, $pg['page'] - 2); $i <= min($pg['pages'], $pg['page'] + 2); $i++) {
        $h .= $i === $pg['page'] ? '<span class="current">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>';
    }
    if ($pg['page'] < $pg['pages']) $h .= '<a href="' . e(page_link($pg['page'] + 1)) . '">Next &rsaquo;</a>';
    return $h . '</nav>';
}

function time_ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    return date('d M Y', strtotime($dt));
}
