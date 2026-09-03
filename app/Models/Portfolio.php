<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Portfolio
{
    /** @return array<int, array<string, mixed>> */
    public static function forArtist(int $artistId, ?int $limit = null): array
    {
        $sql = 'SELECT pi.*, s.name AS service_name
                  FROM portfolio_items pi
                  LEFT JOIN services s ON s.id = pi.service_id
                 WHERE pi.artist_id = :a
                 ORDER BY pi.sort_order ASC, pi.id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(60, $limit));
        }

        return Database::select($sql, ['a' => $artistId]);
    }

    /**
     * Portfolio for the public gallery page, with enough of the linked service
     * to offer a "book this" button next to the photo that sold it.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicForArtist(int $artistId): array
    {
        return Database::select(
            'SELECT pi.*, s.name AS service_name, s.price AS service_price, s.is_active AS service_active
               FROM portfolio_items pi
               LEFT JOIN services s ON s.id = pi.service_id
              WHERE pi.artist_id = :a
              ORDER BY pi.sort_order ASC, pi.id DESC',
            ['a' => $artistId]
        );
    }

    public static function count(int $artistId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM portfolio_items WHERE artist_id = :a',
            ['a' => $artistId]
        );
    }

    public static function create(int $artistId, string $imagePath, ?string $caption, ?int $serviceId): int
    {
        $next = (int) Database::scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM portfolio_items WHERE artist_id = :a',
            ['a' => $artistId]
        );

        return Database::insert(
            'INSERT INTO portfolio_items (artist_id, service_id, image_path, caption, sort_order)
             VALUES (:a, :s, :i, :c, :o)',
            ['a' => $artistId, 's' => $serviceId, 'i' => $imagePath, 'c' => $caption, 'o' => $next]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findForArtist(int $id, int $artistId): ?array
    {
        return Database::selectOne(
            'SELECT * FROM portfolio_items WHERE id = :id AND artist_id = :a LIMIT 1',
            ['id' => $id, 'a' => $artistId]
        );
    }

    public static function delete(int $id, int $artistId): bool
    {
        return Database::affectingStatement(
            'DELETE FROM portfolio_items WHERE id = :id AND artist_id = :a',
            ['id' => $id, 'a' => $artistId]
        ) > 0;
    }
}
