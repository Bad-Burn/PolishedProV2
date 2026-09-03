# Security

What this application defends against, how, and where the code lives.

The original PolishedPro had a set of specific, exploitable problems. Each is listed
at the end with what replaced it.

---

## Threat model

This is a platform where members of the public create accounts, upload files, and
exchange contact details with strangers they are about to meet in person. The
realistic threats are:

1. Account takeover (credential stuffing, weak passwords, session theft)
2. One user reading or altering another user's data by changing an id in a URL
3. Malicious file upload leading to code execution
4. SQL injection through the many search and filter inputs
5. Cross-site scripting through user-supplied profile text and reviews
6. Cross-site request forgery against the state-changing forms
7. Billing manipulation — granting oneself a paid plan without paying
8. Enumeration of registered email addresses
9. Privacy leaks: home addresses and phone numbers exposed to the wrong people

Each is addressed below.

---

## Authentication

**Password storage** — Argon2id with a 64 MB memory cost, falling back to bcrypt
(cost 12) where Argon2id is unavailable. Hashes are upgraded transparently on the next
successful login when the algorithm or cost changes.
→ `app/Core/Hash.php`

**Password policy** — minimum 10 characters, with rejection of single repeated
characters, a common-password list, and any password containing the user's email local
part. Length does most of the work; arbitrary complexity rules mostly produce
`Password1!`.
→ `Validator::assertPassword()`

**Login throttling** — two independent counters, one keyed on the source IP and one on
the target account, so neither password spraying across many accounts nor a focused
attack on one account gets unlimited attempts. Five attempts per 15 minutes by default.
→ `app/Core/RateLimiter.php`, `AuthController::login()`

**No user enumeration** — a wrong password, a non-existent account and a malformed
email all produce the same message. `Hash::verify()` runs a dummy verification when no
hash exists so the timing does not differ either. Password reset always reports
"if that email is registered…" regardless.

**Two-factor authentication** — optional TOTP (RFC 6238) implemented in pure PHP, so it
works with Google Authenticator, Authy or 1Password with no third-party service, no SMS
cost and no extra dependency. Includes single-use recovery codes, stored hashed.
Crucially, the session is **not** established until the second factor is verified — the
pending login lives in a short-lived, separate session key.
→ `app/Core/Totp.php`, `AuthController::verifyTwoFactor()`

**"Stay signed in"** — the cookie holds `selector:validator`. Only the selector is
stored in the clear; the validator is kept as a SHA-256 hash and compared with
`hash_equals`. Tokens are single-use — every successful match rotates the validator — so
a stolen cookie stops working the moment the real owner returns, and the mismatch is
detected and logged, revoking every token for that account.
→ `app/Core/RememberToken.php`

---

## Sessions

- `HttpOnly`, `SameSite=Lax`, and `Secure` whenever the request is over HTTPS
- `session.use_strict_mode=1` — the server never accepts a session id it did not issue
- `session.use_only_cookies=1` — a session id in a query string is never honoured
- New session id on every privilege change (login, logout, password change)
- Periodic id rotation every 15 minutes
- Idle timeout (1 hour) and an absolute lifetime (12 hours)
- **Client fingerprint** — the session is bound to a hash of the user agent and the /24
  of the IP. A cookie replayed from a different machine is rejected and logged. The
  coarse IP prefix means normal mobile network changes do not sign people out.
- **Credential stamp** — the session stores an HMAC of the password hash. Changing a
  password invalidates every other session for that account on its next request.

→ `app/Core/Session.php`, `app/Core/Auth.php`

---

## CSRF

A synchroniser token, one secret per session, compared with `hash_equals`.

The check is enforced **centrally in the kernel**, not per-controller:

```php
if (!str_starts_with($request->path(), '/webhooks/')) {
    Csrf::verify($request);
}
```

Every `POST`, `PUT`, `PATCH` and `DELETE` is covered automatically. A new form cannot
accidentally opt out, because opting out is not possible without editing the kernel.
Behind the token there is a second layer: the `Origin`/`Referer` host is compared to
our own, rejecting cross-origin form posts outright.

The PayPal webhook is the single exemption — it has no session and no browser, and is
authenticated by signature instead (below).

