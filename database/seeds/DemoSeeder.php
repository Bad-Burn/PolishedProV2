<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use App\Core\Hash;

/**
 * Seeds the platform with reference data plus a believable demo dataset.
 *
 * Reference data (categories, plans, settings) is idempotent and safe to
 * re-run. The demo accounts are only created when the users table is empty,
 * so seeding a live database will not manufacture fake artists.
 */
final class DemoSeeder
{
    /** Every demo account shares this password — documented in the README. */
    public const DEMO_PASSWORD = 'PolishedDemo!2026';

    private array $categoryIds = [];
    private array $planIds     = [];

    public function run(bool $withDemoData = true): void
    {
        $this->seedSettings();
        $this->seedCategories();
        $this->seedPlans();

        if (!$withDemoData) {
            return;
        }

        $existing = (int) Database::scalar("SELECT COUNT(*) FROM users WHERE role <> 'admin'");
        if ($existing > 0) {
            $this->out('Skipping demo data — the users table is not empty.');
            return;
        }

        Database::transaction(function (): void {
            $artists   = $this->seedArtists();
            $customers = $this->seedCustomers();
            $this->seedBookingsAndReviews($artists, $customers);
        });
    }

    // -- Reference data -----------------------------------------------------

    private function seedSettings(): void
    {
        $defaults = [
            'platform_name'        => 'PolishedPro',
            'platform_tagline'     => 'Book beauty professionals you can actually trust.',
            'support_email'        => 'support@polishedpro.test',
            'booking_fee_percent'  => '0',
            'allow_registrations'  => '1',
            'require_artist_approval' => '1',
            'maintenance_mode'     => '0',
        ];

        foreach ($defaults as $key => $value) {
            Database::affectingStatement(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_key = setting_key',
                ['k' => $key, 'v' => $value]
            );
        }

        $this->out('Settings ready.');
    }

    private function seedCategories(): void
    {
        $categories = [
            ['Nails',            'nails',      'Manicures, pedicures, gel, extensions and nail art.'],
            ['Hair',             'hair',       'Cuts, colour, treatments and styling.'],
            ['Make-up',          'makeup',     'Everyday, event, bridal and editorial looks.'],
            ['Lashes & Brows',   'lashes-brows', 'Extensions, lifts, laminations and shaping.'],
            ['Skin',             'skin',       'Facials, peels and targeted skin treatments.'],
            ['Barbering',        'barbering',  'Fades, beard work and classic cuts.'],
            ['Massage & Spa',    'massage-spa', 'Relaxation, deep tissue and body treatments.'],
        ];

        foreach ($categories as $i => [$name, $slug, $description]) {
            Database::affectingStatement(
                'INSERT INTO service_categories (name, slug, description, sort_order)
                 VALUES (:n, :s, :d, :o)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)',
                ['n' => $name, 's' => $slug, 'd' => $description, 'o' => $i * 10]
            );
        }

        foreach (Database::select('SELECT id, slug FROM service_categories') as $row) {
            $this->categoryIds[(string) $row['slug']] = (int) $row['id'];
        }

