<?php
// The installer (install.php) creates config.php for you. Use this only for manual setup:
// copy to config.php, fill in, then import install/schema.sql and create an admin.
return [
    'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'your_db', 'user' => 'your_user', 'pass' => 'your_password'],
    'base_path' => '/',          // '/' if the store is at the domain root, '/shop/' if in a sub-folder
    'timezone' => 'Asia/Kolkata',
    'debug' => false,            // never true on a live site
    // Optional: keep Razorpay secrets in this file instead of the admin panel
    // 'razorpay_key_id' => 'rzp_live_xxx', 'razorpay_key_secret' => 'xxx', 'razorpay_webhook_secret' => 'xxx',
];
