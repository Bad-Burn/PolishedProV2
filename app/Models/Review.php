<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Review
{
    /** @return array<int, array<string, mixed>> */
    public static function forArtist(int $artistId, int $limit = 20, int $offset = 0): array
    {
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return Database::select(
            'SELECT r.*, u.full_name AS customer_name, u.avatar_path AS customer_avatar,
                    b.service_name, b.starts_at
               FROM reviews r
               INNER JOIN users u    ON u.id = r.customer_id
               INNER JOIN bookings b ON b.id = r.booking_id
              WHERE r.artist_id = :a AND r.is_visible = 1
              ORDER BY r.created_at DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['a' => $artistId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function byCustomer(int $customerId): array
    {
        return Database::select(
            'SELECT r.*, ap.display_name, ap.slug, b.service_name, b.starts_at
               FROM reviews r
               INNER JOIN bookings b ON b.id = r.booking_id
               LEFT JOIN artist_profiles ap ON ap.user_id = r.artist_id
              WHERE r.customer_id = :c
              ORDER BY r.created_at DESC',
            ['c' => $customerId]
        );
    }

    /**
     * Reviews one client left for one artist, hidden ones included — the
     * artist can see what was written about them either way.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function byCustomerForArtist(int $customerId, int $artistId): array
    {
        return Database::select(
            'SELECT r.*, b.service_name, b.starts_at, b.reference
               FROM reviews r
               INNER JOIN bookings b ON b.id = r.booking_id
              WHERE r.customer_id = :c AND r.artist_id = :a
              ORDER BY r.created_at DESC',
            ['c' => $customerId, 'a' => $artistId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function forBooking(int $bookingId): ?array
    {
        return Database::selectOne('SELECT * FROM reviews WHERE booking_id = :b LIMIT 1', ['b' => $bookingId]);
    }

    /**
     * Create a review. The UNIQUE index on booking_id is the real guarantee
     * that one appointment yields at most one review, even under a double
     * submit — this method just reports it cleanly.
     */
    public static function create(array $data): ?int
    {
        if (self::forBooking((int) $data['booking_id']) !== null) {
            return null;
        }

        $id = Database::insert(
            'INSERT INTO reviews (booking_id, customer_id, artist_id, service_id, rating, comment)
             VALUES (:b, :c, :a, :s, :r, :comment)',
            [
                'b'       => (int) $data['booking_id'],
                'c'       => (int) $data['customer_id'],
                'a'       => (int) $data['artist_id'],
                's'       => $data['service_id'] ?: null,
                'r'       => max(1, min(5, (int) $data['rating'])),
                'comment' => $data['comment'] ?: null,
            ]
        );

        Artist::refreshRating((int) $data['artist_id']);

        return $id;
    }

    public static function reply(int $reviewId, int $artistId, string $reply): bool
    {
        return Database::affectingStatement(
            'UPDATE reviews SET artist_reply = :reply, replied_at = NOW()
              WHERE id = :id AND artist_id = :a',
            ['reply' => $reply, 'id' => $reviewId, 'a' => $artistId]
        ) > 0;
    }

    public static function setVisibility(int $reviewId, bool $visible): void
    {
        $review = Database::selectOne('SELECT artist_id FROM reviews WHERE id = :id', ['id' => $reviewId]);

        Database::affectingStatement(
            'UPDATE reviews SET is_visible = :v WHERE id = :id',
            ['v' => $visible ? 1 : 0, 'id' => $reviewId]
        );

        if ($review !== null) {
            Artist::refreshRating((int) $review['artist_id']);
        }
    }

    /**
     * Star distribution for the profile page: [5 => 40, 4 => 12, …]
     *
     * @return array<int, int>
     */
    public static function distribution(int $artistId): array
    {
        $counts = array_fill_keys([5, 4, 3, 2, 1], 0);

        $rows = Database::select(
            'SELECT rating, COUNT(*) AS total
               FROM reviews WHERE artist_id = :a AND is_visible = 1
              GROUP BY rating',
            ['a' => $artistId]
        );

        foreach ($rows as $row) {
            $counts[(int) $row['rating']] = (int) $row['total'];
        }

        return $counts;
    }

    public static function countForArtist(int $artistId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM reviews WHERE artist_id = :a AND is_visible = 1',
            ['a' => $artistId]
        );
    }

    /** Recent reviews across the platform, for the admin moderation screen. */
    public static function recent(int $limit = 30): array
    {
        return Database::select(
            'SELECT r.*, cu.full_name AS customer_name, ap.display_name AS artist_name, ap.slug
               FROM reviews r
               INNER JOIN users cu ON cu.id = r.customer_id
               LEFT JOIN artist_profiles ap ON ap.user_id = r.artist_id
              ORDER BY r.created_at DESC
              LIMIT ' . max(1, min(100, $limit))
        );
    }
}
