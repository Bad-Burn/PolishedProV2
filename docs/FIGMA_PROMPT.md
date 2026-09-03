# Figma prompt — PolishedPro (working prototype)

Paste everything below the horizontal rule into **Figma Make**. It asks for a
working, clickable app — not static artboards — so you can demo the real flows in
front of an audience.

Design tokens are lifted from `public/assets/css/app.css`; the seed data is lifted
from the running database, so the prototype matches the real system.

**Build it in two passes.** Send Pass 1 first and let it finish. Then send Pass 2
as a follow-up in the same Make session. One shot at everything will drift.

---

# PASS 1 — the working core

Build a **working, clickable prototype** of **PolishedPro**, a booking platform for
independent beauty professionals in the Philippines — nail artists, hairstylists,
make-up artists, barbers and therapists.

This must be a **functioning app**, not a set of static mockups. Every screen is
reachable by clicking. Every control responds. Actions change state, and the state
persists as you navigate. I will demo this live in front of an audience without
touching code.

## What "working" means

- **Client-side routing** between all screens, with a browser-style back that works.
- **In-memory state**, seeded with the demo data below. Actions mutate it and the
  changes show up everywhere they should.
- **A role switcher** pinned in the top-right corner, always visible: `Client ·
  Artist · Admin`. Switching instantly changes who is signed in and what the app
  shows. This is a demo control, not a real product feature — style it as a subtle
  floating pill so it reads as scaffolding.
- **A "Reset demo data" button** next to it, so I can re-run the demo cleanly.
- **A light/dark toggle** in the header that actually switches the theme.
- **Responsive** down to 390px, with a working mobile nav.

The single most important requirement: **the booking flow must genuinely work.**
Picking a date and a time slot and confirming must create a real booking that then
appears in the client's booking list, in the artist's request queue, and in the
admin's all-bookings table. If only one thing works, make it that.

## Design tokens — use these exactly

Define these as CSS custom properties on `:root` and use them everywhere. Both
themes are required.

**Light**
- Canvas `#fbf8f5` · Canvas sunk `#f4efe9` · Surface `#ffffff` · Surface muted `#f8f4f0`
- Ink `#1a1614` · Ink soft `#4a423d` · Ink muted `#7a6f68` · Ink faint `#a89d95`
- Line `#e7ded5` · Line strong `#d6c9bc`
- Accent (wine) `#7a2740` · Accent hover `#63202f` · Accent soft `#f6e9ec` · Accent line `#e3c8d0` · Accent ink `#ffffff`
- Gold `#a97c3f` · Gold soft `#f7f0e3`
- Positive `#2f6b46` / soft `#e7f2ea` · Caution `#8a6212` / soft `#fbf1dc`
- Critical `#9b2c2c` / soft `#fbeaea` · Info `#2c5a80` / soft `#e8f0f6`

**Dark**
- Canvas `#141110` · Canvas sunk `#0e0c0b` · Surface `#1c1817` · Surface raised `#231e1c` · Surface muted `#221d1b`
- Ink `#f5f0ec` · Ink soft `#cdc3bc` · Ink muted `#9a8e86` · Ink faint `#6f645d`
- Line `#2e2825` · Line strong `#423a35`
- Accent (rose) `#e08fa4` · Accent hover `#eaa5b6` · Accent soft `#2b1a1f` · Accent line `#4a2b34` · Accent ink `#1a1614`
- Gold `#d0a765` / soft `#2b2317`
- Positive `#7fc79b` / soft `#17251c` · Caution `#dcb464` / soft `#2a2215`
- Critical `#e89393` / soft `#2b1817` · Info `#8fb8d8` / soft `#16222b`

The accent **flips** between themes: deep wine on light, soft rose on dark.

**Type** — two families only, loaded from Google Fonts.
- **Fraunces** (serif, weight 400) for display headings only.
- **Inter** (400/500/600/700) for body and all interface text.
- Display 1 — Fraunces 88px, line-height 1.02, letter-spacing −3%
- Display 2 — Fraunces 52px, line-height 1.08, letter-spacing −2.5%
- Heading — Inter 600, 18px · Body — Inter 15–16px, line-height 1.6
- Eyebrow — Inter 600, 11px, UPPERCASE, letter-spacing 13%, Ink muted
- Small 13px · Tiny 12px

**Spacing** — 4px scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96.
**Radii** — 4 / 8 / 12 / 18 / 999. Pills (999) are for badges only; buttons and cards never go fully round.
**Shadows** — `0 1px 2px rgba(26,22,20,.05)`, `0 4px 16px -6px rgba(26,22,20,.14)`, `0 18px 44px -20px rgba(26,22,20,.28)`.
**Layout** — 1216px max content width, 12 columns, 24px gutters. Sticky 72px header. Dashboard sidebar 248px, sticky, on Canvas-sunk with a right hairline border.

