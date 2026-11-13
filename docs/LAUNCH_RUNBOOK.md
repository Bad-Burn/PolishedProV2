# PolishedPro launch runbook

Use this against a staging server first. Do not run migrations, the demo seeder,
or browser checks against production until the deployment target and its data
have been confirmed.

## 1. Prepare a release

Review the changes in Git and commit the exact files you intend to deploy. Keep
the application root outside the web directory and set the web server's document
root to the repository's `public/` directory. The server needs PHP 8.1 or newer
with `pdo_mysql`, `gd`, `mbstring`, and `curl`, plus MySQL 5.7+/MariaDB 10.3+.

Do not deploy `.env`, logs, or local uploads from the development machine. Put
the site behind HTTPS and make HTTP redirect to HTTPS.

## 2. Create production configuration

Create a new production `.env` on the server. Do not copy the local `.env` as-is.
Set at least:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-domain
APP_KEY=<unique key generated on this deployment>
APP_TIMEZONE=Asia/Manila

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=polishedpro
DB_USER=polishedpro_app
DB_PASSWORD=<long unique password>

TRUSTED_PROXY=false
SUPPORT_EMAIL=<monitored support inbox>

MAIL_DRIVER=smtp
MAIL_HOST=<your SMTP host>
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<SMTP username>
MAIL_PASSWORD=<SMTP password>
MAIL_FROM_ADDRESS=<verified sender address>
MAIL_FROM_NAME=PolishedPro

PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=<sandbox client id>
PAYPAL_CLIENT_SECRET=<sandbox secret>
PAYPAL_WEBHOOK_ID=<sandbox webhook id>
PAYPAL_CURRENCY=PHP
```

Generate the key on the server with `php bin/key.php`, then place its output in
`APP_KEY`. Store a protected backup of the key with the deployment secrets.
Values containing `#` must be quoted. Restrict `.env` permissions and confirm
that `/.env`, `/app/`, `/config/`, `/database/`, `/storage/`, and `/bin/` are not
web-accessible.

Create a dedicated database and application user. Temporarily use a migration
account with schema privileges to install or upgrade the database; the runtime
user should only have `SELECT`, `INSERT`, `UPDATE`, and `DELETE` permissions.

## 3. Install the schema and first admin

From the deployed application root:

```sh
php bin/migrate.php
php bin/migrate.php --status
php bin/seed.php --reference
php bin/admin.php
```

The reference seeder adds the categories and plans without fake accounts. Do not
run `php bin/seed.php` on a real site. After creating the admin, sign in and
enable two-factor authentication. Remove temporary schema privileges afterward.

## 4. Connect email and billing

In your SMTP provider, verify the sending domain and publish its SPF and DKIM
records. Add a DMARC record and send a real password-reset email to confirm
delivery and that the link returns to the HTTPS domain.

First run billing in PayPal sandbox. With sandbox credentials in `.env`, run:

```sh
php bin/paypal-plans.php
```

Create the PayPal webhook at `https://your-real-domain/webhooks/paypal`, subscribe
to the event list printed by the script, and set its ID in `PAYPAL_WEBHOOK_ID`.
Rehearse subscribe, initial approval, renewal, failed payment, cancellation,
resume, and upgrade. Then replace sandbox credentials with live credentials, set
`PAYPAL_MODE=live`, run `php bin/paypal-plans.php` for the live account, and
register a separate live webhook.

## 5. Configure routine operations

Run `php bin/maintenance.php` hourly using the host's scheduler. Configure log
rotation and uptime/error monitoring. Schedule encrypted backups of the database,
uploads, and `.env`/`APP_KEY`; test a restore before accepting bookings.

Only `storage/logs` and `public/uploads` should be writable by PHP. Set PHP upload
limits to at least `upload_max_filesize=6M`, `post_max_size=8M`, and
`memory_limit=256M`.

## 6. Finish the manual launch items

- Replace the placeholder Terms and Privacy pages with reviewed policies for the
  business and jurisdiction. Confirm the policy describes Google Fonts, email,
  PayPal, booking data, retention, and user requests.
- Set the real support inbox and confirm someone will monitor it.
- Decide how subscription charges and refunds will be reconciled. The current
  app updates subscription access from PayPal events but does not write those
  transactions to its `payments` table; use PayPal reports as the launch source
  of truth or add an in-app payment ledger before depending on app reports.
- Add approved artists and real services before public promotion.
- Check domain DNS, HTTPS renewal, redirects, and the server's PHP-FPM/Apache or
  Nginx configuration. On Apache with PHP-FPM, ensure `.htaccess` is allowed and
  test a request after deployment.
- Verify a test image upload works and remains inert static content.

## 7. Run the local browser smoke checks

On a local or staging copy with its database migrated and configured (never point
these checks at production):

```sh
npm install
npx playwright install chromium
npm run test:e2e
```

The checks exercise the public help page, sign-in form, security headers, CSS
delivery, and the public document-root boundary. They do not replace the manual
PayPal, email, upload, restore, or legal checks above.

## Go/no-go

Do not announce launch until the production environment is configured, the
staging smoke checks pass, live PayPal and email flows have been rehearsed, legal
pages are reviewed, and backups/monitoring are confirmed.
