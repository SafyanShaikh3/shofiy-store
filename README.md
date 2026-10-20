# Shofiy Store: setup & go-live guide

This is a complete online store written in PHP and MySQL. Customers can browse, see live stock, pay with Razorpay (UPI, cards, netbanking, wallets) or cash on delivery, and track their orders. You run everything from the admin dashboard, so you never have to touch code.

---

## 1. Put the site online (about 20 minutes)

You need **PHP hosting with MySQL and a free SSL certificate**. Any cPanel or hPanel host works, for example Hostinger, GoDaddy, Bluehost, BigRock or MilesWeb. A basic/starter plan is enough.

1. **Buy or connect your domain** in the hosting panel and turn on **SSL (HTTPS)**. It's free on all of the hosts above.
2. **Create a MySQL database.** In cPanel use *MySQL Databases*, in hPanel use *Databases*. Write down the database name, the username and the password.
3. **Upload the files.** Open *File Manager* and go to `public_html`. Upload `shofiy-store.zip`, then right-click it and choose **Extract**. Make sure files like `index.php` end up directly inside `public_html`, not inside an extra folder.
4. **Run the installer.** Visit `https://your-domain.com/install.php` and fill in:
   - the database details from step 2
   - your store name, email and phone
   - an admin username and a strong password (10+ characters)
   - leave **Load sample products** ticked if you want to start from the products in your original project
5. **Delete `install.php`** in File Manager. The installer locks itself, but deleting it is safest.
6. **Force HTTPS.** Open `.htaccess`, remove the `#` from the four *Force HTTPS* lines, and save. Many hosts also have a "Force HTTPS" switch you can use instead.

Your store is now live at `https://your-domain.com` and the admin is at `https://your-domain.com/admin/`.

> **Moving from the old version?** The installer needs a **new, empty database** because the old tables are not compatible. The old page addresses (`home.php`, `quick_view.php`, and so on) redirect to the new pages automatically.

---

## 2. Turn on Razorpay payments

1. Sign up at **https://dashboard.razorpay.com**.
2. **Test first.** Switch the dashboard to *Test mode*. Go to **Account & Settings → API Keys → Generate Key**.
3. In your store admin, open **Settings & payments**, paste the **Key ID** (`rzp_test_…`) and the **Key Secret**, click **Save**, then click **Run test**.
4. **Add the webhook.** It confirms a payment even if the customer closes their phone before they get back to your site.
   - Razorpay: **Account & Settings → Webhooks → Add new webhook**
   - URL: `https://your-domain.com/webhook.php` (the admin Settings page shows the exact address; click it to copy)
   - Secret: make up a long password, paste it in Razorpay **and** in admin → Settings → *Webhook Secret*
   - Events: tick **payment.captured**, **order.paid** and **payment.failed**
5. Place a test order on your site and pay with a Razorpay test card or test UPI ID (listed in Razorpay's docs under *Test card details*). The order shows up in admin → Orders as **Paid**.
6. **Go live.** Complete Razorpay KYC. Razorpay checks your website for a Contact page and for Terms, Privacy, Refund and Shipping policies, and all of these are already built in. Then generate **Live** keys (`rzp_live_…`), add a live webhook the same way, and paste the live keys into Settings.

Cash on delivery can be turned on or off in Settings, with an optional maximum order value.

---

## 3. Using the admin dashboard

| I want to… | Go to |
|---|---|
| See today's sales and new orders | **Dashboard** |
| Change a price or stock number | **Products & stock**: edit the row and press **Save**. Customers see it within about 15 seconds without reloading |
| Add a product with photos | **Products & stock → + Add product**. Photos are resized and optimised automatically |
| Run a daily special / deal | **Today's specials**: pick a product, set a price or a % off, choose "End of today". It appears under *Today's deals* with a countdown and switches back automatically |
| Change the homepage slides | **Home banners** |
| Add or rename a category | **Categories** |
| Process an order | **Orders → open the order**: set *Processing → Shipped → Delivered* and add the courier/tracking note. The customer is emailed |
| Cancel an order | Open it and click **Cancel order**. Items go back into stock (refund paid orders from Razorpay) |
| Call or WhatsApp a customer | Buttons on each order page |
| Export orders to Excel | **Orders → Export CSV** |
| Change phone, email, delivery fee, colours, policies | **Settings & payments** |
| Add a staff login or change your password | **Admin users** |

---

## 4. How stock and payments stay correct

- **Live stock.** Every product shows *In stock*, *Only X left* or *Out of stock*. Pages re-check every few seconds, so a customer can't add an item that just sold out.
- **No overselling.** When a customer clicks *Place order*, their items are reserved for 20 minutes (you can change this) while they pay. If they don't pay, the items go back into stock automatically. Two people can never buy the last unit.
- **Prices can't be tampered with.** The price is always read from the database on the server. Razorpay is told the exact amount by the server, and every payment is verified with Razorpay's cryptographic signature.
- **Late payments.** If a payment arrives after the hold expired, the order is still marked paid. If the item sold out in the meantime, the order is flagged **ATTENTION** on your dashboard.

---

## 5. Security built in

- Passwords hashed with bcrypt (the old site stored them in a weak, crackable format)
- Every database query uses prepared statements (fixes SQL injection in the old `category.php`)
- All output is escaped to prevent XSS; CSRF tokens on every form
- Login rate-limiting for customers and admins; admin auto-logout after 2 hours idle
- Uploaded images are checked, re-encoded and stored under random names; scripts can't run from `uploads/`
- `config.php`, `includes/` and `install/` are blocked from the web by `.htaccess`

**Keep it safe:** use a strong admin password, keep `debug` set to `false` in `config.php`, and take regular backups. Most hosts include a one-click backup in the panel.

---

## 6. Email

Order confirmations, new-order alerts and shipping updates use your host's built-in mail. If emails land in spam, create a mailbox on your domain (for example `orders@your-domain.com`) in the hosting panel and set it as the store email in **Settings**.

---

## 7. File map (for developers)

```
index.php, shop.php, product.php      storefront
cart.php, cart_action.php             cart (AJAX + no-JS fallback)
checkout.php → pay.php → payment_verify.php   order + Razorpay flow
webhook.php                           Razorpay webhook (signature verified, idempotent)
order.php, track.php, orders.php      order status for customers
api/stock.php                         live stock/price JSON feed
admin/                                dashboard
includes/                             bootstrap, helpers, cart, orders, Razorpay client
install.php, install/                 one-time installer, schema, sample data
uploads/                              product, banner and category images
```

Requirements: PHP 8.0+, MySQL 5.7+ or MariaDB 10.3+, PHP extensions `pdo_mysql`, `curl`, and `gd` (recommended).

On **Nginx** instead of Apache, deny access to `/config.php`, `/includes/`, `/install/`, and to any `.php` file inside `/uploads/`.