The look to hit: a **printed salon menu**, not a SaaS dashboard. Warm porcelain
paper, deep wine ink, generous whitespace, hairline borders doing most of the
separating work. Shadows only on genuinely raised surfaces — dropdowns, modals, the
floating search card. Editorial and calm. Never neon, never gradient-heavy, never
glassmorphic.

## Seed data — use exactly this

**Categories:** Nails · Hair · Make-up · Lashes & Brows · Skin · Barbering · Massage & Spa

**Artists** (name · area · headline · rating · reviews · plan)
1. Mariel Santos Nails · Makati · "Structured gel that lasts four weeks" · 4.9 · 34 · Signature
2. Jonas Rivera Hair · Tomas Morato, QC · "Colour correction and lived-in blondes" · 4.8 · 27 · Studio
3. Andrea Lim Makeup · Kapitolyo, Pasig · "Bridal and event make-up that survives a Manila afternoon" · 5.0 · 19 · Studio
4. Studio Kim Lashes · BGC, Taguig · "Lash extensions and brow lamination" · 4.7 · 41 · Studio
5. Rica Skin Studio · Katipunan, QC · "Facials for acne-prone and sensitive skin" · 4.9 · 22 · Signature
6. Bautista Barbers · Mandaluyong · "Fades, beard work, hot towel finish" · 4.6 · 58 · Starter
7. Grace Molina Therapy · Double Dragon, Pasay · "Therapeutic massage for desk-bound bodies" · 4.8 · 16 · Starter
8. Tine Aquino · Salcedo, Makati · "Minimal nails, clean sets, quick appointments" · 4.7 · 30 · Starter

**Services** (name · price · duration · artist)
- Structured gel manicure · ₱950 · 90 min · Mariel
- Classic pedicure · ₱700 · 60 min · Mariel
- Soak-off and rebalance · ₱1,200 · 120 min · Tine
- Full balayage · ₱5,500 · 210 min · Jonas
- Skin fade · ₱450 · 45 min · Bautista
- Hydrating facial · ₱1,800 · 60 min · Rica
- Acne treatment facial · ₱2,200 · 90 min · Rica
- Everyday make-up lesson · ₱4,000 · 120 min · Andrea
- Bridal trial · ₱3,500 · 120 min · Andrea

**Signed-in demo users:** Client — *Bea Ramos*. Artist — *Mariel Santos* (Signature
plan, 8 of unlimited services). Admin — *J. Driodoco*.

**Plans:** Starter free (3 services, 6 photos, standard placement) · Studio ₱349/mo
(15 services, 30 photos, priority placement, analytics, instant booking) ·
Signature ₱749/mo (unlimited, top placement, analytics, instant booking). Zero
commission on bookings at every tier.

Pre-seed about 12 bookings spread across past and future dates with a mix of
statuses, and around 20 reviews, so every screen has real content in it.

## Screens and the interactions each must support

**1. Home** — Fraunces display headline "Artists people go back to", lede, two CTAs,
a floating search card (service + area + date), a horizontally scrolling category
rail, a featured-artist grid, a three-step "Three steps, no phone tag" section, an
artist-recruitment band.
→ *Working:* the search card submits and lands on the directory with those filters
already applied. Category chips navigate to a filtered directory. Artist cards open
that artist's profile.

**2. Artist directory** — filter sidebar (category, area, price range, minimum
rating), sort dropdown (Recommended · Highest rated · Most reviewed · Price low to
high · Newest), card grid, result count, pagination.
→ *Working:* filters genuinely filter the list and combine with each other. Sort
genuinely reorders — and Recommended puts Signature artists above Studio above
Starter, while every other sort ignores the plan entirely. Clearing a filter
restores results. An empty result shows a real empty state with a "Clear filters"
action.

**3. Artist profile** — cover image, overlapping avatar, name with headline and
plan/verified badges, rating summary, tabs (Services · Portfolio · Reviews · About),
service list with Book buttons, portfolio grid, review list.
→ *Working:* tabs switch content without a page change. The heart favourites the
artist and it appears on the client's Saved page. Book opens the booking flow for
that service.

