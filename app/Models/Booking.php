<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Booking
{
    /** Statuses that still occupy a slot in the artist's calendar. */
    public const BLOCKING_STATUSES = ['pending', 'confirmed'];

    private const DETAIL_SELECT = '
        SELECT b.*,
               cu.full_name  AS customer_name,
               cu.email      AS customer_email,
               cu.phone      AS customer_phone,
               cu.avatar_path AS customer_avatar,
               au.full_name  AS artist_name,
               au.email      AS artist_email,
               au.phone      AS artist_phone,
               au.avatar_path AS artist_avatar,
               ap.display_name, ap.slug AS artist_slug, ap.city, ap.area,
               ap.studio_address, ap.cancellation_policy,
               s.duration_min AS service_duration,
               r.id AS review_id, r.rating AS review_rating
          FROM bookings b
          INNER JOIN users cu ON cu.id = b.customer_id
          INNER JOIN users au ON au.id = b.artist_id
          LEFT JOIN artist_profiles ap ON ap.user_id = b.artist_id
          LEFT JOIN services s ON s.id = b.service_id
          LEFT JOIN reviews  r ON r.booking_id = b.id
    ';

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne(self::DETAIL_SELECT . ' WHERE b.id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByReference(string $reference): ?array
    {
        return Database::selectOne(
            self::DETAIL_SELECT . ' WHERE b.reference = :r LIMIT 1',
            ['r' => strtoupper($reference)]
        );
    }

    /**
     * Does this window collide with an existing booking?
     *
     * Two ranges overlap when each starts before the other ends. Cancelled,
     * declined and completed bookings never block.
     */
    public static function hasConflict(int $artistId, string $startsAt, string $endsAt, ?int $ignoreBookingId = null): bool
    {
        $sql = 'SELECT 1 FROM bookings
                 WHERE artist_id = :artist
                   AND status IN ("pending", "confirmed")
                   AND starts_at < :ends
                   AND ends_at   > :starts';

        $bindings = ['artist' => $artistId, 'ends' => $endsAt, 'starts' => $startsAt];

        if ($ignoreBookingId !== null) {
            $sql .= ' AND id <> :ignore';
            $bindings['ignore'] = $ignoreBookingId;
        }

        return Database::scalar($sql . ' LIMIT 1', $bindings) !== null;
    }

    /**
     * All blocking bookings for one artist on one date — feeds the slot picker.
     *
     * @return array<int, array{starts_at:string, ends_at:string}>
     */
    public static function occupiedOn(int $artistId, string $date): array
    {
        return Database::select(
            'SELECT starts_at, ends_at
               FROM bookings
              WHERE artist_id = :artist
                AND status IN ("pending", "confirmed")
                AND DATE(starts_at) = :date
              ORDER BY starts_at ASC',
            ['artist' => $artistId, 'date' => $date]
        );
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO bookings
                (reference, customer_id, artist_id, service_id, service_name,
                 starts_at, ends_at, duration_min, price, payment_method, status, notes, confirmed_at)
             VALUES
                (:ref, :customer, :artist, :service, :name,
                 :starts, :ends, :duration, :price, :method, :status, :notes, :confirmed)',
            [
                'ref'       => $data['reference'],
                'customer'  => (int) $data['customer_id'],
                'artist'    => (int) $data['artist_id'],
                'service'   => (int) $data['service_id'],
                'name'      => $data['service_name'],
                'starts'    => $data['starts_at'],
                'ends'      => $data['ends_at'],
                'duration'  => (int) $data['duration_min'],
                'price'     => (float) $data['price'],
                'method'    => $data['payment_method'] ?? 'cash',
                'status'    => $data['status'] ?? 'pending',
                'notes'     => $data['notes'] ?? null,
                'confirmed' => ($data['status'] ?? 'pending') === 'confirmed' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }

    /** Collision-proof booking reference. */
    public static function generateReference(): string
    {
        do {
            $reference = 'PP-' . strtoupper(bin2hex(random_bytes(4)));
        } while (Database::scalar('SELECT 1 FROM bookings WHERE reference = :r', ['r' => $reference]) !== null);

        return $reference;
    }

    public static function updateStatus(int $id, string $status, ?int $actorId = null, ?string $reason = null): bool
    {
        $valid = ['pending', 'confirmed', 'completed', 'cancelled', 'declined', 'no_show'];
        if (!in_array($status, $valid, true)) {
            return false;
        }

        $columns = ['status = :status'];
        $bind    = ['status' => $status, 'id' => $id];

        match ($status) {
            'confirmed' => $columns[] = 'confirmed_at = NOW()',
            'completed' => $columns[] = 'completed_at = NOW()',
            'cancelled', 'declined' => $columns[] = 'cancelled_at = NOW()',
            default => null,
        };

        if (in_array($status, ['cancelled', 'declined'], true)) {
            $columns[]   = 'cancellation_reason = :reason';
            $columns[]   = 'cancelled_by = :actor';
            $bind['reason'] = $reason;
            $bind['actor']  = $actorId;
        }

        $changed = Database::affectingStatement(
            'UPDATE bookings SET ' . implode(', ', $columns) . ' WHERE id = :id',
            $bind
        ) > 0;

        if ($changed) {
            self::logEvent($id, $status, $actorId, $reason);
        }

        return $changed;
    }

    public static function logEvent(int $bookingId, string $event, ?int $actorId = null, ?string $note = null): void
    {
        Database::insert(
            'INSERT INTO booking_events (booking_id, actor_id, event, note) VALUES (:b, :a, :e, :n)',
            ['b' => $bookingId, 'a' => $actorId, 'e' => $event, 'n' => $note]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function timeline(int $bookingId): array
    {
        return Database::select(
            'SELECT be.*, u.full_name AS actor_name, u.role AS actor_role
               FROM booking_events be
               LEFT JOIN users u ON u.id = be.actor_id
              WHERE be.booking_id = :b
              ORDER BY be.created_at ASC, be.id ASC',
            ['b' => $bookingId]
        );
    }

    /**
     * Bookings for one person, filtered by a coarse tab.
     *
     * @param 'customer'|'artist' $side
     * @param 'upcoming'|'past'|'pending'|'cancelled'|'all' $scope
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId, string $side, string $scope = 'upcoming', int $limit = 50): array
    {
        $column = $side === 'artist' ? 'b.artist_id' : 'b.customer_id';
        $limit  = max(1, min(200, $limit));

        [$condition, $order] = match ($scope) {
            'upcoming'  => ['b.status IN ("pending","confirmed") AND b.ends_at >= NOW()', 'b.starts_at ASC'],
            'pending'   => ['b.status = "pending"', 'b.starts_at ASC'],
            'past'      => ['(b.status = "completed" OR (b.status IN ("pending","confirmed") AND b.ends_at < NOW()))', 'b.starts_at DESC'],
            'cancelled' => ['b.status IN ("cancelled","declined","no_show")', 'b.starts_at DESC'],
            default     => ['1 = 1', 'b.starts_at DESC'],
        };

        return Database::select(
            self::DETAIL_SELECT . " WHERE {$column} = :id AND {$condition} ORDER BY {$order} LIMIT {$limit}",
            ['id' => $userId]
        );
    }

    /** Today's schedule for the artist dashboard. */
    public static function todayFor(int $artistId): array
    {
        return Database::select(
            self::DETAIL_SELECT . ' WHERE b.artist_id = :a
                                     AND DATE(b.starts_at) = CURDATE()
                                     AND b.status IN ("pending","confirmed")
                                   ORDER BY b.starts_at ASC',
            ['a' => $artistId]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: array<int, array<string, mixed>>, total:int, pages:int, page:int}
     */
    public static function paginate(array $filters = []): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['status'])) {
            $statuses = array_values(array_intersect(
                (array) $filters['status'],
                ['pending', 'confirmed', 'completed', 'cancelled', 'declined', 'no_show']
            ));

            if ($statuses !== []) {
                // Placeholders are generated, values are still bound.
                $names = [];
                foreach ($statuses as $i => $status) {
                    $names[] = ":status{$i}";
                    $bindings["status{$i}"] = $status;
                }
                $where[] = 'b.status IN (' . implode(', ', $names) . ')';
            }
        }

        if (!empty($filters['artist_id'])) {
            $where[] = 'b.artist_id = :artist';
            $bindings['artist'] = (int) $filters['artist_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $where[] = '(b.reference LIKE :search1 OR cu.full_name LIKE :search2
                         OR au.full_name LIKE :search3 OR b.service_name LIKE :search4)';

            foreach (['search1', 'search2', 'search3', 'search4'] as $name) {
                $bindings[$name] = $term;
            }
        }

        if (!empty($filters['from'])) {
            $where[] = 'b.starts_at >= :from';
            $bindings['from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $where[] = 'b.starts_at <= :to';
            $bindings['to'] = $filters['to'] . ' 23:59:59';
        }

        $clause  = implode(' AND ', $where);
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::scalar(
            "SELECT COUNT(*)
               FROM bookings b
               INNER JOIN users cu ON cu.id = b.customer_id
               INNER JOIN users au ON au.id = b.artist_id
              WHERE {$clause}",
            $bindings
        );

        $rows = Database::select(
            self::DETAIL_SELECT . " WHERE {$clause} ORDER BY b.starts_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'pages' => (int) max(1, ceil($total / $perPage)),
            'page'  => $page,
        ];
    }

    // -- Aggregates ---------------------------------------------------------

    /** @return array<string, mixed> */
    public static function artistStats(int $artistId): array
    {
        $row = Database::selectOne(
            'SELECT
                COUNT(*)                                                        AS total,
                SUM(status = "pending")                                         AS pending,
                SUM(status = "confirmed" AND ends_at >= NOW())                   AS upcoming,
                SUM(status = "completed")                                        AS completed,
                SUM(status IN ("cancelled","declined","no_show"))                AS cancelled,
                COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0)  AS revenue_total,
                COALESCE(SUM(CASE WHEN status = "completed"
                                   AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  THEN price END), 0)                           AS revenue_30d,
                COUNT(DISTINCT customer_id)                                      AS clients
               FROM bookings
              WHERE artist_id = :a',
            ['a' => $artistId]
        );

        return $row ?? [];
    }

    /** @return array<string, mixed> */
    public static function customerStats(int $customerId): array
    {
        $row = Database::selectOne(
            'SELECT
                COUNT(*)                                                       AS total,
                SUM(status IN ("pending","confirmed") AND ends_at >= NOW())     AS upcoming,
                SUM(status = "completed")                                       AS completed,
                COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0) AS spent
               FROM bookings
              WHERE customer_id = :c',
            ['c' => $customerId]
        );

        return $row ?? [];
    }

    /**
     * Revenue and volume per month for the artist analytics chart.
     *
     * @return array<int, array{label:string, revenue:float, bookings:int}>
     */
    public static function monthlyTrend(int $artistId, int $months = 6): array
    {
        $months = max(3, min(24, $months));

        $rows = Database::select(
            'SELECT DATE_FORMAT(starts_at, "%Y-%m") AS period,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0) AS revenue
               FROM bookings
              WHERE artist_id = :a
                AND starts_at >= DATE_SUB(CURDATE(), INTERVAL ' . $months . ' MONTH)
              GROUP BY period
              ORDER BY period ASC',
            ['a' => $artistId]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['period']] = $row;
        }

        // Fill the gaps so the chart has an unbroken axis.
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $series[] = [
                'label'    => date('M', strtotime($key . '-01')),
                'revenue'  => (float) ($indexed[$key]['revenue'] ?? 0),
                'bookings' => (int) ($indexed[$key]['bookings'] ?? 0),
            ];
        }

        return $series;
    }

    /** Most-booked services for one artist. */
    public static function topServices(int $artistId, int $limit = 5): array
    {
        return Database::select(
            'SELECT service_name,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0) AS revenue
               FROM bookings
              WHERE artist_id = :a
              GROUP BY service_name
              ORDER BY bookings DESC
              LIMIT ' . max(1, min(20, $limit)),
            ['a' => $artistId]
        );
    }

    /** Repeat-client list shown on the artist's Clients tab. */
    public static function clientsFor(int $artistId, int $limit = 50): array
    {
        return Database::select(
            'SELECT u.id, u.full_name, u.email, u.phone, u.avatar_path,
                    COUNT(b.id) AS visits,
                    COALESCE(SUM(CASE WHEN b.status = "completed" THEN b.price END), 0) AS total_spent,
                    MAX(b.starts_at) AS last_visit
               FROM bookings b
               INNER JOIN users u ON u.id = b.customer_id
              WHERE b.artist_id = :a
              GROUP BY u.id
              ORDER BY visits DESC, last_visit DESC
              LIMIT ' . max(1, min(200, $limit)),
            ['a' => $artistId]
        );
    }

    /**
     * One client's relationship with one artist.
     *
     * Scoped to the artist on purpose: it returns null unless that person has
     * actually booked them, so the detail page cannot be used to look up
     * arbitrary users by id.
     *
     * @return array<string, mixed>|null
     */
    public static function clientSummaryFor(int $artistId, int $customerId): ?array
    {
        return Database::selectOne(
            'SELECT u.id, u.full_name, u.email, u.phone, u.avatar_path, u.created_at AS joined_at,
                    COUNT(b.id)                                                      AS visits,
                    SUM(b.status = "completed")                                       AS completed,
                    SUM(b.status = "pending")                                         AS pending,
                    SUM(b.status = "confirmed" AND b.ends_at >= NOW())                AS upcoming,
                    SUM(b.status IN ("cancelled","declined"))                         AS cancelled,
                    SUM(b.status = "no_show")                                         AS no_shows,
                    COALESCE(SUM(CASE WHEN b.status = "completed" THEN b.price END), 0) AS total_spent,
                    MIN(b.starts_at)                                                  AS first_visit,
                    MAX(b.starts_at)                                                  AS last_visit
               FROM bookings b
               INNER JOIN users u ON u.id = b.customer_id
              WHERE b.artist_id = :a AND b.customer_id = :c
              GROUP BY u.id',
            ['a' => $artistId, 'c' => $customerId]
        );
    }

    /**
     * Every appointment this client has had with this artist, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function historyForClient(int $artistId, int $customerId, int $limit = 100): array
    {
        return Database::select(
            self::DETAIL_SELECT . ' WHERE b.artist_id = :a AND b.customer_id = :c
                                    ORDER BY b.starts_at DESC
                                    LIMIT ' . max(1, min(200, $limit)),
            ['a' => $artistId, 'c' => $customerId]
        );
    }

    /**
     * The services this client books most often — the useful thing to know
     * before they walk in.
     *
     * @return array<int, array{service_name:string, times:int}>
     */
    public static function favouriteServicesForClient(int $artistId, int $customerId, int $limit = 5): array
    {
        return Database::select(
            'SELECT service_name, COUNT(*) AS times
               FROM bookings
              WHERE artist_id = :a AND customer_id = :c AND status = "completed"
              GROUP BY service_name
              ORDER BY times DESC, service_name ASC
              LIMIT ' . max(1, min(20, $limit)),
            ['a' => $artistId, 'c' => $customerId]
        );
    }

    /** Platform-wide counters for the admin dashboard. */
    public static function platformStats(): array
    {
        $row = Database::selectOne(
            'SELECT
                COUNT(*)                                                        AS total,
                SUM(status = "pending")                                          AS pending,
                SUM(status = "completed")                                         AS completed,
                SUM(status IN ("cancelled","declined","no_show"))                 AS cancelled,
                SUM(DATE(created_at) = CURDATE())                                 AS today,
                COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0)   AS gmv,
                COALESCE(SUM(CASE WHEN status = "completed"
                                   AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  THEN price END), 0)                            AS gmv_30d
               FROM bookings'
        );

        return $row ?? [];
    }

    /** @return array<int, array{label:string, revenue:float, bookings:int}> */
    public static function platformTrend(int $months = 6): array
    {
        $months = max(3, min(24, $months));

        $rows = Database::select(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN status = "completed" THEN price END), 0) AS revenue
               FROM bookings
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $months . ' MONTH)
              GROUP BY period ORDER BY period ASC'
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['period']] = $row;
        }

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $series[] = [
                'label'    => date('M', strtotime($key . '-01')),
                'revenue'  => (float) ($indexed[$key]['revenue'] ?? 0),
                'bookings' => (int) ($indexed[$key]['bookings'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Admin-facing counters.
     *
     * Deliberately free of every money column. What an artist earns from their
     * own appointments is their business, not the console's — the platform
     * takes no commission on it, so there is nothing here for an admin to
     * reconcile. Platform revenue lives on Subscription::mrr() instead.
     *
     * @return array<string, mixed>
     */
    public static function platformVolumeStats(): array
    {
        $row = Database::selectOne(
            'SELECT
                COUNT(*)                                                          AS total,
                SUM(status = "pending")                                            AS pending,
                SUM(status = "confirmed" AND ends_at >= NOW())                      AS upcoming,
                SUM(status = "completed")                                           AS completed,
                SUM(status IN ("cancelled","declined","no_show"))                   AS cancelled,
                SUM(DATE(created_at) = CURDATE())                                   AS today,
                SUM(status = "completed"
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))           AS completed_30d
               FROM bookings'
        );

        return $row ?? [];
    }

    /**
     * Appointment volume per month for the admin chart — counts only, for the
     * same reason as platformVolumeStats().
     *
     * @return array<int, array{label:string, bookings:int}>
     */
    public static function platformVolumeTrend(int $months = 6): array
    {
        $months = max(3, min(24, $months));

        $rows = Database::select(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period, COUNT(*) AS bookings
               FROM bookings
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $months . ' MONTH)
              GROUP BY period ORDER BY period ASC'
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['period']] = (int) $row['bookings'];
        }

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $series[] = [
                'label'    => date('M', strtotime($key . '-01')),
                'bookings' => $indexed[$key] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Sweep past-due appointments into `completed` so ratings and revenue are
     * not stuck waiting on an artist to tick a box. Runs opportunistically.
     */
    /** Appointments this customer is holding that have not happened yet. */
    public static function countUpcomingFor(int $customerId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM bookings
              WHERE customer_id = :c AND status IN ("pending", "confirmed") AND starts_at > NOW()',
            ['c' => $customerId]
        );
    }

    /**
     * Confirmed appointments in the next 24 hours that have not been reminded.
     * Bookings made in the last two hours are skipped — the confirmation they
     * just received is reminder enough.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function dueForReminder(): array
    {
        return Database::select(
            'SELECT b.id, b.service_name, b.starts_at,
                    c.id AS customer_id, c.email AS customer_email, c.full_name AS customer_name,
                    a.id AS artist_id,   a.email AS artist_email,   a.full_name AS artist_name,
                    ap.display_name
               FROM bookings b
               INNER JOIN users c ON c.id = b.customer_id
               INNER JOIN users a ON a.id = b.artist_id
               LEFT JOIN artist_profiles ap ON ap.user_id = b.artist_id
              WHERE b.status = "confirmed"
                AND b.reminder_sent_at IS NULL
                AND b.starts_at > NOW()
                AND b.starts_at <= NOW() + INTERVAL 24 HOUR
                AND b.created_at < NOW() - INTERVAL 2 HOUR
              ORDER BY b.starts_at ASC
              LIMIT 500'
        );
    }

    /** Claim a booking for reminding. False if another run already did. */
    public static function markReminded(int $bookingId): bool
    {
        return Database::affectingStatement(
            'UPDATE bookings SET reminder_sent_at = NOW() WHERE id = :id AND reminder_sent_at IS NULL',
            ['id' => $bookingId]
        ) === 1;
    }

    public static function autoCompleteOverdue(): int
    {
        return Database::affectingStatement(
            'UPDATE bookings
                SET status = "completed", completed_at = ends_at
              WHERE status = "confirmed"
                AND ends_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
    }
}
