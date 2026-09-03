# Architecture

How the pieces fit together, and the reasoning behind the choices that are not obvious.

---

## Why plain PHP, and no Composer

The platform has to run on XAMPP during development and on inexpensive shared hosting
in production. Every dependency is a thing that can fail to install on a host you do
not control.

So: a hand-written PSR-4 autoloader (`app/Core/Autoloader.php`), a small router, and
plain-PHP templates. PayPal is spoken to over its REST API with cURL rather than
through an SDK. Upload it and it runs — no `composer install`, no `npm run build`.

The trade-off is that some wheels are reinvented. They are kept deliberately small:
the router is ~150 lines, the view layer ~120, the validator ~250.

---

## Request lifecycle

```
public/index.php
  ├─ define PP_ROOT / PP_PUBLIC / PP_APP / PP_VIEWS
  ├─ register the autoloader
  ├─ load app/Support/helpers.php
  ├─ Kernel::boot()          .env → config → timezone → error reporting → APP_KEY check
  ├─ routes/web.php          build the route table
  └─ Kernel::handle()
       ├─ Response::securityHeaders()   CSP and friends, before any output
       ├─ Session::start()              hardened cookie params, timeouts, fingerprint
       ├─ Csrf::verify()                every unsafe method, centrally
       ├─ View::share()                 current user, flashes, form state
       └─ Router::dispatch()
            ├─ middleware   auth / role / throttle / subscription / nocache
            └─ Controller   → Model → View
```

Errors are caught in `Kernel::handle()`. `HttpException` renders a styled error page;
anything else is logged and becomes a 500 (or a stack trace when `APP_DEBUG=true`).

---

## Directory decisions

**`public/` is the only web-reachable directory.** Everything else sits above it. This
is what makes `.env` and the application code unreachable even if `.htaccess` is
ignored — a far stronger guarantee than a deny rule.

**`routes/web.php` holds the entire access model.** Authorisation is declared beside
each route rather than inside controllers, so "who can reach what" is one screen of
reading rather than an audit of thirty files.

**Controllers are grouped by role** (`Controllers/Artist/`, `Controllers/Admin/`) so
adding a feature to one area does not mean scrolling past another's.

---

## The view layer

Templates are plain PHP. A template chooses its own layout and fills named sections:

```php
layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan]);
end_section();
```

`View::render()` captures the template first, then the layout, so sections are
populated by the time the layout runs.

The escaping funnel is `e()`. Every dynamic value in every template goes through it.
There is no "raw" helper, deliberately — the absence of an easy escape hatch is what
keeps the invariant true.

---

## Data model

The v1 schema kept artist-specific columns on `users` and duplicated state in JSON
files alongside the database. v2 normalises:

```
users ──┬── artist_profiles      (1:1, artists only)
        ├── services ── portfolio_items
        ├── availability_rules   (recurring weekly)
        ├── availability_blocks  (one-off closures)
        ├── bookings ──┬── booking_events   (append-only timeline)
        │              └── reviews          (unique per booking)
        ├── favorites
        ├── subscriptions ── plans
        ├── notifications
        └── audit_logs
```

Two deliberate denormalisations:

- `bookings.service_name` and `bookings.price` are **copied in at booking time**, so a
  historical booking still reads correctly after the artist renames or deletes the
  service. `service_id` becomes `NULL` rather than cascading the row away.
- `artist_profiles.rating_avg` / `rating_count` are cached and recomputed by
  `Artist::refreshRating()` whenever a review is created, hidden or deleted. Search
  sorts on rating constantly; recomputing an average across a join on every query would
  not survive growth.

Two deliberate absences:

- **No card table.** PayPal holds the payment instrument. v1 stored card numbers and
  CVVs in plaintext, which is prohibited outright.
- **No JSON state files.** One source of truth.

---

## Availability engine

The part of v1 that most needed replacing: booking date and time were free text, with
no conflict checking at all.

`AvailabilityService::slotsFor()` composes four inputs:

1. `availability_rules` — the recurring working week (weekday, start, end). Multiple
   windows per day support split shifts.
2. `availability_blocks` — dated closures, whole-day or partial.
3. Existing bookings in `pending` or `confirmed` status.
4. The service's `duration_min` **plus** its `buffer_min`, so clean-up time is reserved
   and the next client is not booked ten minutes after a two-hour set.