        $this->out('Service categories ready (' . count($this->categoryIds) . ').');
    }

    /**
     * The three tiers. What a paid plan buys is deliberately narrow and honest:
     * more active services, more portfolio slots, and higher placement in
     * search results. Nothing here hides a customer-facing safety feature.
     */
    private function seedPlans(): void
    {
        $plans = [
            [
                'code'    => 'premium',
                'name'    => 'Premium',
                'tagline' => 'For independent artists ready to grow their bookings.',
                'monthly' => 149.00,
                'yearly'  => 0.00,
                'services'  => 3,
                'portfolio' => 6,
                'weight'    => 0,
                'badge'     => null,
                'analytics' => 0,
                'instant'   => 0,
                'highlights' => [
                    'Up to 3 active services',
                    '6 portfolio photos',
                    'Standard search placement',
                    'Booking calendar and client messages',
                    'Reviews and ratings',
                ],
            ],
            [
                // Retired: replaced by the free trial. Seeded inactive so old
                // subscription rows keep a valid foreign key, and so the plan
                // chooser never shows it.
                'code'    => 'starter',
                'name'    => 'Starter (retired)',
                'tagline' => 'Replaced by the free trial. Kept for historical subscriptions.',
                'active'  => 0,
                'monthly' => 0.00,
                'yearly'  => 0.00,
                'services'  => 3,
                'portfolio' => 6,
                'weight'    => 0,
                'badge'     => null,
                'analytics' => 0,
                'instant'   => 0,
                'highlights' => [
                    'Up to 3 active services',
                    '6 portfolio photos',
                    'Standard search placement',
                    'Booking calendar and client messages',
                    'Reviews and ratings',
                ],
            ],
            [
                'code'    => 'studio',
                'name'    => 'Studio',
                'tagline' => 'For artists with a full menu and a steady calendar.',
                'monthly' => 349.00,
                'yearly'  => 3490.00,
                'services'  => 15,
                'portfolio' => 30,
                'weight'    => 10,
                'badge'     => 'Studio',
                'analytics' => 1,
                'instant'   => 1,
                'highlights' => [
                    'Up to 15 active services',
                    '30 portfolio photos',
                    'Priority placement in search results',
                    'Studio badge on your profile',
                    'Performance analytics dashboard',
                    'Instant booking (skip manual approval)',
                ],
            ],
            [
                'code'    => 'signature',
                'name'    => 'Signature',
                'tagline' => 'Maximum reach for established studios and teams.',
                'monthly' => 749.00,
                'yearly'  => 7490.00,
                'services'  => null,   // unlimited
                'portfolio' => null,
                'weight'    => 20,
                'badge'     => 'Signature',
                'analytics' => 1,
                'instant'   => 1,
                'highlights' => [
                    'Unlimited services and portfolio photos',
                    'Top placement in search and category pages',
                    'Signature badge on your profile',
                    'Featured on the homepage rotation',
                    'Full analytics with revenue breakdowns',
                    'Instant booking and priority support',
                ],
            ],
        ];

        foreach ($plans as $i => $plan) {
            Database::affectingStatement(
                'INSERT INTO plans
                    (code, name, tagline, price_monthly, price_yearly, currency,
                     max_services, max_portfolio_items, featured_weight, badge,
                     has_analytics, has_instant_book, highlights, is_active, sort_order)
                 VALUES
                    (:code, :name, :tagline, :monthly, :yearly, :currency,
                     :services, :portfolio, :weight, :badge,
                     :analytics, :instant, :highlights, :active, :sort)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name), tagline = VALUES(tagline),
                    price_monthly = VALUES(price_monthly), price_yearly = VALUES(price_yearly),
                    max_services = VALUES(max_services), max_portfolio_items = VALUES(max_portfolio_items),
                    featured_weight = VALUES(featured_weight), badge = VALUES(badge),
                    has_analytics = VALUES(has_analytics), has_instant_book = VALUES(has_instant_book),
                    highlights = VALUES(highlights), sort_order = VALUES(sort_order),
                    is_active = VALUES(is_active)',
                [
                    'code'      => $plan['code'],
                    'name'      => $plan['name'],
                    'tagline'   => $plan['tagline'],
                    'monthly'   => $plan['monthly'],
                    'yearly'    => $plan['yearly'],
                    'currency'  => 'PHP',
                    'services'  => $plan['services'],
                    'portfolio' => $plan['portfolio'],
                    'weight'    => $plan['weight'],
                    'badge'     => $plan['badge'],
                    'analytics' => $plan['analytics'],
                    'instant'   => $plan['instant'],
                    'highlights'=> json_encode($plan['highlights'], JSON_UNESCAPED_UNICODE),
                    // Retired plans seed as inactive so they never reach the chooser.
                    'active'    => $plan['active'] ?? 1,
                    'sort'      => $i * 10,
                ]
            );
        }

        foreach (Database::select('SELECT id, code FROM plans') as $row) {
            $this->planIds[(string) $row['code']] = (int) $row['id'];
        }

        $this->out('Subscription plans ready (' . count($this->planIds) . ').');
    }

    // -- Demo data ----------------------------------------------------------

    /** @return array<int, int> artist user ids */
    private function seedArtists(): array
    {
        $artists = [
            [
                'name' => 'Mariel Santos', 'email' => 'mariel@polishedpro.test',
                'display' => 'Mariel Santos Nails', 'city' => 'Makati', 'area' => 'Poblacion',
                'headline' => 'Gel extensions and hand-painted nail art',
                'bio' => "I have been doing nails for nine years, the last four out of a small private studio in Poblacion. I work slowly and I only take four clients a day, because rushed nails are nails that lift.\n\nMost of my work is structured gel — overlays, extensions, and the kind of detailed art that takes two hours and is worth every minute. Bring a reference photo or let me improvise.",
                'experience' => 9, 'plan' => 'signature', 'rating' => 4.9, 'reviews' => 128,
                'completed' => 412, 'verified' => 1, 'instagram' => 'marielnails',
                'category' => 'nails',
                'services' => [
                    ['Structured gel manicure', 'Builder gel overlay on your natural nails. Includes cuticle work, shaping and a colour of your choice.', 90, 15, 950],
                    ['Gel extensions — full set', 'Soft gel tips shaped to length. Anything from a short almond to a long coffin.', 150, 15, 1650],
                    ['Nail art (per nail)', 'Hand-painted detail, chrome, 3D or encapsulated work. Priced per nail on top of your base service.', 15, 0, 150],
                    ['Soak-off and rebalance', 'Safe removal of existing gel, plus a fresh overlay. Please book this if you are coming from another artist.', 120, 15, 1200],
                    ['Classic pedicure', 'Soak, shape, cuticle care, callus work and polish. Done in a proper spa chair.', 60, 10, 700],
                ],
            ],
            [
                'name' => 'Jonas Rivera', 'email' => 'jonas@polishedpro.test',
                'display' => 'Jonas Rivera Hair', 'city' => 'Quezon City', 'area' => 'Tomas Morato',
                'headline' => 'Colour correction and lived-in blondes',
                'bio' => "Colourist based in Tomas Morato. I trained in Manila and spent two years assisting in Singapore before coming back.\n\nMy focus is colour that grows out gracefully — balayage, root smudges, and fixing the box dye disasters people are too embarrassed to talk about. No judgement here, I have seen worse.",
                'experience' => 7, 'plan' => 'studio', 'rating' => 4.8, 'reviews' => 94,
                'completed' => 301, 'verified' => 1, 'instagram' => 'jonascolour',
                'category' => 'hair',
                'services' => [
                    ['Consultation', 'Twenty minutes to talk through what you want, check your hair history, and quote you honestly before we book anything.', 20, 0, 0],
                    ['Cut and finish', 'A proper cut with a blow-dry finish. Includes a wash and scalp massage.', 60, 15, 900],
                    ['Full balayage', 'Freehand lightening with a gloss and trim. Long or very dark hair may need a second session.', 210, 30, 5500],
                    ['Root touch-up', 'Regrowth colour on the first four centimetres, blended into your existing length.', 90, 15, 1800],
                    ['Colour correction', 'For box dye, banding or a previous job that went wrong. Priced after a consultation.', 240, 30, 6500],
                    ['Keratin treatment', 'Smoothing treatment that lasts around three months. Frizz-focused, not a permanent straightener.', 180, 20, 4200],
                ],
            ],
            [
                'name' => 'Andrea Lim', 'email' => 'andrea@polishedpro.test',
                'display' => 'Andrea Lim Makeup', 'city' => 'Pasig', 'area' => 'Kapitolyo',
                'headline' => 'Bridal and event make-up that survives a Manila afternoon',
                'bio' => "Fifteen years of weddings has taught me two things: skin prep is everything, and the make-up has to hold up in humidity, in photos, and after the third round of crying.\n\nI travel to you. Trials are strongly recommended for brides and are credited back against your wedding-day booking.",
                'experience' => 15, 'plan' => 'signature', 'rating' => 5.0, 'reviews' => 67,
                'completed' => 189, 'verified' => 1, 'instagram' => 'andrealim.mua',
                'category' => 'makeup',
                'services' => [
                    ['Bridal trial', 'A full run-through of your wedding look. Credited back if you book the wedding day with me.', 120, 20, 3500],
                    ['Bridal day-of make-up', 'Your wedding look on the day, plus a touch-up kit. Travel within Metro Manila is included.', 180, 30, 12000],
                    ['Event make-up', 'Debuts, shoots, corporate events, birthdays. Soft glam by default, but say the word and we go bolder.', 90, 15, 3200],
                    ['Everyday lesson', 'A two-hour session where I teach you your own face, using your own products. Includes a written routine.', 120, 0, 4000],
                ],
            ],
            [
                'name' => 'Kim Delos Reyes', 'email' => 'kim@polishedpro.test',
                'display' => 'Studio Kim — Lashes', 'city' => 'Taguig', 'area' => 'BGC',
                'headline' => 'Lash extensions and brow lamination',
                'bio' => "Lash and brow specialist working out of a shared studio in BGC. I am licensed, I use medical-grade adhesive, and I will tell you honestly if your natural lashes need a break.\n\nRefills every two to three weeks keep a set looking new. Come with clean lashes and no eye make-up, please.",
                'experience' => 5, 'plan' => 'studio', 'rating' => 4.7, 'reviews' => 156,
                'completed' => 520, 'verified' => 1, 'instagram' => 'studiokim.lash',
                'category' => 'lashes-brows',
                'services' => [
                    ['Classic lash set', 'One extension per natural lash. Subtle, and the safest option if you are new to extensions.', 90, 15, 1400],
                    ['Volume lash set', 'Handmade fans for a fuller look. Still lightweight if your natural lashes can carry it.', 120, 15, 2000],
                    ['Lash refill', 'Within three weeks of your last set. After that it is priced as a new set.', 60, 10, 900],
                    ['Brow lamination', 'Sets your brow hairs in place for around six weeks. Includes shaping and tint.', 60, 10, 1300],
                    ['Lash lift and tint', 'Curls and darkens your own lashes. No extensions, no maintenance.', 60, 10, 1500],
                ],
            ],
            [
                'name' => 'Paolo Bautista', 'email' => 'paolo@polishedpro.test',
                'display' => 'Bautista Barbers', 'city' => 'Mandaluyong', 'area' => 'Greenfield',
                'headline' => 'Skin fades, beard sculpting, hot towel shaves',
                'bio' => "Second-generation barber. My father cut hair in the same district for thirty years and I still use two of his razors.\n\nWalk-ins are welcome when the chair is free, but booking saves you the wait. Kids under ten are half price.",
                'experience' => 11, 'plan' => 'starter', 'rating' => 4.6, 'reviews' => 88,
                'completed' => 640, 'verified' => 1, 'instagram' => 'bautistabarbers',
                'category' => 'barbering',
                'services' => [
                    ['Skin fade', 'Clean fade to the skin, blended and finished with a line-up.', 45, 10, 450],
                    ['Cut and beard trim', 'Full haircut plus beard shaping with a hot towel finish.', 60, 10, 650],
                    ['Hot towel shave', 'Traditional straight-razor shave. Two towels, proper lather, no rush.', 45, 10, 550],
                ],
            ],
            [
                'name' => 'Rica Fernandez', 'email' => 'rica@polishedpro.test',
                'display' => 'Rica Skin Studio', 'city' => 'Quezon City', 'area' => 'Katipunan',
                'headline' => 'Facials for acne-prone and sensitive skin',
                'bio' => "Licensed aesthetician. I mostly see people who have tried everything and are tired of being sold twelve products.\n\nWe start with one appointment, one honest assessment, and a routine you can actually stick to. Extractions only when your skin can take them.",
                'experience' => 6, 'plan' => 'studio', 'rating' => 4.9, 'reviews' => 73,
                'completed' => 254, 'verified' => 1, 'instagram' => 'ricaskin',
                'category' => 'skin',
                'services' => [
                    ['Deep cleansing facial', 'Cleanse, steam, gentle extractions, mask and SPF. The standard first appointment.', 75, 15, 1600],
                    ['Acne treatment facial', 'Targeted for active breakouts. Includes a high-frequency pass and a take-home plan.', 90, 15, 2200],
                    ['Chemical peel', 'Light peel for texture and pigmentation. Requires a patch test at least a week before.', 45, 15, 2800],
                    ['Hydrating facial', 'For dry, tight or over-exfoliated skin. No extractions, no actives.', 60, 15, 1800],
                ],
            ],
            [
                'name' => 'Tine Aquino', 'email' => 'tine@polishedpro.test',
                'display' => 'Tine Aquino', 'city' => 'Makati', 'area' => 'Salcedo',
                'headline' => 'Minimal nails, clean sets, quick appointments',
                'bio' => "Short, neat, and out the door in under an hour. I do one thing well: a clean single-colour gel manicure for people who do not have three hours to spare on a Saturday.",
                'experience' => 3, 'plan' => 'starter', 'rating' => 4.5, 'reviews' => 41,
                'completed' => 137, 'verified' => 0, 'instagram' => 'tine.nails',
                'category' => 'nails',
                'services' => [
                    ['Express gel manicure', 'One colour, neat cuticles, forty-five minutes. No art, no extensions.', 45, 10, 650],
                    ['Gel removal', 'Proper soak-off without drilling your nail plate to death.', 30, 5, 350],
                ],
            ],
            [
                'name' => 'Grace Molina', 'email' => 'grace@polishedpro.test',
                'display' => 'Grace Molina Therapy', 'city' => 'Pasay', 'area' => 'Double Dragon',
                'headline' => 'Therapeutic massage for desk-bound bodies',
                'bio' => "Trained in Swedish and deep tissue, with a focus on necks and shoulders wrecked by laptops.\n\nI will ask about your pain before we start and check in during. Tell me if something is too much — pressure should be uncomfortable, never painful.",
                'experience' => 8, 'plan' => 'starter', 'rating' => 4.8, 'reviews' => 52,
                'completed' => 298, 'verified' => 1, 'instagram' => null,
                'category' => 'massage-spa',
                'services' => [
                    ['Deep tissue — 60 min', 'Focused work on your worst two areas. Most people pick neck, shoulders and upper back.', 60, 15, 1200],
                    ['Swedish massage — 90 min', 'Full body, moderate pressure, properly relaxing rather than clinical.', 90, 15, 1600],
                    ['Back and shoulder — 30 min', 'A short, targeted session. Good on a lunch break.', 30, 10, 700],
                ],
            ],
        ];

        $ids = [];

        foreach ($artists as $artist) {
            $userId = Database::insert(
                'INSERT INTO users (role, full_name, email, phone, password_hash, status, approved_at, email_verified_at, created_at)
                 VALUES ("artist", :name, :email, :phone, :hash, "active", :approved, :verified, :created)',
                [
                    'name'     => $artist['name'],
                    'email'    => $artist['email'],
                    'phone'    => '+63 917 ' . random_int(100, 999) . ' ' . random_int(1000, 9999),
                    'hash'     => Hash::make(self::DEMO_PASSWORD),
                    'approved' => date('Y-m-d H:i:s', strtotime('-' . random_int(60, 400) . ' days')),
                    'verified' => date('Y-m-d H:i:s', strtotime('-' . random_int(60, 400) . ' days')),
                    'created'  => date('Y-m-d H:i:s', strtotime('-' . random_int(60, 400) . ' days')),
                ]
            );

            Database::insert(
                'INSERT INTO artist_profiles
                    (user_id, display_name, slug, headline, bio, city, area, years_experience,
                     instagram, is_verified, instant_book, rating_avg, rating_count, completed_bookings,
                     cancellation_policy)
                 VALUES
                    (:id, :display, :slug, :headline, :bio, :city, :area, :years,
                     :instagram, :verified, :instant, :rating, :reviews, :completed, :policy)',
                [
                    'id'        => $userId,
                    'display'   => $artist['display'],
                    'slug'      => $this->uniqueSlug($artist['display']),
                    'headline'  => $artist['headline'],
                    'bio'       => $artist['bio'],
                    'city'      => $artist['city'],
                    'area'      => $artist['area'],
                    'years'     => $artist['experience'],
                    'instagram' => $artist['instagram'],
                    'verified'  => $artist['verified'],
                    'instant'   => $artist['plan'] === 'starter' ? 0 : 1,
                    'rating'    => $artist['rating'],
                    'reviews'   => $artist['reviews'],
                    'completed' => $artist['completed'],
                    'policy'    => 'Free cancellation up to 24 hours before your appointment. Later than that, please message me — I am reasonable about genuine emergencies.',
                ]
            );

            $this->attachSubscription($userId, $artist['plan']);
            $this->attachServices($userId, $artist['category'], $artist['services']);
            $this->attachAvailability($userId);

            $ids[] = $userId;
        }

        $this->out('Created ' . count($ids) . ' artists with services and schedules.');

        return $ids;
    }

    /**
     * Give the artist a paid subscription, or — for the ones the fixtures mark
     * as `starter` — a trial, since there is no free tier to put them on.
     */
    private function attachSubscription(int $userId, string $planCode): void
    {
        if ($planCode === 'starter') {
            Database::affectingStatement(
                'UPDATE users SET trial_ends_at = DATE_ADD(NOW(), INTERVAL :d DAY) WHERE id = :u',
                ['d' => \App\Models\Subscription::trialDays(), 'u' => $userId]
            );
            return;
        }

        $planId = $this->planIds[$planCode] ?? null;
        if ($planId === null) {
            return;
        }

        $isPaid = true;

        $startedAt = date('Y-m-d H:i:s', strtotime('-' . random_int(3, 25) . ' days'));

        // Note the two distinct placeholders for the same value: with emulated
        // prepares switched off, PDO will not let one named parameter be bound
        // to two positions in a statement.
        Database::insert(
            'INSERT INTO subscriptions
                (user_id, plan_id, status, billing_cycle, provider,
                 current_period_start, current_period_end, started_at)
             VALUES (:u, :p, "active", :cycle, "manual", :period_start, :period_end, :started_at)',
            [
                'u'            => $userId,
                'p'            => $planId,
                'cycle'        => 'monthly',
                'period_start' => $startedAt,
                'period_end'   => $isPaid ? date('Y-m-d H:i:s', strtotime('+' . random_int(5, 27) . ' days')) : null,
                'started_at'   => $startedAt,
            ]
        );
    }

    private function attachServices(int $artistId, string $categorySlug, array $services): void
    {
        $categoryId = $this->categoryIds[$categorySlug] ?? null;

        foreach ($services as $i => [$name, $description, $duration, $buffer, $price]) {
            Database::insert(
                'INSERT INTO services
                    (artist_id, category_id, name, description, duration_min, buffer_min, price,
                     is_active, bookings_count, sort_order)
                 VALUES (:a, :c, :n, :d, :dur, :buf, :price, 1, :count, :sort)',
                [
                    'a'     => $artistId,
                    'c'     => $categoryId,
                    'n'     => $name,
                    'd'     => $description,
                    'dur'   => $duration,
                    'buf'   => $buffer,
                    'price' => $price,
                    'count' => random_int(4, 90),
                    'sort'  => $i * 10,
                ]
            );
        }
    }

    /** A believable week: closed Sundays, a half-day Saturday, evenings midweek. */
    private function attachAvailability(int $artistId): void
    {
        $week = [
            1 => ['10:00', '19:00'],
            2 => ['10:00', '19:00'],
            3 => ['10:00', '20:00'],
            4 => ['10:00', '20:00'],
            5 => ['10:00', '20:00'],
            6 => ['09:00', '16:00'],
        ];

        foreach ($week as $weekday => [$start, $end]) {
            Database::insert(
                'INSERT INTO availability_rules (artist_id, weekday, start_time, end_time, is_open)
                 VALUES (:a, :w, :s, :e, 1)',
                ['a' => $artistId, 'w' => $weekday, 's' => $start . ':00', 'e' => $end . ':00']
            );
        }
    }

    /** @return array<int, int> customer user ids */
    private function seedCustomers(): array
    {
        $customers = [
            ['Bea Villanueva',  'bea@example.test'],
            ['Marco Tan',       'marco@example.test'],
            ['Joy Ramos',       'joy@example.test'],
            ['Nikko Cruz',      'nikko@example.test'],
            ['Sam Ocampo',      'sam@example.test'],
            ['Trina Gutierrez', 'trina@example.test'],
        ];

        $ids = [];

        foreach ($customers as [$name, $email]) {
            $ids[] = Database::insert(
                'INSERT INTO users (role, full_name, email, phone, password_hash, status, email_verified_at, created_at)
                 VALUES ("customer", :name, :email, :phone, :hash, "active", :verified, :created)',
                [
                    'name'     => $name,
                    'email'    => $email,
                    'phone'    => '+63 918 ' . random_int(100, 999) . ' ' . random_int(1000, 9999),
                    'hash'     => Hash::make(self::DEMO_PASSWORD),
                    'verified' => date('Y-m-d H:i:s'),
                    'created'  => date('Y-m-d H:i:s', strtotime('-' . random_int(10, 200) . ' days')),
                ]
            );
        }

        $this->out('Created ' . count($ids) . ' customers.');

        return $ids;
    }

    /**
     * A spread of past and upcoming bookings so every dashboard has something
     * real to render, plus reviews on some of the completed ones.
     */
    private function seedBookingsAndReviews(array $artistIds, array $customerIds): void
    {
        $comments = [
            5 => [
                'Exactly what I asked for and she talked me out of a length that would not have suited me. Booking again.',
                'Third time here now. Always on time, always clean, never rushed.',
                'Worth the trip across the city. My friends have all asked who did it.',
                'Genuinely the most comfortable I have felt at an appointment. No upselling at all.',
            ],
            4 => [
                'Really happy with the result. Started about fifteen minutes late but she made up the time.',
                'Great work. The studio is small so bring a jacket, the aircon is aggressive.',
                'Good service and fair pricing. Would have liked a bit more aftercare advice.',
            ],
            3 => [
                'The result was fine but it took much longer than the time quoted.',
                'Decent, though not quite the shade I brought a photo for.',
            ],
        ];

        $bookingCount = 0;
        $reviewCount  = 0;

        foreach ($artistIds as $artistId) {
            $services = Database::select(
                'SELECT id, name, duration_min, buffer_min, price FROM services WHERE artist_id = :a',
                ['a' => $artistId]
            );

            if ($services === []) {
                continue;
            }

            // Past, completed bookings.
            for ($i = 0; $i < random_int(4, 9); $i++) {
                $service    = $services[array_rand($services)];
                $customerId = $customerIds[array_rand($customerIds)];
                $start      = strtotime('-' . random_int(2, 120) . ' days ' . random_int(10, 17) . ':00');

                $bookingId = $this->insertBooking($customerId, $artistId, $service, $start, 'completed');
                $bookingCount++;

                if (random_int(1, 100) <= 65) {
                    $rating = match (true) {
                        random_int(1, 100) <= 70 => 5,
                        random_int(1, 100) <= 80 => 4,
                        default                  => 3,
                    };

                    Database::insert(
                        'INSERT INTO reviews (booking_id, customer_id, artist_id, service_id, rating, comment, created_at)
                         VALUES (:b, :c, :a, :s, :r, :comment, :created)',
                        [
                            'b'       => $bookingId,
                            'c'       => $customerId,
                            'a'       => $artistId,
                            's'       => $service['id'],
                            'r'       => $rating,
                            'comment' => $comments[$rating][array_rand($comments[$rating])],
                            'created' => date('Y-m-d H:i:s', $start + 86400),
                        ]
                    );
                    $reviewCount++;
                }
            }

            // Upcoming bookings, on the hour, on working days only.
            for ($i = 0; $i < random_int(2, 5); $i++) {
                $service = $services[array_rand($services)];
                $start   = strtotime('+' . random_int(1, 21) . ' days ' . random_int(10, 17) . ':00');

                if ((int) date('w', $start) === 0) {
                    $start = strtotime('+1 day', $start);
                }

                $this->insertBooking(
                    $customerIds[array_rand($customerIds)],
                    $artistId,
                    $service,
                    $start,
                    random_int(1, 100) <= 70 ? 'confirmed' : 'pending'
                );
                $bookingCount++;
            }
        }

        // A few favourites so the customer dashboard is not empty.
        foreach ($customerIds as $customerId) {
            $picks = (array) array_rand(array_flip($artistIds), min(3, count($artistIds)));
            foreach ($picks as $artistId) {
                Database::affectingStatement(
                    'INSERT IGNORE INTO favorites (customer_id, artist_id) VALUES (:c, :a)',
                    ['c' => $customerId, 'a' => (int) $artistId]
                );
            }
        }

        $this->out("Created {$bookingCount} bookings and {$reviewCount} reviews.");
    }

    private function insertBooking(int $customerId, int $artistId, array $service, int $start, string $status): int
    {
        $duration = (int) $service['duration_min'];
        $buffer   = (int) $service['buffer_min'];
        $end      = $start + (($duration + $buffer) * 60);

        return Database::insert(
            'INSERT INTO bookings
                (reference, customer_id, artist_id, service_id, service_name,
                 starts_at, ends_at, duration_min, price, status,
                 confirmed_at, completed_at, created_at)
             VALUES
                (:ref, :c, :a, :s, :name, :start, :end, :dur, :price, :status,
                 :confirmed, :completed, :created)',
            [
                'ref'       => $this->reference(),
                'c'         => $customerId,
                'a'         => $artistId,
                's'         => $service['id'],
                'name'      => $service['name'],
                'start'     => date('Y-m-d H:i:s', $start),
                'end'       => date('Y-m-d H:i:s', $end),
                'dur'       => $duration,
                'price'     => $service['price'],
                'status'    => $status,
                'confirmed' => in_array($status, ['confirmed', 'completed'], true) ? date('Y-m-d H:i:s', $start - 86400) : null,
                'completed' => $status === 'completed' ? date('Y-m-d H:i:s', $end) : null,
                'created'   => date('Y-m-d H:i:s', $start - random_int(2, 14) * 86400),
            ]
        );
    }

    private function reference(): string
    {
        do {
            $reference = 'PP-' . strtoupper(bin2hex(random_bytes(4)));
            $exists = Database::scalar('SELECT 1 FROM bookings WHERE reference = :r', ['r' => $reference]);
        } while ($exists !== null);

        return $reference;
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        $base = $base === '' ? 'artist' : $base;
        $slug = $base;
        $i    = 1;

        while (Database::scalar('SELECT 1 FROM artist_profiles WHERE slug = :s', ['s' => $slug]) !== null) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    private function out(string $message): void
    {
        if (PHP_SAPI === 'cli' && function_exists('cli_ok')) {
            cli_ok($message);
        }
    }
}