→ `app/Core/Csrf.php`, `app/Core/Kernel.php`

---

## Authorisation

Three layers, because one is never enough:

1. **Route middleware** — `role:artist`, `role:admin`, `auth`, `verified`. Declared in
   `routes/web.php` so the entire access model is readable on one screen rather than
   scattered through controllers where one can be forgotten.

2. **Ownership assertions** — `Controller::authorizeOwner()` on every record fetched by
   id. Failures are logged with the actor, the owner and the context.

3. **Ownership in the query** — the important reads are scoped in SQL rather than
   fetched and then checked, e.g. `Service::findForArtist($id, $artistId)`. A record
   belonging to someone else simply does not come back.

Cross-role access returns 403 and is written to `storage/logs/security.log`.

Verified by the smoke tests: a client hitting `/artist` gets 403; an artist hitting
another artist's booking gets 403; a client hitting an unrelated booking gets 403.

---

## SQL injection

Every query in the application goes through `Database::run()`, which uses prepared
statements with bound parameters. There is no code path that concatenates user input
into SQL.

`PDO::ATTR_EMULATE_PREPARES` is **false**, so the MySQL driver parses the statement and
the values separately — the values are never interpolated into a query string at all.

Where SQL must be assembled dynamically (search filters, sort orders, column updates):

- Sort keys come from a **whitelist** `match` expression, never from raw input
- Updatable columns are checked against an **allow-list** before being added to a `SET`
  clause — `User::update()`, `Artist::updateProfile()`, `Plan::update()`
- `LIMIT`/`OFFSET` values are cast to `int` and clamped to a range
- `LIKE` terms have `%` and `_` escaped, so a client searching for "50%" gets a literal
  search rather than a wildcard

> **Implementation note.** With emulated prepares disabled, PDO will not bind one named
> placeholder to several positions in a statement. Multi-column `LIKE` searches
> therefore use `:search1`, `:search2`, … bound to the same value. A scanner for this
> mistake is worth keeping; getting it wrong throws `Invalid parameter number` at
> runtime rather than at parse time.

---

## Cross-site scripting

All dynamic output goes through `e()`, which is
`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` — correct for both HTML
text and quoted attribute contexts. There is no raw echo of user data anywhere in the
views.

A **Content Security Policy** provides defence in depth:

```
default-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self';
script-src 'self' https://www.paypal.com …
```

Note that `script-src` does **not** include `'unsafe-inline'`. The application contains
no inline scripts at all — every behaviour is bound via `data-` attributes in
`assets/js/app.js`. That is what makes the strict policy possible, and it means an
injected `<script>` tag will not execute even if escaping were somehow bypassed.

Alongside it: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
`Referrer-Policy: strict-origin-when-cross-origin`, a restrictive `Permissions-Policy`,
and HSTS when the connection is secure.

→ `app/Core/Response.php`, `app/Support/helpers.php`

---

## File uploads

Uploaded images are treated as hostile input. The original file is never trusted and
never kept:

1. `is_uploaded_file()` confirms it genuinely arrived via an upload
2. Size is capped at 5 MB, and pixel dimensions at 50 megapixels — a decompression
   bomb is rejected before GD allocates memory for it
3. The type is detected from the file's **bytes** with `getimagesize()`, not from the
   client-supplied filename or MIME type, and checked against an allow-list of
   JPEG/PNG/WebP
4. The image is **decoded and re-encoded through GD**. This is the important step: it
   discards EXIF (including the GPS coordinates phones embed in photos), any appended
   payload, and polyglot files that are simultaneously a valid image and a valid script
5. It is written under a random 32-character hex name with an extension derived from
   the detected type — never from user input
6. The destination folder name is validated against `^[a-z0-9_-]+$`, so traversal is
   impossible
7. Deletion resolves the real path and refuses anything outside `public/uploads`

Belt and braces, `public/uploads/.htaccess` disables the PHP engine, strips every
executable handler, sets a restrictive CSP with `sandbox`, and denies any file that is
not a recognised image extension.

→ `app/Core/Uploader.php`, `public/uploads/.htaccess`

---

## Payments

**No card data ever touches this database.** There is no card table, no card column, no
card field. PayPal holds the payment instrument; the application stores only a
subscription reference and a status.

