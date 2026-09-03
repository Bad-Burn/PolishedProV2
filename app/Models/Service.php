<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Service
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT s.*, sc.name AS category_name, sc.slug AS category_slug
               FROM services s
               LEFT JOIN service_categories sc ON sc.id = s.category_id
              WHERE s.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /** Fetch a service and confirm it belongs to the given artist in one query. */
    public static function findForArtist(int $id, int $artistId): ?array
    {
        return Database::selectOne(
            'SELECT * FROM services WHERE id = :id AND artist_id = :artist LIMIT 1',
            ['id' => $id, 'artist' => $artistId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forArtist(int $artistId, bool $activeOnly = false): array
    {
        $sql = 'SELECT s.*, sc.name AS category_name, sc.slug AS category_slug
                  FROM services s
                  LEFT JOIN service_categories sc ON sc.id = s.category_id
                 WHERE s.artist_id = :artist';

        if ($activeOnly) {
            $sql .= ' AND s.is_active = 1';
        }

        return Database::select($sql . ' ORDER BY s.sort_order ASC, s.id ASC', ['artist' => $artistId]);
    }

    public static function countActive(int $artistId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM services WHERE artist_id = :a AND is_active = 1',
            ['a' => $artistId]
        );
    }

    /** @param array<string, mixed> $data */
    public static function create(int $artistId, array $data): int
    {
        return Database::insert(
            'INSERT INTO services
                (artist_id, category_id, name, description, duration_min, buffer_min, price, is_active, sort_order)
             VALUES (:a, :c, :n, :d, :dur, :buf, :price, :active, :sort)',
            [
                'a'      => $artistId,
                'c'      => $data['category_id'] ?: null,
                'n'      => $data['name'],
                'd'      => $data['description'] ?? null,
                'dur'    => (int) $data['duration_min'],
                'buf'    => (int) ($data['buffer_min'] ?? 0),
                'price'  => (float) $data['price'],
                'active' => !empty($data['is_active']) ? 1 : 0,
                'sort'   => (int) ($data['sort_order'] ?? 0),
            ]
        );
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, int $artistId, array $data): void
    {
        Database::affectingStatement(
            'UPDATE services
                SET category_id = :c, name = :n, description = :d,
                    duration_min = :dur, buffer_min = :buf, price = :price,
                    is_active = :active, sort_order = :sort
              WHERE id = :id AND artist_id = :artist',
            [
                'c'      => $data['category_id'] ?: null,
                'n'      => $data['name'],
                'd'      => $data['description'] ?? null,
                'dur'    => (int) $data['duration_min'],
                'buf'    => (int) ($data['buffer_min'] ?? 0),
                'price'  => (float) $data['price'],
                'active' => !empty($data['is_active']) ? 1 : 0,
                'sort'   => (int) ($data['sort_order'] ?? 0),
                'id'     => $id,
                'artist' => $artistId,
            ]
        );
    }

    public static function setActive(int $id, int $artistId, bool $active): void
    {
        Database::affectingStatement(
            'UPDATE services SET is_active = :active WHERE id = :id AND artist_id = :artist',
            ['active' => $active ? 1 : 0, 'id' => $id, 'artist' => $artistId]
        );
    }

    public static function delete(int $id, int $artistId): bool
    {
        return Database::affectingStatement(
            'DELETE FROM services WHERE id = :id AND artist_id = :artist',
            ['id' => $id, 'artist' => $artistId]
        ) > 0;
    }

    public static function incrementBookings(int $id): void
    {
        Database::affectingStatement(
            'UPDATE services SET bookings_count = bookings_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function categories(): array
    {
        return Database::select(
            'SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
    }

    /** Category list with a live count of artists offering each one. */
    public static function categoriesWithCounts(): array
    {
        return Database::select(
            'SELECT sc.*, COUNT(DISTINCT s.artist_id) AS artist_count
               FROM service_categories sc
               LEFT JOIN services s ON s.category_id = sc.id AND s.is_active = 1
               LEFT JOIN users u    ON u.id = s.artist_id
                                    AND u.status = "active" AND u.approved_at IS NOT NULL
              WHERE sc.is_active = 1
              GROUP BY sc.id
              ORDER BY sc.sort_order ASC, sc.name ASC'
        );
    }

    /** Popular services across the platform, used on the homepage. */
    public static function trending(int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));

        return Database::select(
            'SELECT s.id, s.name, s.price, s.duration_min, s.bookings_count,
                    ap.display_name, ap.slug, ap.city, ap.rating_avg,
                    sc.name AS category_name
               FROM services s
               INNER JOIN users u           ON u.id = s.artist_id
               INNER JOIN artist_profiles ap ON ap.user_id = s.artist_id
               LEFT JOIN service_categories sc ON sc.id = s.category_id
              WHERE s.is_active = 1 AND u.status = "active" AND u.approved_at IS NOT NULL
              ORDER BY s.bookings_count DESC, ap.rating_avg DESC
              LIMIT ' . $limit
        );
    }
}
