<?php
/**
 * One-time web installer. Open https://your-domain/install.php after uploading the files.
 * It refuses to run again once config.php exists.
 */
declare(strict_types=1);
define('APP_ROOT', __DIR__);
require __DIR__ . '/includes/functions.php';
session_start();

$installed = is_file(__DIR__ . '/config.php');
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

$checks = [
    'PHP 8.0 or newer'           => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO MySQL extension'        => extension_loaded('pdo_mysql'),
    'cURL extension (payments)'  => extension_loaded('curl'),
    'GD extension (image resize)' => extension_loaded('gd'),
    'Site folder is writable'    => is_writable(__DIR__),
    'uploads/ folder is writable' => is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads'),
];
$required = ['PHP 8.0 or newer', 'PDO MySQL extension', 'cURL extension (payments)', 'Site folder is writable', 'uploads/ folder is writable'];
$canInstall = !$installed && !in_array(false, array_intersect_key($checks, array_flip($required)), true);

$errors = [];
$done = false;
$f = ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'store_name' => 'Shofiy', 'store_email' => 'safyanshaikh009@gmail.com', 'store_phone' => '+91 70695 00011',
      'admin_user' => 'admin', 'rzp_key_id' => '', 'sample' => '1'];

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $rzpSecret = trim((string)($_POST['rzp_key_secret'] ?? ''));
    $f['admin_user'] = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $f['admin_user']));

    if (!$canInstall) $errors[] = 'Please fix the server requirements first.';
    if ($f['db_name'] === '' || $f['db_user'] === '') $errors[] = 'Database name and user are required.';
    if (strlen($f['admin_user']) < 3) $errors[] = 'Admin username must be at least 3 characters.';
    if (strlen($adminPass) < 10) $errors[] = 'Admin password must be at least 10 characters.';
    if ($adminPass !== (string)($_POST['admin_pass2'] ?? '')) $errors[] = 'Admin passwords do not match.';
    if ($f['store_email'] !== '' && !filter_var($f['store_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Store email looks invalid.';
    if ($f['rzp_key_id'] !== '' && !preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/', $f['rzp_key_id'])) $errors[] = 'Razorpay Key ID should start with rzp_test_ or rzp_live_.';

    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $f['db_host'] . ';port=' . (int)$f['db_port'] . ';dbname=' . $f['db_name'] . ';charset=utf8mb4', $f['db_user'], $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            date_default_timezone_set('Asia/Kolkata');
            $pdo->exec("SET time_zone = '" . date('P') . "'");

            if ($pdo->query("SHOW TABLES LIKE 'products'")->fetch()) {
                $cols = array_column($pdo->query('SHOW COLUMNS FROM products')->fetchAll(), 'Field');
                if (!in_array('slug', $cols, true)) {
                    throw new RuntimeException('This database already has tables from the old version of the site. Please create a new, empty database (or delete the old tables) and try again.');
                }
            }

            $schema = (string)file_get_contents(__DIR__ . '/install/schema.sql');
            foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', preg_replace('/^--.*$/m', '', $schema)))) as $stmt) $pdo->exec($stmt);

            // Settings
            $settings = default_settings();
            $settings['store_name'] = $f['store_name'] ?: 'My Store';
            if ($f['store_email']) $settings['store_email'] = $f['store_email'];
            if ($f['store_phone']) $settings['store_phone'] = $f['store_phone'];
            $settings['razorpay_key_id'] = $f['rzp_key_id'];
            $settings['razorpay_key_secret'] = $rzpSecret;
            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            foreach ($settings as $k => $v) $st->execute([$k, $v]);

            // Admin
            $pdo->prepare('INSERT INTO admins (name, username, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
                ->execute(['Store Owner', $f['admin_user'], password_hash($adminPass, PASSWORD_DEFAULT)]);

            // Sample catalogue
            if ($f['sample'] === '1' && (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
                $seed = require __DIR__ . '/install/seed.php';
                $copy = function (string $file, string $dir) {
                    $src = __DIR__ . "/install/seed/$dir/$file";
                    if (!is_dir(__DIR__ . "/uploads/$dir")) mkdir(__DIR__ . "/uploads/$dir", 0755, true);
                    if (is_file($src)) copy($src, __DIR__ . "/uploads/$dir/$file");
                    return is_file($src) ? $file : null;
                };
                $catIds = [];
                $ins = $pdo->prepare('INSERT INTO categories (name, slug, image, sort_order) VALUES (?, ?, ?, ?)');
                foreach ($seed['categories'] as $i => [$name, $slug, $img]) {
                    $ins->execute([$name, $slug, $img ? $copy($img, 'categories') : null, $i + 1]);
                    $catIds[$slug] = (int)$pdo->lastInsertId();
                }
                $ins = $pdo->prepare('INSERT INTO banners (title, subtitle, image, link_url, button_text, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($seed['banners'] as $i => [$t, $s, $img, $link, $btn]) $ins->execute([$t, $s, $copy($img, 'banners'), $link, $btn, $i + 1]);

                $ins = $pdo->prepare('INSERT INTO products (category_id, name, slug, details, price, mrp, special_price, special_ends_at, stock, low_stock_threshold, is_featured, image_1, image_2, image_3, created_at)
                                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $n = count($seed['products']);
                foreach ($seed['products'] as $i => $p) {
                    [$name, $cat, $price, $mrp, $stock, $featured, $imgs, $details] = $p;
                    $special = $p[8] ?? null;
                    $imgs = array_map(fn($x) => $copy($x, 'products'), $imgs);
                    $ins->execute([$catIds[$cat] ?? null, $name, slugify($name), $details, $price, $mrp, $special,
                        $special ? date('Y-m-d 23:59:59', strtotime('+1 day')) : null, $stock, 5, $featured,
                        $imgs[0] ?? null, $imgs[1] ?? null, $imgs[2] ?? null, date('Y-m-d H:i:s', time() - ($n - $i) * 60)]);
                }
            }

            // Write config.php
            $config = [
                'db' => ['host' => $f['db_host'], 'port' => (int)$f['db_port'], 'name' => $f['db_name'], 'user' => $f['db_user'], 'pass' => $dbPass],
                'base_path' => $basePath,
                'timezone' => 'Asia/Kolkata',
                'debug' => false,
            ];
            $php = "<?php\n// Generated by the installer on " . date('Y-m-d H:i') . ". Keep this file private.\n// Razorpay keys are managed in Admin -> Settings (you may also set 'razorpay_key_id' / 'razorpay_key_secret' / 'razorpay_webhook_secret' here).\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents(__DIR__ . '/config.php', $php, LOCK_EX) === false) throw new RuntimeException('Could not write config.php — check folder permissions.');
            @chmod(__DIR__ . '/config.php', 0640);
            $done = true;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install your store</title>
<link rel="stylesheet" href="assets/css/admin.css">
<style>.wrap{max-width:640px;margin:0 auto;padding:24px 16px}.req li{list-style:none;padding:4px 0}.ok{color:#15803d}.bad{color:#b91c1c}h1{margin-top:12px}</style>
</head><body>
<div class="wrap">
  <h1>Store setup</h1>
  <?php if ($done): ?>
    <div class="panel">
      <h2 class="ok">✓ Your store is installed</h2>
      <p>Admin username: <b><?= $h($f['admin_user']) ?></b></p>
      <ol class="steps">
        <li><b>Delete <code>install.php</code></b> from your server now (it is locked, but removing it is safest).</li>
        <li>Log in to the <a href="admin/login.php">admin dashboard</a> and go to <b>Settings &amp; payments</b> to finish connecting Razorpay and its webhook.</li>
        <li>Replace the sample products with your own in <b>Products &amp; stock</b>.</li>
      </ol>
      <p class="btn-row"><a class="btn" href="admin/login.php">Open admin</a> <a class="btn btn-ghost" href="./">View store</a></p>
    </div>
  <?php elseif ($installed): ?>
    <div class="panel"><h2>Already installed</h2><p>This store is already set up. For safety the installer is disabled. Delete <code>install.php</code> from your server.</p><p><a class="btn" href="./">Go to store</a></p></div>
  <?php else: ?>
    <div class="panel">
      <h2>1. Server check</h2>
      <ul class="req">
        <?php foreach ($checks as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= $h($label) ?><?= !$ok && !in_array($label, $required, true) ? ' <span class="muted">(optional — images will not be resized)</span>' : '' ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= $h($er) ?></div><?php endforeach; ?>
    <form method="post" class="panel" autocomplete="off">
      <h2>2. Database</h2>
      <p class="help">Create an empty MySQL database in your hosting control panel (cPanel → MySQL Databases, or hPanel → Databases) and enter its details.</p>
      <div class="row2">
        <label class="f"><span>Database host</span><input name="db_host" value="<?= $h($f['db_host']) ?>" required></label>
        <label class="f"><span>Port</span><input name="db_port" value="<?= $h($f['db_port']) ?>" required></label>
      </div>
      <label class="f"><span>Database name</span><input name="db_name" value="<?= $h($f['db_name']) ?>" required></label>
      <div class="row2">
        <label class="f"><span>Database user</span><input name="db_user" value="<?= $h($f['db_user']) ?>" required></label>
        <label class="f"><span>Database password</span><input type="password" name="db_pass"></label>
      </div>

      <h2 class="mt">3. Store</h2>
      <label class="f"><span>Store name</span><input name="store_name" value="<?= $h($f['store_name']) ?>" required></label>
      <div class="row2">
        <label class="f"><span>Store email</span><input type="email" name="store_email" value="<?= $h($f['store_email']) ?>"></label>
        <label class="f"><span>Store phone</span><input name="store_phone" value="<?= $h($f['store_phone']) ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="sample" value="1" <?= $f['sample'] === '1' ? 'checked' : '' ?>> Load sample products, categories and banners (from your original project)</label>

      <h2 class="mt">4. Admin login</h2>
      <label class="f"><span>Admin username</span><input name="admin_user" value="<?= $h($f['admin_user']) ?>" required autocapitalize="none"></label>
      <div class="row2">
        <label class="f"><span>Password (min 10 characters)</span><input type="password" name="admin_pass" required minlength="10" autocomplete="new-password"></label>
        <label class="f"><span>Repeat password</span><input type="password" name="admin_pass2" required minlength="10" autocomplete="new-password"></label>
      </div>

      <h2 class="mt">5. Razorpay (optional — can be added later)</h2>
      <div class="row2">
        <label class="f"><span>Key ID</span><input name="rzp_key_id" value="<?= $h($f['rzp_key_id']) ?>" placeholder="rzp_test_…"></label>
        <label class="f"><span>Key Secret</span><input type="password" name="rzp_key_secret" autocomplete="new-password"></label>
      </div>
      <button class="btn btn-block" type="submit" <?= $canInstall ? '' : 'disabled' ?>>Install store</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