**Webhook authentication** — every incoming webhook payload is sent back to PayPal's
`verify-webhook-signature` endpoint before a single row is touched. A missing or
invalid signature is rejected with 401 and logged. If `PAYPAL_WEBHOOK_ID` is not
configured, *all* webhooks are rejected — failing closed rather than open.

**Replay protection** — events are de-duplicated on PayPal's event id, recorded in
`subscription_events`. PayPal retries deliveries until it gets a 2xx, and a replayed
"payment completed" must not extend a subscription twice.

**The redirect is not trusted** — when PayPal returns the artist to
`/artist/subscription/return`, the `subscription_id` in the query string is
attacker-controllable. It is used only to look up a row *the application itself*
created for *the currently signed-in artist*, and the actual status is then re-read
from the PayPal API. A forged return URL cannot activate a plan.

**Entitlements are enforced on the write path** — `Subscription::serviceAllowance()` is
checked in the controller before anything is created or published, not only in the
template. Plan-gated features like instant booking ignore the submitted field entirely
unless the plan permits it, so a crafted POST cannot enable them. Both are covered by
the smoke tests.

→ `app/Services/PayPalClient.php`, `app/Controllers/Webhook/PayPalController.php`

---

## Privacy

- Email addresses and phone numbers are exposed to the counterparty **only once a
  booking is confirmed** — never on an unaccepted request, and never publicly
- Studio addresses are shown only to clients with a confirmed appointment
- Uploaded photos have their location metadata stripped by re-encoding
- Rate-limit keys are HMACs, so the throttling table never stores raw emails or IPs
- Password reset tokens are stored as SHA-256 hashes; a database leak yields no usable
  reset links
- One session cookie and one optional remember-me cookie. No analytics, no third-party
  tracking

---

## Operational

**Error handling** — stack traces are shown only when `APP_DEBUG=true`. In production
the user sees a styled error page while the detail goes to `storage/logs/app.log`.
The kernel refuses to boot at all if `APP_KEY` is missing or still the placeholder.

**Audit log** — every administrative action writes to `audit_logs` with the actor, the
target, the metadata and the IP. It is append-only by convention: the model exposes no
update or delete method, so the trail cannot be quietly edited from inside the app.

**Security log** — failed logins, lockouts, CSRF rejections, session fingerprint
mismatches, remember-me validator mismatches, cross-role access attempts and rejected
webhooks all go to `storage/logs/security.log` for review.

**Admin creation is CLI-only.** `php bin/admin.php`. There is no web-accessible setup
page.

---

## What changed from v1

| v1 problem | v2 |
|---|---|
| `payment_methods` stored **card numbers and CVVs in plaintext** | Table does not exist. PayPal holds the instrument; no card data is stored at all. Storing a CVV is prohibited under PCI DSS even encrypted. |
| **No CSRF protection** on any form | Central kernel-level enforcement on every state-changing request, plus an origin check |
| `admin-setup.php` **publicly reachable** on the web root, secured by "remember to delete it" | Admin accounts are created from the command line only |
| Passwords hashed with bcrypt defaults, never upgraded | Argon2id with tuned parameters, transparent rehash on login |
| No rate limiting — unlimited password guesses | Dual IP + account throttling on login, plus registration, reset and 2FA limits |
| Sessions with default cookie flags, no timeout, no rotation | HttpOnly/SameSite/Secure, strict mode, fingerprint binding, idle and absolute timeouts, rotation on privilege change |
| Uploads validated by file extension | Byte-level type detection, GD re-encode, random names, PHP execution disabled in the directory |
| Booking date/time was free text — no conflict checking | Availability engine with weekly rules, dated blocks, buffer time, and a `FOR UPDATE` row lock so two clients cannot take the same slot |
| Authorisation checked ad hoc inside 1,000-line page scripts | Route middleware + ownership assertions + ownership scoped into the SQL |
| Data split between a database and JSON "state" files that could disagree | Single normalised schema with foreign keys and transactions |
| No audit trail | Append-only `audit_logs`, plus a dedicated security log |

---

## Reporting a problem

Email the address in `SUPPORT_EMAIL`. Please do not open a public issue for a security
report.
