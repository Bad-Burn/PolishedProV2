<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\BookingService;

final class BookingController extends Controller
{
    /** Step 1 + 2 on one page: pick a date, pick a slot, confirm. */
    public function create(Request $request): never
    {
        $service = $this->abortIfMissing(Service::find($request->intParam('service')), 'service');

        if ((int) $service['is_active'] !== 1) {
            $this->failure('That service is not currently bookable.', '/artists');
        }

        $artist = $this->abortIfMissing(
            Artist::findByUserId((int) $service['artist_id'], true),
            'artist'
        );

        if (Auth::check() && Auth::id() === (int) $service['artist_id']) {
            $this->failure('You cannot book your own service.', '/artist/services');
        }

        $duration = (int) $service['duration_min'];
        $buffer   = (int) $service['buffer_min'];

        // Default to the first date that actually has openings.
        $date = (string) $request->query('date', '');
        if ($date === '' || !$this->isValidDate($date)) {
            $date = AvailabilityService::nextAvailableDate((int) $artist['id'], $duration, $buffer)
                ?? date('Y-m-d');
        }

        $this->view('pages/book', [
            'title'    => 'Book ' . $service['name'],
            'service'  => $service,
            'artist'   => $artist,
            'date'     => $date,
            'slots'    => AvailabilityService::slotsFor((int) $artist['id'], $date, $duration, $buffer),
            'calendar' => AvailabilityService::calendar((int) $artist['id'], $duration, $buffer, 14, date('Y-m-d')),
            'freeCancelHours' => (int) Config::get('app.booking.free_cancel_hours', 24),
        ]);
    }

    public function store(Request $request): never
    {
        $serviceId = $request->int('service_id');
        $service   = Service::find($serviceId);

        if ($service === null) {
            $this->failure('That service is no longer available.', '/artists');
        }

        $data = $this->validate($request, [
            'service_id' => 'required|integer',
            'date'       => 'required|date',
            'time'       => 'required|time',
            'notes'      => 'max:500',
        ], [
            'date'  => 'Appointment date',
            'time'  => 'Appointment time',
            'notes' => 'Notes',
        ], '/book/' . $serviceId);

        $result = BookingService::create(
            (int) Auth::id(),
            $serviceId,
            (string) $data['date'],
            (string) $data['time'],
            $data['notes'] ?: null
        );

        if (!$result['ok']) {
            $this->failure(
                (string) $result['error'],
                '/book/' . $serviceId . '?date=' . urlencode((string) $data['date'])
            );
        }

        AuditLog::record('booking.created', 'booking', (int) $result['booking_id'], [
            'service_id' => $serviceId,
        ]);

        $this->success(
            'Booking request sent. Reference ' . $result['reference'] . '.',
            '/bookings/' . $result['booking_id']
        );
    }

    /** Booking detail, visible to the customer, the artist, and admins. */
    public function show(Request $request): never
    {
        $booking = $this->abortIfMissing(Booking::find($request->intParam('id')), 'booking');

        $userId = (int) Auth::id();
        $isMine = (int) $booking['customer_id'] === $userId
            || (int) $booking['artist_id'] === $userId
            || Auth::isAdmin();

        if (!$isMine) {
            $this->abort(403);
        }

        $this->view('pages/booking-detail', [
            'title'    => 'Booking ' . $booking['reference'],
            'booking'  => $booking,
            'timeline' => Booking::timeline((int) $booking['id']),
            'review'   => Review::forBooking((int) $booking['id']),
            'isCustomer' => (int) $booking['customer_id'] === $userId,
            'isArtist'   => (int) $booking['artist_id'] === $userId,
            'canReview'  => BookingService::canReview($booking, $userId),
            'freeCancel' => BookingService::isFreeCancellation($booking),
        ]);
    }

    /** One endpoint for every status change; the service layer authorises it. */
    public function updateStatus(Request $request): never
    {
        $bookingId = $request->intParam('id');
        $target    = $request->string('status');
        $reason    = $request->string('reason');

        if (mb_strlen($reason) > 300) {
            $reason = mb_substr($reason, 0, 300);
        }

        $result = BookingService::transition(
            $bookingId,
            $target,
            (int) Auth::id(),
            (string) Auth::role(),
            $reason !== '' ? $reason : null
        );

        if (!$result['ok']) {
            $this->failure((string) $result['error'], '/bookings/' . $bookingId);
        }

        AuditLog::record('booking.' . $target, 'booking', $bookingId, ['reason' => $reason ?: null]);

        $messages = [
            'confirmed' => 'Booking confirmed.',
            'declined'  => 'Booking declined.',
            'cancelled' => 'Booking cancelled.',
            'completed' => 'Booking marked as completed.',
            'no_show'   => 'Booking marked as a no-show.',
        ];

        $this->success($messages[$target] ?? 'Booking updated.', '/bookings/' . $bookingId);
    }

    /** Customer leaves a review on a completed booking. */
    public function review(Request $request): never
    {
        $bookingId = $request->intParam('id');
        $booking   = $this->abortIfMissing(Booking::find($bookingId), 'booking');

        if (!BookingService::canReview($booking, (int) Auth::id())) {
            $this->failure('You can only review your own completed appointments, and only once.', '/bookings/' . $bookingId);
        }

        $data = $this->validate($request, [
            'rating'  => 'required|integer|gte:1|lte:5',
            'comment' => 'max:1500',
        ], ['rating' => 'Rating'], '/bookings/' . $bookingId);

        $reviewId = Review::create([
            'booking_id'  => $bookingId,
            'customer_id' => (int) $booking['customer_id'],
            'artist_id'   => (int) $booking['artist_id'],
            'service_id'  => $booking['service_id'],
            'rating'      => (int) $data['rating'],
            'comment'     => $data['comment'] ?: null,
        ]);

        if ($reviewId === null) {
            $this->failure('You have already reviewed this appointment.', '/bookings/' . $bookingId);
        }

        \App\Models\Notification::push(
            (int) $booking['artist_id'],
            'review.received',
            'You received a new review',
            sprintf('%s rated %s %d out of 5.', $booking['customer_name'], $booking['service_name'], (int) $data['rating']),
            \App\Core\Url::to('/artist/reviews')
        );

        AuditLog::record('review.created', 'review', $reviewId, ['booking_id' => $bookingId]);

        $this->success('Thank you — your review is live.', '/bookings/' . $bookingId);
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y);
    }
}
