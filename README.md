# PolishedPro

A booking platform for independent beauty professionals — nail artists, hairstylists,
make-up artists, barbers and therapists — built for the Philippine market.

This is a ground-up rebuild of the original PolishedPro. Same three roles, same domain,
but a new architecture, a new interface, a real availability engine, artist
subscriptions billed through PayPal, and a security posture that holds up to scrutiny.

---

## What it does

**Clients** search by service, area, price and rating; see an artist's real calendar;
book a slot; and review the appointment afterwards. No booking fee, no card required.

**Artists** publish a service menu, set their working week, take bookings, manage a
portfolio and client list, and optionally subscribe for more listings and better
search placement. Zero commission on bookings, on every tier.

**The admin** (you) approves new artists, moderates reviews, manages plans and
subscriptions, and has an append-only audit log of every administrative action.

---

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer, with `pdo_mysql`, `gd`, `mbstring`, `curl` |
| Database | MySQL 5.7+ or MariaDB 10.3+ |
| Web server | Apache with `mod_rewrite`, or Nginx |
| Composer | **not required** — there are no PHP dependencies |
| Node / npm | **not required** — no build step |

XAMPP ships with everything needed.

---

## Quick start

```bash
# 1. Configure
cp .env.example .env
php bin/key.php              # generates APP_KEY

# edit .env: DB_NAME, DB_USER, DB_PASSWORD, APP_URL

# 2. Create the schema
php bin/migrate.php

# 3. Seed
php bin/seed.php             # reference data + demo artists and bookings
# or, for a real deployment:
php bin/seed.php --reference # categories, plans and settings only

# 4. Create your admin account
php bin/admin.php
```

Then point your browser at `APP_URL`.

### Running it locally without Apache

```bash
php -S 127.0.0.1:8000 -t public bin/router.php
```

Use `bin/router.php`, not `public/index.php`. The built-in server sends every
request to the script it is given, so pointing it straight at the front
controller means CSS, JavaScript and uploaded photos come back as 404s. The
router serves files that exist and passes the rest through, which is the same
rule `public/.htaccess` gives Apache.

### Running it under XAMPP

Drop the folder in `htdocs`, then visit `http://localhost/polishedpro-v2/public`.
Set `APP_URL` in `.env` to match. The root `.htaccess` will forward requests into
`public/` if you hit the folder directly.

---

## Demo accounts

`php bin/seed.php` creates eight artists, six clients and ~75 bookings so every
screen has something real in it. All demo accounts share one password:

| Role | Email | Password |
|---|---|---|
| Artist (Signature plan) | `mariel@polishedpro.test` | `PolishedDemo!2026` |
| Artist (Studio plan) | `jonas@polishedpro.test` | `PolishedDemo!2026` |
| Artist (trial, no plan yet) | `paolo@polishedpro.test` | `PolishedDemo!2026` |
| Client | `bea@example.test` | `PolishedDemo!2026` |

**Delete these before going live.** There is no admin among them — create yours with
`php bin/admin.php`.

---

## Project layout

```
polishedpro-v2/
├── public/              ← the only web-reachable directory
│   ├── index.php          front controller
│   ├── assets/            css, js, images
│   └── uploads/           user uploads (PHP execution disabled)
├── app/
│   ├── Core/              router, request, auth, session, csrf, database, …
│   ├── Models/            one class per table group
│   ├── Controllers/       grouped by role
│   ├── Services/          availability engine, booking rules, PayPal client
│   ├── Support/           template helpers
│   └── Views/             layouts, partials, pages
├── config/              app, database, paypal
├── database/
│   ├── migrations/        versioned .sql
│   └── seeds/
├── routes/web.php       ← every route and its authorisation, on one screen
├── storage/logs/        app.log and security.log
└── bin/                 CLI: migrate, seed, admin, key, paypal-plans
```

Application code, configuration and `.env` all sit **outside** the document root.
Even a badly configured server cannot serve them.

---

## Artist subscriptions

Subscription only — there is no free tier. Every new artist starts a **14-day trial**
granting the full Studio feature set, then subscribes or goes quiet. Plans are seeded
and editable from **Admin → Plans**:

| | Trial | Studio | Signature |
|---|---|---|---|
| Price | Free, 14 days | ₱349/mo | ₱749/mo |
| Active services | 15 | 15 | Unlimited |
| Portfolio photos | 30 | 30 | Unlimited |
| Search placement | Standard | Priority | Top |
| Analytics | Yes | Yes | Yes |
| Instant booking | Yes | Yes | Yes |
| Commission on bookings | **0%** | **0%** | **0%** |

Trial length and which plan it mirrors are set in `config/app.php` under
`subscription.trial_days` / `subscription.trial_plan_code`.

### What "lapsed" means

`Subscription::accessFor()` is the single question the app asks: is this artist
`trialing`, `subscribed`, or `locked`? A locked artist:

- **disappears from search and their public profile 404s** — enforced in
  `Artist::HAS_ACCESS`, which is part of `PUBLIC_WHERE`, so every public query
  inherits it
- **cannot take new bookings** — `BookingService` resolves the artist through that
  same public filter, so there is no second gate to keep in sync
- **keeps their dashboard**, their data, and any appointments already booked

Nothing is deleted, and subscribing again restores the profile immediately. The
dashboard shows a banner in all three states (`partials/subscription-banner.php`).

Limits are enforced in `Subscription::serviceAllowance()` and checked on every write
path — hiding a button is not a limit. Only *active* services count, so an artist at
their cap can still draft a service and choose which one is published.

The old free `starter` plan row is retained but inactive, so historical subscriptions
keep a valid foreign key. `Plan::locked()` — all limits zero — replaced `Plan::free()`
as the fallback when nobody is paying.

Search placement affects the default "Recommended" sort only. Every other sort —
highest rated, most reviewed, price, newest — ignores the plan entirely.

### Setting up PayPal

A sandbox account at [developer.paypal.com](https://developer.paypal.com) is free.

```bash
# 1. Put your REST app credentials in .env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...

# 2. Create the billing plans
php bin/paypal-plans.php
```

That script creates a PayPal product and one plan per paid tier per billing cycle,
then stores the plan ids. It finishes by printing the webhook setup instructions.

**The webhook is not optional.** Add a webhook in the PayPal dashboard pointing at
`https://yoursite/webhooks/paypal`, subscribe to the events the script lists, and put
the webhook id in `PAYPAL_WEBHOOK_ID`. Without it, renewals and cancellations made on
PayPal's side never reach the app — and the app rejects unsigned webhooks outright.

Until PayPal is configured the billing screens degrade gracefully: plans are shown for
reference, and an admin can grant a plan manually from **Admin → Subscriptions**.

---

## Docs

| File | Contents |
|---|---|
| [docs/SECURITY.md](docs/SECURITY.md) | Threat model, every control, and what changed from v1 |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Going live, hardening checklist, backups |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the pieces fit, and why |

---

## CLI reference

```bash
php bin/key.php                 # generate APP_KEY
php bin/migrate.php             # apply pending migrations
php bin/migrate.php --status    # list applied / pending
php bin/migrate.php --fresh     # drop everything and re-apply (asks first)
php bin/seed.php                # reference data + demo dataset
php bin/seed.php --reference    # reference data only
php bin/admin.php               # create or promote an administrator
php bin/paypal-plans.php        # create PayPal billing plans
php bin/showcase.php <img> <name>   # rebuild a landing-page hero photo
```

---

## Licence

Built for the PolishedPro project. The placeholder Terms and Privacy pages are
starting points, not legal advice — have them reviewed before you launch.
