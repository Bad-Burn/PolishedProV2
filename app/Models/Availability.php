<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * The artist's working week (recurring rules) and one-off closures (blocks).
 */
final class Availability
{
    public const WEEKDAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function rulesFor(int $artistId): array
    {
        return Database::select(
            'SELECT * FROM availability_rules WHERE artist_id = :a ORDER BY weekday ASC, start_time ASC',
            ['a' => $artistId]
        );
    }

    /**
     * Rules grouped by weekday, with every day present even when closed —
     * lets the schedule editor render a full week without extra logic.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function weekFor(int $artistId): array
    {
        $week = array_fill_keys(array_keys(self::WEEKDAYS), []);

        foreach (self::rulesFor($artistId) as $rule) {
            $week[(int) $rule['weekday']][] = $rule;
        }

        return $week;
    }

    /** @return array<int, array<string, mixed>> open windows for a given date */
    public static function rulesForDate(int $artistId, string $date): array
    {
        $weekday = (int) date('w', strtotime($date));

        return Database::select(
            'SELECT start_time, end_time
               FROM availability_rules
              WHERE artist_id = :a AND weekday = :w AND is_open = 1
              ORDER BY start_time ASC',
            ['a' => $artistId, 'w' => $weekday]
        );
    }

    /**
     * Replace an artist's whole week in one transaction.
     *
     * @param array<int, array<int, array{start:string, end:string}>> $week weekday => windows
     */
    public static function replaceWeek(int $artistId, array $week): void
    {
        Database::transaction(static function () use ($artistId, $week): void {
            Database::affectingStatement('DELETE FROM availability_rules WHERE artist_id = :a', ['a' => $artistId]);

            foreach ($week as $weekday => $windows) {
                if (!array_key_exists($weekday, self::WEEKDAYS)) {
                    continue;
                }

                foreach ($windows as $window) {
                    $start = (string) ($window['start'] ?? '');
                    $end   = (string) ($window['end'] ?? '');

                    // Skip anything malformed or inverted rather than storing nonsense.
                    if (!self::isTime($start) || !self::isTime($end) || $start >= $end) {
                        continue;
                    }

                    Database::insert(
                        'INSERT IGNORE INTO availability_rules (artist_id, weekday, start_time, end_time, is_open)
                         VALUES (:a, :w, :s, :e, 1)',
                        ['a' => $artistId, 'w' => (int) $weekday, 's' => $start . ':00', 'e' => $end . ':00']
                    );
                }
            }
        });
    }

    private static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    // -- One-off closures ---------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public static function blocksFor(int $artistId, ?string $fromDate = null): array
    {
        $sql  = 'SELECT * FROM availability_blocks WHERE artist_id = :a';
        $bind = ['a' => $artistId];

        if ($fromDate !== null) {
            $sql .= ' AND block_date >= :from';
            $bind['from'] = $fromDate;
        }

        return Database::select($sql . ' ORDER BY block_date ASC, start_time ASC', $bind);
    }

    /** @return array<int, array<string, mixed>> */
    public static function blocksOn(int $artistId, string $date): array
    {
        return Database::select(
            'SELECT start_time, end_time FROM availability_blocks WHERE artist_id = :a AND block_date = :d',
            ['a' => $artistId, 'd' => $date]
        );
    }

    public static function isFullDayBlocked(int $artistId, string $date): bool
    {
        return Database::scalar(
            'SELECT 1 FROM availability_blocks
              WHERE artist_id = :a AND block_date = :d AND start_time IS NULL
              LIMIT 1',
            ['a' => $artistId, 'd' => $date]
        ) !== null;
    }

    public static function addBlock(int $artistId, string $date, ?string $start, ?string $end, ?string $reason): int
    {
        return Database::insert(
            'INSERT INTO availability_blocks (artist_id, block_date, start_time, end_time, reason)
             VALUES (:a, :d, :s, :e, :r)',
            [
                'a' => $artistId,
                'd' => $date,
                's' => $start === null || $start === '' ? null : $start . ':00',
                'e' => $end === null || $end === '' ? null : $end . ':00',
                'r' => $reason,
            ]
        );
    }

    public static function deleteBlock(int $id, int $artistId): bool
    {
        return Database::affectingStatement(
            'DELETE FROM availability_blocks WHERE id = :id AND artist_id = :a',
            ['id' => $id, 'a' => $artistId]
        ) > 0;
    }

    /** Seed a sensible Mon–Sat week for a newly approved artist. */
    public static function seedDefaults(int $artistId): void
    {
        if (Database::scalar('SELECT 1 FROM availability_rules WHERE artist_id = :a LIMIT 1', ['a' => $artistId]) !== null) {
            return;
        }

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            Database::insert(
                'INSERT IGNORE INTO availability_rules (artist_id, weekday, start_time, end_time, is_open)
                 VALUES (:a, :w, "10:00:00", "18:00:00", 1)',
                ['a' => $artistId, 'w' => $weekday]
            );
        }
    }
}
