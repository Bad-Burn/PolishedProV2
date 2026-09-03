<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification
{
    public static function push(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u, :t, :title, :body, :link)',
            [
                'u'     => $userId,
                't'     => substr($type, 0, 40),
                'title' => substr($title, 0, 140),
                'body'  => $body === null ? null : substr($body, 0, 400),
                'link'  => $link,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId, int $limit = 30): array
    {
        return Database::select(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)),
            ['u' => $userId]
        );
    }

    /** @return array<int, array<string, mixed>> the newest few, for the header dropdown */
    public static function recentFor(int $userId, int $limit = 6): array
    {
        return self::forUser($userId, $limit);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL',
            ['u' => $userId]
        );
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::affectingStatement(
            'UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u AND read_at IS NULL',
            ['id' => $id, 'u' => $userId]
        );
    }

    public static function markAllRead(int $userId): int
    {
        return Database::affectingStatement(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL',
            ['u' => $userId]
        );
    }

    public static function delete(int $id, int $userId): void
    {
        Database::affectingStatement(
            'DELETE FROM notifications WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
    }

    /** Housekeeping: drop read notifications older than 90 days. */
    public static function purgeOld(): int
    {
        return Database::affectingStatement(
            'DELETE FROM notifications
              WHERE read_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
        );
    }
}
