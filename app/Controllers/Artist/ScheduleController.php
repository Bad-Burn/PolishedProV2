<?php

declare(strict_types=1);

namespace App\Controllers\Artist;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Subscription;

final class ScheduleController extends Controller
{
    public function index(Request $request): never
    {
        $artistId = (int) Auth::id();

        $this->view('artist/schedule', [
            'title'     => 'Schedule',
            'week'      => Availability::weekFor($artistId),
            'weekdays'  => Availability::WEEKDAYS,
            'blocks'    => Availability::blocksFor($artistId, date('Y-m-d')),
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
        ]);
    }

    /**
     * Replace the whole working week in one submit.
     *
     * Input arrives as hours[weekday][index][start|end], which keeps the form
     * simple and lets the model validate and rewrite the set atomically.
     */
    public function updateWeek(Request $request): never
    {
        $artistId = (int) Auth::id();
        $input    = $request->all()['hours'] ?? [];

        if (!is_array($input)) {
            $this->failure('We could not read that schedule.', '/artist/schedule');
        }

        $week   = [];
        $errors = [];

        foreach ($input as $weekday => $windows) {
            $weekday = (int) $weekday;

            if (!array_key_exists($weekday, Availability::WEEKDAYS) || !is_array($windows)) {
                continue;
            }

            $week[$weekday] = [];

            foreach ($windows as $window) {
                if (!is_array($window)) {
                    continue;
                }

                $start = trim((string) ($window['start'] ?? ''));
                $end   = trim((string) ($window['end'] ?? ''));

                if ($start === '' && $end === '') {
                    continue; // an empty row just means "not used"
                }

                if ($start === '' || $end === '') {
                    $errors[] = Availability::WEEKDAYS[$weekday] . ': fill in both a start and an end time.';
                    continue;
                }

                if ($start >= $end) {
                    $errors[] = Availability::WEEKDAYS[$weekday] . ': the end time has to be after the start time.';
                    continue;
                }

                $week[$weekday][] = ['start' => $start, 'end' => $end];
            }
        }

        if ($errors !== []) {
            $this->failure(implode(' ', array_slice($errors, 0, 3)), '/artist/schedule');
        }

        Availability::replaceWeek($artistId, $week);
        AuditLog::record('schedule.updated', 'artist', $artistId);

        $this->success('Your working hours have been saved.', '/artist/schedule');
    }

    public function addBlock(Request $request): never
    {
        $artistId = (int) Auth::id();

        $data = $this->validate($request, [
            'block_date' => 'required|date',
            'reason'     => 'max:160',
        ], ['block_date' => 'Date'], '/artist/schedule');

        $date = date('Y-m-d', (int) strtotime((string) $data['block_date']));

        if ($date < date('Y-m-d')) {
            $this->failure('Pick a date from today onwards.', '/artist/schedule');
        }

        $fullDay = $request->bool('full_day');
        $start   = $fullDay ? null : $request->string('start_time');
        $end     = $fullDay ? null : $request->string('end_time');

        if (!$fullDay) {
            if ($start === '' || $end === '') {
                $this->failure('Give a start and end time, or tick "all day".', '/artist/schedule');
            }
            if ($start >= $end) {
                $this->failure('The end time has to be after the start time.', '/artist/schedule');
            }
        }

        // Warn rather than silently orphan existing appointments.
        $affected = $this->bookingsInBlock($artistId, $date, $start, $end);

        Availability::addBlock($artistId, $date, $start, $end, $data['reason'] ?: null);
        AuditLog::record('schedule.block_added', 'artist', $artistId, ['date' => $date]);

        if ($affected > 0) {
            $this->failure(
                'Time off saved — but you still have ' . plural($affected, 'booking') .
                ' in that window. Cancel or move them from your bookings list.',
                '/artist/schedule'
            );
        }

        $this->success('Time off added.', '/artist/schedule');
    }

    private function bookingsInBlock(int $artistId, string $date, ?string $start, ?string $end): int
    {
        $from = $date . ' ' . ($start ?? '00:00') . ':00';
        $to   = $date . ' ' . ($end ?? '23:59') . ':59';

        return count(array_filter(
            Booking::occupiedOn($artistId, $date),
            static fn (array $b): bool => $b['starts_at'] < $to && $b['ends_at'] > $from
        ));
    }

    public function deleteBlock(Request $request): never
    {
        $artistId = (int) Auth::id();

        if (!Availability::deleteBlock($request->intParam('id'), $artistId)) {
            $this->abort(404, 'We could not find that time off.');
        }

        AuditLog::record('schedule.block_removed', 'artist', $artistId);

        $this->success('Time off removed.', '/artist/schedule');
    }
}