Slots are generated on a 15-minute grid, filtered against a minimum lead time and a
maximum booking horizon, and any slot overlapping a busy range is dropped.

### Preventing double bookings

The slot list in the browser is a convenience and may be seconds stale. The authority
is `BookingService::create()`:

```php
Database::transaction(function () {
    Database::run(
        'SELECT id FROM bookings
          WHERE artist_id = :a AND status IN ("pending","confirmed")
            AND starts_at < :ends AND ends_at > :starts
          FOR UPDATE',
        [...]
    );

    if (Booking::hasConflict($artistId, $startsAt, $endsAt)) {
        return ['conflict' => true];
    }

    // …insert
});
```

`FOR UPDATE` takes a row lock on the overlapping range, so two clients clicking the
same slot in the same instant cannot both succeed — the second blocks, then re-checks
and loses. Overlap is a plain range test (`a.start < b.end AND a.end > b.start`),
which the `(artist_id, starts_at, ends_at)` index serves directly.

Storing `ends_at` rather than deriving it from the duration is what makes that test a
single indexed comparison.

---

## Subscriptions and entitlements

`Subscription::planFor($artistId)` is the single source of truth for what an artist may
do. It always returns a plan — falling back to the free tier — so callers never
null-check before reading a limit.

Controllers ask, rather than reading limits themselves:

```php
$allowance = Subscription::serviceAllowance($artistId);
if (!$allowance['allowed']) {
    $this->failure($allowance['message'], '/artist/subscription');
}
```

One place to audit when the rules change, and the check sits on the **write path** —
hiding a button in a template is not a limit.

Only *active* services count against the cap, so an artist at their limit can still
draft a service and choose which one is published. Downgrading hides services beyond
the new limit rather than deleting them; upgrading brings them straight back.

### Search placement

`Artist::search()` sorts by `plan_weight DESC` on the default "Recommended" order, with
rating and review count breaking ties. Every other sort — highest rated, most reviewed,
price, newest — ignores the plan entirely.

That boundary is intentional and worth keeping. A paid tier buying visibility on a
neutral default is an honest arrangement; a paid tier overriding an explicit "sort by
highest rated" would be a lie to the client.

### Billing state

PayPal is authoritative on whether money moved. The application never marks a
subscription active from a user-controlled redirect — it re-reads the subscription from
the API first. See [SECURITY.md](SECURITY.md#payments).

Webhooks are verified by signature, de-duplicated by event id, and drive the state
machine: `pending → active → past_due → cancelled/expired`.

---

## Front-end

No framework, no build step. One stylesheet for the public site
(`assets/css/app.css`), one more for signed-in areas (`assets/css/dashboard.css`),
one progressive-enhancement script.

The design is token-driven — colour, spacing, type scale and radii are CSS custom
properties, with a full dark-theme override. Light and dark are both first-class; the
toggle sets `data-theme` on the root element and wins over the OS preference in both
directions.

**Everything works without JavaScript.** `app.js` adds a theme toggle, a mobile menu,
optimistic favourite toggling, debounced search, a password-strength hint, double-submit
guards and character counters — all bound via `data-` attributes. Nothing is inline,
which is what allows a CSP without `'unsafe-inline'` on `script-src`.

Dropdowns and confirmation popovers use `<details>/<summary>`, so they open and close
natively with no script at all.

---

## Testing

There is no test framework (no Composer). Verification was done with scripts that
drive the running application over HTTP with a real cookie jar, covering:

- Every public page renders
- Login for all three roles, and cross-role access returning 403
- CSRF rejecting both a forged token and a missing one
- The full booking flow: slot listing → booking → detail page
- Double-booking rejected, and the taken slot disappearing from the grid
- Ownership: an unrelated client and a different artist both blocked from a booking
- Plan limits refusing an over-limit service, and refusing to publish a draft
- Plan-gated features ignoring a forged POST
- Search placement ordering by plan on "recommended" and by rating on "highest rated"

Worth keeping: a scanner for SQL strings that bind the same named placeholder twice.
With `EMULATE_PREPARES` off that throws at runtime rather than at parse time, so it
hides in rarely-exercised filter branches.
