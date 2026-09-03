<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Favorite
{
    public static function exists(int $customerId, int $artistId): bool
    {
        return Database::scalar(
            'SELECT 1 FROM favorites WHERE customer_id = :c AND artist_id = :a LIMIT 1',
            ['c' => $customerId, 'a' => $artistId]
        ) !== null;
    }

    /** @return bool the new state: true when saved, false when removed */
    public static function toggle(int $customerId, int $artistId): bool
    {
        if (self::exists($customerId, $artistId)) {
            Database::affectingStatement(
                'DELETE FROM favorites WHERE customer_id = :c AND artist_id = :a',
                ['c' => $customerId, 'a' => $artistId]
            );
            return false;
        }

        Database::affectingStatement(
            'INSERT IGNORE INTO favorites (customer_id, artist_id) VALUES (:c, :a)',
            ['c' => $customerId, 'a' => $artistId]
        );

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forCustomer(int $customerId): array
    {
        return Database::select(
            'SELECT u.id, u.full_name, u.avatar_path,
                    ap.display_name, ap.slug, ap.headline, ap.city, ap.area,
                    ap.rating_avg, ap.rating_count, ap.is_verified,
                    f.created_at AS saved_at,
                    (SELECT MIN(s.price) FROM services s
                      WHERE s.artist_id = u.id AND s.is_active = 1) AS price_from
               FROM favorites f
               INNER JOIN users u            ON u.id = f.artist_id
               INNER JOIN artist_profiles ap ON ap.user_id = f.artist_id
              WHERE f.customer_id = :c AND u.status = "active"
              ORDER BY f.created_at DESC',
            ['c' => $customerId]
        );
    }

    public static function countFor(int $customerId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM favorites WHERE customer_id = :c',
            ['c' => $customerId]
        );
    }

    /**
     * Ids only — used to light up the heart icons on a page of search results
     * without one query per card.
     *
     * @return array<int, int>
     */
    public static function artistIdsFor(int $customerId): array
    {
        return array_map(
            'intval',
            array_column(
                Database::select('SELECT artist_id FROM favorites WHERE customer_id = :c', ['c' => $customerId]),
                'artist_id'
            )
        );
    }
}
