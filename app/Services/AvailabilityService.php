<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Availability;
use App\Models\Booking;

/**
 * Turns an artist's working week, their time off, and their existing bookings
 * into a concrete list of bookable start times for a given service and date.
 *
 * The v1 system let customers type any date and time they liked, which is how
 * you end up with two clients booked for 2pm on a day the artist is closed.
 */
final class AvailabilityService
{
    /**
     * Bookable start times for one date.
     *
     * @return array<int, array{time:string, label:string, ends:string}>
     */
    public static function slotsFor(int $artistId, string $date, int $durationMin, int $bufferMin = 0): array
    {
        if (!self::isValidDate($date)) {
            return [];
        }

        // A whole-day closure short-circuits everything else.
        if (Availability::isFullDayBlocked($artistId, $date)) {
            return [];
        }

        $windows = Availability::rulesForDate($artistId, $date);
        if ($windows === []) {
            return [];
        }

        $total    = max(5, $durationMin + $bufferMin);
        $interval = max(5, (int) Config::get('app.booking.slot_interval_minutes', 15)) * 60;
        $earliest = time() + ((int) Config::get('app.booking.min_lead_time_minutes', 120) * 60);

        $busy = self::busyRanges($artistId, $date);
        $slots = [];

        foreach ($windows as $window) {
            $windowStart = strtotime($date . ' ' . $window['start_time']);
            $windowEnd   = strtotime($date . ' ' . $window['end_time']);

            if ($windowStart === false || $windowEnd === false || $windowStart >= $windowEnd) {
                continue;
            }

            for ($start = $windowStart; $start + ($total * 60) <= $windowEnd; $start += $interval) {
                $end = $start + ($total * 60);

                // Too soon to book.
                if ($start < $earliest) {
                    continue;
                }

                if (self::overlapsAny($start, $end, $busy)) {
                    continue;
                }

                $slots[date('H:i', $start)] = [
                    'time'  => date('H:i', $start),
                    'label' => date('g:i A', $start),
                    'ends'  => date('g:i A', $start + ($durationMin * 60)),
                ];
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Every busy range on a date: confirmed/pending bookings plus partial-day
     * blocks, as [startTimestamp, endTimestamp] pairs.
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private static function busyRanges(int $artistId, string $date): array
    {
        $ranges = [];

        foreach (Booking::occupiedOn($artistId, $date) as $booking) {
            $start = strtotime((string) $booking['starts_at']);
            $end   = strtotime((string) $booking['ends_at']);
            if ($start !== false && $end !== false) {
                $ranges[] = [$start, $end];
            }
        }

        foreach (Availability::blocksOn($artistId, $date) as $block) {
            if ($block['start_time'] === null || $block['end_time'] === null) {
                continue; // full-day blocks are handled before we get here
            }

            $start = strtotime($date . ' ' . $block['start_time']);
            $end   = strtotime($date . ' ' . $block['end_time']);
            if ($start !== false && $end !== false) {
                $ranges[] = [$start, $end];
            }
        }

        return $ranges;
    }

    /** @param array<int, array{0:int, 1:int}> $ranges */
    private static function overlapsAny(int $start, int $end, array $ranges): bool
    {
        foreach ($ranges as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * A rolling calendar strip: each day flagged with whether anything is free.
     *
     * @return array<int, array{date:string, weekday:string, day:string, month:string,
     *                          is_today:bool, has_slots:bool, slot_count:int}>
     */
    public static function calendar(int $artistId, int $durationMin, int $bufferMin = 0, int $days = 14, ?string $from = null): array
    {
        $maxAdvance = (int) Config::get('app.booking.max_advance_days', 60);
        $days       = max(1, min($maxAdvance, $days));
        $cursor     = $from !== null && self::isValidDate($from) ? strtotime($from) : time();

        $calendar = [];

        for ($i = 0; $i < $days; $i++) {
            $timestamp = strtotime("+{$i} days", $cursor);
            $date      = date('Y-m-d', $timestamp);
            $slots     = self::slotsFor($artistId, $date, $durationMin, $bufferMin);

            $calendar[] = [
                'date'       => $date,
                'weekday'    => date('D', $timestamp),
                'day'        => date('j', $timestamp),
                'month'      => date('M', $timestamp),
                'is_today'   => $date === date('Y-m-d'),
                'has_slots'  => $slots !== [],
                'slot_count' => count($slots),
            ];
        }

        return $calendar;
    }

    /**
     * The first date in the booking window that has any availability, so the
     * booking page can open on a useful day instead of an empty one.
     */
    public static function nextAvailableDate(int $artistId, int $durationMin, int $bufferMin = 0): ?string
    {
        $maxAdvance = (int) Config::get('app.booking.max_advance_days', 60);

        for ($i = 0; $i < $maxAdvance; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            if (self::slotsFor($artistId, $date, $durationMin, $bufferMin) !== []) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Authoritative re-check before a booking is written.
     *
     * The slot list shown in the browser is a convenience; this is what
     * actually decides, because the page may be minutes stale or forged.
     *
     * @return array{ok:bool, reason:?string}
     */
    public static function validateSlot(int $artistId, string $date, string $time, int $durationMin, int $bufferMin = 0): array
    {
        if (!self::isValidDate($date) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return ['ok' => false, 'reason' => 'That date and time are not valid.'];
        }

        $start = strtotime($date . ' ' . $time);
        if ($start === false) {
            return ['ok' => false, 'reason' => 'That date and time are not valid.'];
        }

        $leadSeconds = (int) Config::get('app.booking.min_lead_time_minutes', 120) * 60;
        if ($start < time() + $leadSeconds) {
            $hours = (int) round($leadSeconds / 3600);
            return ['ok' => false, 'reason' => "Appointments need at least {$hours} hours' notice. Please pick a later slot."];
        }

        $maxAdvance = (int) Config::get('app.booking.max_advance_days', 60);
        if ($start > strtotime("+{$maxAdvance} days")) {
            return ['ok' => false, 'reason' => "Bookings only open {$maxAdvance} days ahead."];
        }

        if (Availability::isFullDayBlocked($artistId, $date)) {
            return ['ok' => false, 'reason' => 'That artist is not taking appointments on that date.'];
        }

        $end   = $start + (($durationMin + $bufferMin) * 60);
        $fits  = false;

        foreach (Availability::rulesForDate($artistId, $date) as $window) {
            $windowStart = strtotime($date . ' ' . $window['start_time']);
            $windowEnd   = strtotime($date . ' ' . $window['end_time']);

            if ($windowStart !== false && $windowEnd !== false && $start >= $windowStart && $end <= $windowEnd) {
                $fits = true;
                break;
            }
        }

        if (!$fits) {
            return ['ok' => false, 'reason' => 'That time falls outside the artist’s working hours.'];
        }

        if (self::overlapsAny($start, $end, self::busyRanges($artistId, $date))) {
            return ['ok' => false, 'reason' => 'That slot was just taken. Please choose another time.'];
        }

        return ['ok' => true, 'reason' => null];
    }

    private static function isValidDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $date));

        return checkdate($m, $d, $y);
    }

    /** Human summary of the working week, e.g. "Mon–Fri 10:00 AM – 7:00 PM". */
    public static function weekSummary(int $artistId): array
    {
        $summary = [];

        foreach (Availability::weekFor($artistId) as $weekday => $windows) {
            $summary[] = [
                'weekday' => Availability::WEEKDAYS[$weekday],
                'short'   => substr(Availability::WEEKDAYS[$weekday], 0, 3),
                'closed'  => $windows === [],
                'windows' => array_map(
                    static fn (array $w): string => date('g:i A', strtotime($w['start_time']))
                        . ' – ' . date('g:i A', strtotime($w['end_time'])),
                    $windows
                ),
            ];
        }

        return $summary;
    }
}