**4. Booking flow** — the centrepiece, must fully work. Service summary, a
horizontal rail of the next 14 days, a time-slot grid, a notes field, and a sticky
booking panel with the price breakdown and a confirm button.
→ *Working:* picking a date loads that day's slots. Slots run on a **15-minute
grid** inside the artist's working hours (9:00–18:00 weekdays, 10:00–16:00 Saturday,
closed Sunday — show Sunday as unavailable). Already-booked slots render disabled.
A slot needs enough room for the service's full duration or it isn't offered — a
210-minute balayage cannot start at 17:00. Slots less than 2 hours from now are
unavailable. Confirming creates the booking, generates a reference like
`PP-8716BA20`, shows a success screen, and the booking then appears in the client's
list, the artist's queue, and the admin's table.

**5. Sign in / Register** — email and password, inline validation, a client/artist
toggle on register.
→ *Working:* sign-in accepts anything and drops you into the matching dashboard.
Empty fields show real inline errors.

**6. Client dashboard** — sidebar shell. My bookings (upcoming/past tabs), booking
detail with an event timeline and reference code, Saved artists, My reviews.
→ *Working:* cancelling a booking moves it to Cancelled and updates the artist's
view. Leaving a review opens a modal with a star picker, and the review then appears
on the artist's public profile. Unfavouriting removes the card from Saved.

**7. Artist dashboard** — sidebar shell. Overview with stat tiles (bookings this
month, revenue, average rating, profile views), today's schedule, recent activity.
Bookings queue. Services table. Schedule editor. Portfolio grid.
→ *Working:* Accept and Decline on a pending booking change its status, and the
client sees the change. The services table's active/draft toggle works and enforces
the plan cap — a Starter artist at 3 active services gets a real "Upgrade to add
more" block rather than a hidden button. The schedule editor's day toggles and time
pickers persist.

**8. Admin dashboard** — sidebar shell. Overview with platform stats and a
pending-artist approval queue, Users table with role filter, all Bookings, Reviews
moderation, Subscriptions with a manual grant-plan action, Audit log.
→ *Working:* approving a pending artist moves them out of the queue and makes them
visible in the public directory. Hiding a review removes it from the artist's public
profile. Every admin action appends a timestamped row to the audit log — actor,
action, target.

## Acceptance checklist

Before you call it done, all of these must click through end to end:

1. Home → category chip → directory filters to that category → artist card → profile → Book → pick date → pick slot → confirm → success screen with reference → the booking is in My Bookings.
2. Switch to Artist → the new booking is in the queue as Pending → Accept → switch to Client → it now reads Confirmed.
3. Client → a past booking → Leave a review → 5 stars → the review is live on that artist's public profile.
4. Switch to Admin → Users → approve the pending artist → they appear in the public directory → the action is in the audit log.
5. Directory → set a price filter and sort by Highest rated → results actually change → clear filters → results come back.
6. Toggle dark mode on every screen. Nothing is unreadable.
7. Resize to 390px. Navigation still works and nothing overflows sideways.

## Constraints

- Two typefaces only: Fraunces for display, Inter for everything else.
- The wine accent marks the primary action — roughly once per view, not everywhere.
- Only badges are pills. Buttons and cards use 4 / 8 / 12 / 18px radii.
- Every text-on-background pair clears WCAG AA in both themes.
- Build the real states, not just the happy path: empty lists, disabled slots,
  validation errors, and the plan-limited upsell.
- Buttons say what happens. "Confirm booking", then a toast that says "Booking
  confirmed".

---

# PASS 2 — send this after Pass 1 finishes

Keep everything already built. Same tokens, same data, same routing. Now add:

**Screens**
- **Pricing** — the three plan cards with a working monthly/yearly toggle that
  changes the prices, a comparison table, and an FAQ accordion that opens and closes.
- **For artists** — recruitment landing page with a working sign-up CTA.
- **Artist analytics** — a bookings-over-time line chart, a revenue bar chart, and a
  top-services list. Also build the **locked** state a Starter artist sees: the
  charts blurred behind an upgrade prompt. Make the role switcher able to demo a
  Starter artist so I can show the lock.
- **Subscription** — current plan, usage meters against the plan's limits, an
  upgrade flow, billing history.
- **Notifications** — a full page plus the header bell dropdown, with unread dots,
  mark-as-read, and mark-all-read all working.
- **Settings** — profile, and a security tab with a two-factor setup flow.
- **Admin plans editor** and **platform settings**.
- **Categories index**, Help, Terms, Privacy.
- **Error states** — 404, 403, and a 419 "session expired" page.

**Polish**
- Skeleton loading states on the directory and dashboards, shown for ~400ms on
  navigation so the app feels real.
- Toasts for every successful action.
- A confirmation modal before anything destructive.
- Keyboard focus rings in the accent colour on every interactive element.
- `prefers-reduced-motion` respected.
