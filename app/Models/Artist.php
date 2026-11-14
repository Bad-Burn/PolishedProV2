<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Artist profiles and the search/discovery queries.
 *
 * Search ordering is where the paid plans actually pay off: an artist's
 * `featured_weight` comes from their active subscription and is the primary
 * sort key, with rating and review volume breaking ties underneath it.
 */
final class Artist
{
    /**
     * Shared SELECT + JOIN used by every listing query.
     *
     * `plan_weight` resolves to the featured weight of the artist's active
     * subscription, or 0 when they are on the free tier or lapsed.
     */
    private const BASE_SELECT = '
        SELECT u.id, u.full_name, u.email, u.avatar_path, u.status, u.approved_at, u.created_at,
               ap.display_name, ap.slug, ap.headline, ap.bio, ap.city, ap.area, ap.studio_address,
               ap.years_experience, ap.instagram, ap.website, ap.cover_path,
               ap.is_verified, ap.instant_book, ap.accepts_home_service, ap.featured_until,
               ap.rating_avg, ap.rating_count, ap.completed_bookings, ap.cancellation_policy,
               COALESCE(p.featured_weight, 0) AS plan_weight,
               p.badge AS plan_badge,
               p.code  AS plan_code,
               (ap.featured_until IS NOT NULL AND ap.featured_until > NOW()) AS is_boosted,
               (SELECT MIN(s.price) FROM services s WHERE s.artist_id = u.id AND s.is_active = 1) AS price_from,
               (SELECT COUNT(*)     FROM services s WHERE s.artist_id = u.id AND s.is_active = 1) AS service_count,
               (SELECT COUNT(*) FROM portfolio_items pi WHERE pi.artist_id = u.id) AS photo_count,
               -- First portfolio photo, so a card without a cover still shows real work.
               (SELECT pi2.image_path FROM portfolio_items pi2
                 WHERE pi2.artist_id = u.id
                 ORDER BY pi2.sort_order ASC, pi2.id DESC LIMIT 1) AS first_photo
          FROM users u
          INNER JOIN artist_profiles ap ON ap.user_id = u.id
          LEFT JOIN subscriptions sub
                 ON sub.user_id = u.id
                AND sub.status = "active"
                AND (sub.current_period_end > NOW()
                     OR (sub.provider = "paypal" AND sub.cancel_at_period_end = 0
                         AND sub.current_period_end > NOW() - INTERVAL ' . Subscription::RENEWAL_GRACE_DAYS . ' DAY))
          LEFT JOIN plans p ON p.id = sub.plan_id
    ';

    /**
     * Only approved, active, *paying* artists are ever shown to the public.
     *
     * The subscription test is a self-contained EXISTS rather than a join
     * condition, because this fragment is also used by the COUNT query in
     * search(), which does not join the subscription tables at all. Referencing
     * only `u` keeps it valid everywhere it is pasted.
     *
     * "Paying" means one of two things: inside the trial window, or holding a
     * subscription to a plan that actually costs money. The price test matters
     * — the retired ₱0 Starter plan still has rows pointing at it.
     */
    private const HAS_ACCESS = '
        (
            u.trial_ends_at > NOW()
            OR EXISTS (
                SELECT 1 FROM subscriptions sx
                 INNER JOIN plans px ON px.id = sx.plan_id
                 WHERE sx.user_id = u.id
                   AND sx.status IN ("active", "past_due")
                   AND px.price_monthly > 0
                   AND (sx.current_period_end > NOW()
                        OR (sx.provider = "paypal" AND sx.cancel_at_period_end = 0
                            AND sx.current_period_end > NOW() - INTERVAL ' . Subscription::RENEWAL_GRACE_DAYS . ' DAY))
            )
        )
    ';

    private const PUBLIC_WHERE = ' u.role = "artist" AND u.status = "active" AND u.approved_at IS NOT NULL AND '
        . self::HAS_ACCESS . ' ';

