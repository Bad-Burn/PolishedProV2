# PayPal Premium Sandbox setup

PolishedProV2 already sells artist listing plans. This integration adds an artist **Premium** plan for PHP 149/month and leaves customer accounts free. The PayPal plan has one regular monthly cycle, unlimited renewals, no PayPal trial or setup fee, automatic outstanding billing, and a one-failure suspension threshold. Existing Studio and Signature plans remain available.

The app's separate 14-day artist onboarding trial remains an account-level feature. The Premium PayPal agreement itself has no trial and charges when the artist approves it.

## 1. Prepare the local environment

1. Start Apache and MySQL in XAMPP.
2. Open a terminal in the project root and run:

   ```powershell
   php bin/key.php
   php bin/migrate.php
   ```

   Do not run `php bin/migrate.php --fresh`; that option drops every table.

3. In the PayPal Developer Dashboard, stay in **Sandbox**, create/select a REST app for a Sandbox Business merchant, and copy its Sandbox Client ID and Secret into the local `.env`:

   ```dotenv
   PAYPAL_MODE=sandbox
   PAYPAL_CLIENT_ID=your-sandbox-client-id
   PAYPAL_CLIENT_SECRET=your-sandbox-client-secret
   PAYPAL_WEBHOOK_ID=
   PAYPAL_CURRENCY=PHP
   PAYPAL_PRODUCT_ID=
   PAYPAL_PLAN_ID=
   PAYPAL_MONTHLY_PRICE=149.00
   ```

   Keep the Secret in `.env`; do not paste it into chat, HTML, JavaScript, or Git. PayPal lists PHP as a supported payment currency. PayPal account country, receiving currency, and product access can still affect whether an account is allowed to create or collect subscriptions.

## 2. Create or reuse the Sandbox product and plan

After the migration and credentials are set, run:

```powershell
php bin/paypal-premium.php
```

This CLI-only command refuses to make API calls unless `PAYPAL_MODE=sandbox`. It validates any configured IDs, searches Sandbox for matching resources before creating new ones, verifies the plan fields and `ACTIVE` status, saves the plan ID on the local Premium row, and writes only the product ID, plan ID, and price to `.env`. It never prints or writes the Client Secret.

The app reads the plan ID from the database row. Do not manually copy a plan ID into an unrelated plan or change the Premium price in the database.

## 3. Serve only this app locally, then expose it over HTTPS

PayPal cannot send webhooks to `localhost`. The current `.env` points at `http://localhost/polishedpro-v2/public`, but that path currently returns 404: XAMPP Apache serves `C:\xampp\htdocs`, while this checkout is in `D:\workspace - Copy\polishedpro-v2`. Also, tunneling port 80 would expose every site in Apache's document root. Use the XAMPP PHP development server bound to loopback so only this app is reachable through the tunnel.

Open a terminal in the project root and start the local app:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8765 -t public bin/router.php
```

Confirm `http://127.0.0.1:8765/help` loads. Keep this terminal open. After approval to expose the app, open a second terminal and run:

```powershell
ngrok http 8765
```

Use the HTTPS forwarding hostname ngrok displays. The public app is at its root, with no `/polishedpro-v2/public` suffix. Update the local `.env`:

```dotenv
APP_URL=https://your-current-ngrok-host
TRUSTED_PROXY=true
TRUSTED_PROXY_IPS=127.0.0.1,::1
```

The app honors forwarded headers only when the connecting peer matches `TRUSTED_PROXY_IPS`. Ngrok appends the originating client values, and `Request` reads the last `X-Forwarded-For` and `X-Forwarded-Proto` hops. Keep the allowlist limited to verified proxy peer IPs. Revert to `TRUSTED_PROXY=false` when the tunnel is stopped and the app is accessed directly. The temporary tunnel hostname changes when the tunnel restarts, so update `APP_URL` and the PayPal webhook URL each time.

## 4. Register the PayPal webhook

In the PayPal Developer Dashboard, open **Apps & Credentials → Sandbox → PolishedProV2 → Webhooks → Add Webhook**. Add this endpoint:

```text
https://your-current-ngrok-host/webhooks/paypal
```

Subscribe to:

- `BILLING.SUBSCRIPTION.ACTIVATED`
- `BILLING.SUBSCRIPTION.CANCELLED`
- `BILLING.SUBSCRIPTION.SUSPENDED`
- `BILLING.SUBSCRIPTION.EXPIRED`
- `BILLING.SUBSCRIPTION.PAYMENT.FAILED`
- `PAYMENT.SALE.COMPLETED`
- `PAYMENT.SALE.REFUNDED`
- `PAYMENT.SALE.REVERSED`

Save the webhook, then copy its generated **Webhook ID** into `PAYPAL_WEBHOOK_ID` in `.env`. Do not use the Client ID in this setting. The app verifies each webhook with PayPal before changing billing records. Failed webhook work returns a retryable response; duplicate event IDs are processed once.

## 5. Run a Sandbox checkout

1. Create or use an artist account. For an immediate first-charge test, use an account whose existing artist trial has expired; the app defers first charges on the older plans during that trial. Premium has no PayPal trial and begins billing when approved.
2. Open **Artist dashboard → Plan & billing** and choose Premium.
3. Approve using a Sandbox Personal buyer account from the PayPal Developer Dashboard's **Sandbox → Accounts** page.
4. Confirm the subscription is active in PayPal Sandbox and that PolishedProV2 records the subscription and completed payment. The billing page shows the status, billed period, last successful payment, and payment history.
5. Test cancellation from the artist billing page. The local access period ends immediately for a refunded/reversed sale; ordinary cancellation retains already-paid access through the period end.

The browser approval callback alone never activates a plan. PHP reads the subscription and transaction from PayPal, checks the exact plan, user-bound customer reference, amount, currency, and completed transaction before enabling paid features. A pending approval stays pending until a verified successful sale arrives by API lookup or webhook.

## Sandbox test checklist

- Approve a new PHP 149 monthly subscription; verify a completed payment row and active artist plan.
- Verify PHP 148, USD, wrong plan IDs, and a subscription belonging to another signed-in artist are rejected.
- Submit the same successful webhook event twice; confirm there is one ledger transaction and no duplicate period extension.
- Send a webhook without a valid PayPal signature; confirm it is rejected and leaves the database unchanged.
- Trigger a failed payment, suspension, cancellation, and expiration in Sandbox; confirm pending subscriptions never grant access and a paying account retains only its verified paid-through period.
- Refund or reverse a test sale; confirm the payment status changes, access is revoked, and PayPal billing is cancelled.
- Let a Sandbox monthly billing period renew, then verify the renewal transaction and next billing date. The fixed plan remains monthly; do not alter its frequency to accelerate tests.
- Confirm a free customer account has no artist-plan entitlements and administrator access is unchanged.

The Sandbox account must support PayPal Subscriptions. PayPal's current Subscriptions integration documentation notes eligibility/onboarding restrictions for some accounts. If the API returns `NOT_AUTHORIZED` or the button cannot create a subscription, check the merchant Sandbox account's Subscriptions access with PayPal before changing the application.

## Before production

- This setup command is Sandbox-only. The code does not switch `PAYPAL_MODE` to Live or create Live products/plans.
- Live credentials, Live Product/Plan IDs, Live webhook registration, and production terms/refund policy remain separate manual steps and need review before accepting real payments.
- A public HTTPS host is required for production webhooks. Do not use a temporary development tunnel for a launch site.
- PayPal subscription availability, PHP receiving/settlement behavior, and merchant eligibility must be confirmed in the actual PayPal Business account.
- Refund and reversal events conservatively cancel the subscription and revoke its current entitlement immediately. Review this policy before live billing if partial refunds should retain access.

## Official PayPal references

- [Subscriptions API](https://developer.paypal.com/api/subscriptions/v1/)
- [Pricing plans and billing cycles](https://developer.paypal.com/subscriptions/pricing-plan)
- [Supported currencies](https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/)
- [Subscriptions JavaScript SDK integration](https://developer.paypal.com/platforms/subscriptions/integrate/)
- [Subscription webhook events](https://developer.paypal.com/subscriptions/webhooks/)
- [Webhook signature verification](https://developer.paypal.com/api/rest/webhooks/)
- [JavaScript SDK CSP and COOP guidance](https://developer.paypal.com/sdk/js/v5/best-practices/)
- [ngrok for Windows](https://ngrok.com/download/windows)
- [ngrok forwarded headers](https://ngrok.com/docs/gateway/endpoints/http)
