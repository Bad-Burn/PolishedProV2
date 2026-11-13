<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Emails;

final class Notification
{
    /**
     * Notification types that also go out by email, matched by prefix.
     *
     * Anything someone has to act on or turn up for. The welcome message is
     * left out (the verification email does that job), and so is the
     * "password reset requested" notice — the reset email itself is the
     * warning, and a second one would only add noise.
     */
    private const EMAILED = ['booking.', 'subscription', 'account.approved', 'review.received'];

    public static function push(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        $id = Database::insert(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (:u, :t, :title, :body, :link)',
            [
                'u'     => $userId,
                't'     => substr($type, 0, 40),
                'title' => substr($title, 0, 140),
                'body'  => $body === null ? null : substr($body, 0, 400),
                'link'  => $link,
            ]
        );

        if (self::isEmailed($type)) {
            $user = Database::selectOne(
                'SELECT email, full_name FROM users WHERE id = :u AND status = "active" LIMIT 1',
                ['u' => $userId]
            );
            if ($user !== null) {
                Emails::notification($user, $title, $body, $link);
            }
        }

        return $id;
    }

    private static function isEmailed(string $type): bool
    {
        foreach (self::EMAILED as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
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