    /**
     * Every publicly visible profile, for the sitemap.
     *
     * @return array<int, array{slug:string, updated_at:string}>
     */
    public static function publicSlugs(): array
    {
        return Database::select(
            'SELECT ap.slug, ap.updated_at
               FROM users u
               INNER JOIN artist_profiles ap ON ap.user_id = u.id
              WHERE ' . self::PUBLIC_WHERE . '
              ORDER BY ap.updated_at DESC
              LIMIT 50000'
        );
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return Database::selectOne(
            self::BASE_SELECT . ' WHERE ap.slug = :slug AND ' . self::PUBLIC_WHERE . ' LIMIT 1',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByUserId(int $userId, bool $publicOnly = false): ?array
    {
        $sql = self::BASE_SELECT . ' WHERE u.id = :id';
        if ($publicOnly) {
            $sql .= ' AND ' . self::PUBLIC_WHERE;
        }

        return Database::selectOne($sql . ' LIMIT 1', ['id' => $userId]);
    }

    /**
     * Discovery search.
     *
     * @param array{q?:string, category?:string, city?:string, min_rating?:float,
     *              max_price?:float, verified?:bool, instant?:bool, sort?:string,
     *              page?:int, per_page?:int} $filters
     * @return array{rows: array<int, array<string, mixed>>, total:int, pages:int, page:int}
     */
    public static function search(array $filters = []): array
    {
        $where    = [self::PUBLIC_WHERE];
        $bindings = [];

        if (!empty($filters['q'])) {
            // Escaped so a customer typing "50%" does not turn into a wildcard.
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';

            // Each position gets its own placeholder: PDO with emulated
            // prepares off refuses to bind one name to several positions.
            $where[] = '(ap.display_name LIKE :q1 OR ap.headline LIKE :q2 OR ap.city LIKE :q3 OR ap.area LIKE :q4
                         OR EXISTS (SELECT 1 FROM services s2
                                     WHERE s2.artist_id = u.id AND s2.is_active = 1 AND s2.name LIKE :q5))';

            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $name) {
                $bindings[$name] = $term;
            }
        }

        if (!empty($filters['category'])) {
            $where[] = 'EXISTS (SELECT 1 FROM services s3
                                 INNER JOIN service_categories sc ON sc.id = s3.category_id
                                 WHERE s3.artist_id = u.id AND s3.is_active = 1 AND sc.slug = :category)';
            $bindings['category'] = (string) $filters['category'];
        }

        if (!empty($filters['city'])) {
            $where[] = 'ap.city = :city';
            $bindings['city'] = (string) $filters['city'];
        }

        if (!empty($filters['min_rating'])) {
            $where[] = 'ap.rating_avg >= :min_rating';
            $bindings['min_rating'] = (float) $filters['min_rating'];
        }

