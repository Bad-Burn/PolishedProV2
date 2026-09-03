# Deployment

Getting PolishedPro onto a real server, safely.

---

## 1. Point the document root at `public/`

This is the single most important step. Everything else in the repository —
application code, configuration, `.env`, logs, migrations — must sit outside the
web-reachable directory.

**Nginx**

```nginx
server {
    listen 443 ssl http2;
    server_name polishedpro.example;

    root /var/www/polishedpro-v2/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Uploads are static content and must never execute.
    location ^~ /uploads/ {
        location ~ \.php$ { return 403; }
        add_header X-Content-Type-Options "nosniff" always;
    }

    client_max_body_size 6M;
}
```

**Apache virtual host**

```apache
<VirtualHost *:443>
    ServerName polishedpro.example
    DocumentRoot /var/www/polishedpro-v2/public

    <Directory /var/www/polishedpro-v2/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

With a proper vhost you can **delete the root `.htaccess`** — it exists only for shared
hosting where the document root cannot be moved.

**Shared hosting / cPanel**, where the root is fixed at `public_html`: upload the
project one level above `public_html` and make `public_html` a symlink to
`polishedpro-v2/public`. If symlinks are not permitted, upload everything into
`public_html` and rely on the bundled root `.htaccess` — it blocks `app/`, `config/`,
`database/`, `routes/`, `storage/`, `bin/` and `.env`. This is the weakest option;
prefer the symlink.

---

## 2. Configure `.env`

```ini
APP_ENV=production
APP_DEBUG=false            # non-negotiable
APP_URL=https://polishedpro.example
APP_KEY=                   # php bin/key.php

DB_HOST=127.0.0.1
DB_NAME=polishedpro
DB_USER=polishedpro_app    # NOT root
DB_PASSWORD=<long random>

TRUSTED_PROXY=false        # true ONLY behind a proxy you control
```

`APP_DEBUG=false` matters: with it on, an uncaught exception prints a stack trace
including file paths and query fragments. The kernel refuses to start if `APP_KEY`
is unset or still the placeholder.

### Database user

Do not run as `root`. Create a dedicated user with only the privileges the app needs:

```sql
CREATE DATABASE polishedpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'polishedpro_app'@'localhost' IDENTIFIED BY '<long random password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON polishedpro.* TO 'polishedpro_app'@'localhost';
FLUSH PRIVILEGES;
```

Run migrations with a temporarily elevated user (or grant `CREATE, ALTER, DROP, INDEX,
REFERENCES` for the duration), then drop back to the four verbs above. An application
account that cannot `DROP TABLE` limits the damage of a SQL injection that gets past
everything else.

---

## 3. Install

```bash
php bin/migrate.php
php bin/seed.php --reference   # categories, plans, settings — NO demo accounts
php bin/admin.php
```

Use `--reference`. The plain `php bin/seed.php` invents eight fake artists and
seventy-odd fake bookings, which is exactly what you do not want in production.

If you seeded demo data by accident:

```sql
DELETE FROM users WHERE email LIKE '%@polishedpro.test'
                     OR email LIKE '%@example.test';
```

Foreign keys cascade, so their services, bookings and reviews go with them.

---

## 4. Permissions

```bash
cd /var/www/polishedpro-v2

sudo chown -R www-data:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# Only these need to be writable by the web server:
sudo chmod -R 775 storage/logs public/uploads

# .env holds every secret you have:
sudo chmod 640 .env
```

---

## 5. HTTPS

Not optional. Session cookies are only marked `Secure` over HTTPS, and HSTS is only
sent over HTTPS.

```bash
sudo certbot --nginx -d polishedpro.example
```

Then confirm `APP_URL` uses `https://`, since PayPal return URLs are built from it.

---

## 6. PayPal

```bash
# .env
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=<live client id>
PAYPAL_CLIENT_SECRET=<live secret>

php bin/paypal-plans.php
```

Then, in the PayPal dashboard, create a webhook:

- **URL** — `https://polishedpro.example/webhooks/paypal`
- **Events** — `BILLING.SUBSCRIPTION.ACTIVATED`, `.CANCELLED`, `.SUSPENDED`,
  `.EXPIRED`, `.PAYMENT.FAILED`, and `PAYMENT.SALE.COMPLETED`

Copy the webhook id into `PAYPAL_WEBHOOK_ID`.

**Without `PAYPAL_WEBHOOK_ID` the application rejects every webhook** — it fails closed
rather than trusting unsigned input. Renewals and PayPal-side cancellations would then
never register, and subscriptions would silently expire at the end of their period.

Test the whole flow in `sandbox` mode before switching to `live`.

---

## 7. Scheduled maintenance (optional)

The app does opportunistic housekeeping when an admin loads the dashboard —
auto-completing overdue bookings and expiring lapsed subscriptions — so it works
without cron. On a busy site, a nightly job is tidier:

```cron
0 3 * * * cd /var/www/polishedpro-v2 && php bin/maintenance.php >> storage/logs/cron.log 2>&1
```

---

## 8. Backups

```bash
#!/usr/bin/env bash
set -euo pipefail

STAMP=$(date +%F-%H%M)
DEST=/var/backups/polishedpro

mkdir -p "$DEST"

mysqldump --single-transaction --quick polishedpro | gzip > "$DEST/db-$STAMP.sql.gz"
tar czf "$DEST/uploads-$STAMP.tar.gz" -C /var/www/polishedpro-v2/public uploads

find "$DEST" -type f -mtime +30 -delete
```

Back up `.env` separately and securely — it holds `APP_KEY`, and without it every
remember-me token and session fingerprint becomes invalid.

**Test a restore before you need one.** An untested backup is a guess.

---

## Pre-launch checklist

- [ ] Document root points at `public/`; `/app/`, `/config/` and `/.env` return 403 or 404
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generated and backed up
- [ ] Database user is not `root` and cannot `DROP`
- [ ] HTTPS working, HTTP redirects to it
- [ ] Demo accounts deleted
- [ ] Admin account created, with two-factor authentication switched on
- [ ] PayPal webhook registered and `PAYPAL_WEBHOOK_ID` set
- [ ] `storage/logs` and `public/uploads` writable; nothing else is
- [ ] A test upload lands in `uploads/` and does **not** execute
- [ ] Backups scheduled and a restore rehearsed
- [ ] Terms and Privacy pages replaced with reviewed text
- [ ] `SUPPORT_EMAIL` set to an address someone actually reads

### Verify the document root is correct

```bash
curl -I https://polishedpro.example/.env          # expect 403 or 404
curl -I https://polishedpro.example/app/Core/Auth.php
curl -I https://polishedpro.example/storage/logs/app.log
```

Any `200` here means the document root is wrong. Fix it before launching.

---

## Upgrading

```bash
php bin/migrate.php --status   # see what is pending
# back up the database
php bin/migrate.php
```

Migrations are tracked in the `migrations` table and are safe to re-run. Never use
`--fresh` on production — it drops every table.