        if (!empty($filters['max_price'])) {
            $where[] = 'EXISTS (SELECT 1 FROM services s4
                                 WHERE s4.artist_id = u.id AND s4.is_active = 1 AND s4.price <= :max_price)';
            $bindings['max_price'] = (float) $filters['max_price'];
        }

        if (!empty($filters['verified'])) {
            $where[] = 'ap.is_verified = 1';
        }

        if (!empty($filters['instant'])) {
            $where[] = 'ap.instant_book = 1';
        }

        $clause = implode(' AND ', $where);

        // Whitelist: the sort key is never interpolated from raw input.
        $order = match ((string) ($filters['sort'] ?? 'recommended')) {
            'rating'     => 'ap.rating_avg DESC, ap.rating_count DESC',
            'reviews'    => 'ap.rating_count DESC, ap.rating_avg DESC',
            'price_low'  => 'price_from IS NULL, price_from ASC',
            'price_high' => 'price_from DESC',
            'newest'     => 'u.created_at DESC',
            default      => 'is_boosted DESC, plan_weight DESC, ap.rating_avg DESC, ap.rating_count DESC',
        };

        $perPage = max(6, min(48, (int) ($filters['per_page'] ?? 12)));
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::scalar(
            'SELECT COUNT(*)
               FROM users u
               INNER JOIN artist_profiles ap ON ap.user_id = u.id
              WHERE ' . $clause,
            $bindings
        );

        $rows = Database::select(
            self::BASE_SELECT
            . ' WHERE ' . $clause
            . " ORDER BY {$order}, u.id DESC"
            . " LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'pages' => (int) max(1, ceil($total / $perPage)),
            'page'  => $page,
        ];
    }

    /**
     * Homepage rotation: paid artists first, then the best-rated free ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function featured(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));

        return Database::select(
            self::BASE_SELECT
            . ' WHERE ' . self::PUBLIC_WHERE
            . ' AND ap.rating_count > 0
                ORDER BY COALESCE(p.featured_weight, 0) DESC, ap.rating_avg DESC, ap.rating_count DESC
                LIMIT ' . $limit
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function cities(): array
    {
        return Database::select(
            'SELECT ap.city, COUNT(*) AS total
               FROM artist_profiles ap
               INNER JOIN users u ON u.id = ap.user_id
              WHERE ' . self::PUBLIC_WHERE . ' AND ap.city IS NOT NULL AND ap.city <> ""
              GROUP BY ap.city
              ORDER BY total DESC, ap.city ASC'
        );
    }

    // -- Profile writes -----------------------------------------------------

    public static function createProfile(int $userId, string $displayName): void
    {
        Database::insert(
            'INSERT INTO artist_profiles (user_id, display_name, slug) VALUES (:id, :name, :slug)',
            ['id' => $userId, 'name' => $displayName, 'slug' => self::uniqueSlug($displayName)]
        );
    }

    /** @param array<string, mixed> $fields */
    public static function updateProfile(int $userId, array $fields): void
    {
        $allowed = [
            'display_name', 'headline', 'bio', 'city', 'area', 'studio_address',
            'years_experience', 'instagram', 'website', 'cover_path',
            'instant_book', 'accepts_home_service', 'cancellation_policy',
            'is_verified', 'featured_until', 'slug',
        ];

        $set  = [];
        $bind = ['id' => $userId];

        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $bind[$column] = $value;
        }

        if ($set === []) {
            return;
        }

        Database::affectingStatement(
            'UPDATE artist_profiles SET ' . implode(', ', $set) . ' WHERE user_id = :id',
            $bind
        );
    }

    public static function uniqueSlug(string $name, ?int $ignoreUserId = null): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $base = $base === '' ? 'artist' : substr($base, 0, 120);
        $slug = $base;
        $i    = 1;

        while (true) {
            $sql  = 'SELECT user_id FROM artist_profiles WHERE slug = :slug';
            $bind = ['slug' => $slug];

            if ($ignoreUserId !== null) {
                $sql .= ' AND user_id <> :ignore';
                $bind['ignore'] = $ignoreUserId;
            }

            if (Database::scalar($sql . ' LIMIT 1', $bind) === null) {
                return $slug;
            }

            $slug = $base . '-' . (++$i);
        }
    }

    /**
     * Recompute the cached rating from the visible reviews.
     * Called whenever a review is created, edited, hidden or deleted.
     */
    public static function refreshRating(int $artistId): void
    {
        Database::affectingStatement(
            'UPDATE artist_profiles ap
                SET ap.rating_avg = COALESCE((
                        SELECT ROUND(AVG(r.rating), 2) FROM reviews r
                         WHERE r.artist_id = :id1 AND r.is_visible = 1
                    ), 0),
                    ap.rating_count = (
                        SELECT COUNT(*) FROM reviews r
                         WHERE r.artist_id = :id2 AND r.is_visible = 1
                    )
              WHERE ap.user_id = :id3',
            ['id1' => $artistId, 'id2' => $artistId, 'id3' => $artistId]
        );
    }

    public static function refreshCompletedCount(int $artistId): void
    {
        Database::affectingStatement(
            'UPDATE artist_profiles
                SET completed_bookings = (
                    SELECT COUNT(*) FROM bookings
                     WHERE artist_id = :id1 AND status = "completed"
                )
              WHERE user_id = :id2',
            ['id1' => $artistId, 'id2' => $artistId]
        );
    }

    public static function isBoosted(array $artist): bool
    {
        $until = $artist['featured_until'] ?? null;
        return is_string($until) && $until !== '' && strtotime($until) > time();
    }
}
