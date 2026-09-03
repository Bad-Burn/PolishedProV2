<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Url;
use App\Models\Artist;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Service;
use App\Models\Subscription;

/**
 * Booking creation and state transitions, with the notification side-effects
 * kept out of the controllers.
 */
final class BookingService
{
    /**
     * @return array{ok:bool, booking_id:?int, reference:?string, error:?string}
     */
    public static function create(int $customerId, int $serviceId, string $date, string $time, ?string $notes): array
    {
        $service = Service::find($serviceId);

        if ($service === null || (int) $service['is_active'] !== 1) {
            return self::error('That service is no longer available.');
        }

        $artistId = (int) $service['artist_id'];

        if ($artistId === $customerId) {
            return self::error('You cannot book your own service.');
        }

        $artist = Artist::findByUserId($artistId, true);
        if ($artist === null) {
            return self::error('That artist is not accepting bookings right now.');
        }

        $duration = (int) $service['duration_min'];
        $buffer   = (int) $service['buffer_min'];

        $check = AvailabilityService::validateSlot($artistId, $date, $time, $duration, $buffer);
        if (!$check['ok']) {
            return self::error((string) $check['reason']);
        }

        $startsAt = date('Y-m-d H:i:s', (int) strtotime($date . ' ' . $time));
        $endsAt   = date('Y-m-d H:i:s', (int) strtotime($startsAt) + (($duration + $buffer) * 60));

        // Artists on a plan with instant book skip the manual approval step.
        $status = Subscription::hasInstantBook($artistId) && (int) $artist['instant_book'] === 1
            ? 'confirmed'
            : 'pending';

        try {
            $result = Database::transaction(static function () use (
                $customerId, $artistId, $service, $startsAt, $endsAt, $duration, $notes, $status
            ): array {
                // Lock the artist's overlapping rows, then re-check. Two people
                // clicking the same slot at the same moment cannot both win.
                Database::run(
                    'SELECT id FROM bookings
                      WHERE artist_id = :a
                        AND status IN ("pending","confirmed")
                        AND starts_at < :ends AND ends_at > :starts
                      FOR UPDATE',
                    ['a' => $artistId, 'ends' => $endsAt, 'starts' => $startsAt]
                );

                if (Booking::hasConflict($artistId, $startsAt, $endsAt)) {
                    return ['conflict' => true];
                }

                $reference = Booking::generateReference();

                $bookingId = Booking::create([
                    'reference'      => $reference,
                    'customer_id'    => $customerId,
                    'artist_id'      => $artistId,
                    'service_id'     => (int) $service['id'],
                    'service_name'   => (string) $service['name'],
                    'starts_at'      => $startsAt,
                    'ends_at'        => $endsAt,
                    'duration_min'   => $duration,
                    'price'          => (float) $service['price'],
                    'payment_method' => 'cash',
                    'status'         => $status,
                    'notes'          => $notes,
                ]);

                Booking::logEvent($bookingId, 'created', $customerId);
                if ($status === 'confirmed') {
                    Booking::logEvent($bookingId, 'confirmed', null, 'Auto-confirmed (instant booking)');
                }

                Service::incrementBookings((int) $service['id']);

                return ['conflict' => false, 'booking_id' => $bookingId, 'reference' => $reference];
            });
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Booking creation failed: ' . $e->getMessage());
            return self::error('We could not complete that booking. Please try again.');
        }

        if (!empty($result['conflict'])) {
            return self::error('That slot was just taken. Please choose another time.');
        }

        $bookingId = (int) $result['booking_id'];
        $reference = (string) $result['reference'];

        self::notifyCreated($bookingId, $customerId, $artistId, $service, $startsAt, $status, $reference);

        return ['ok' => true, 'booking_id' => $bookingId, 'reference' => $reference, 'error' => null];
    }

    private static function notifyCreated(
        int $bookingId,
        int $customerId,
        int $artistId,
        array $service,
        string $startsAt,
        string $status,
        string $reference
    ): void {
        $when = date('M j', strtotime($startsAt)) . ' at ' . date('g:i A', strtotime($startsAt));
        $link = Url::to('/bookings/' . $bookingId);

        Notification::push(
            $artistId,
            'booking.created',
            $status === 'confirmed' ? 'New confirmed booking' : 'New booking request',
            sprintf('%s — %s (%s)', $service['name'], $when, $reference),
            $link
        );

        Notification::push(
            $customerId,
            'booking.created',
            $status === 'confirmed' ? 'Your booking is confirmed' : 'Booking request sent',
            $status === 'confirmed'
                ? sprintf('%s on %s. Reference %s.', $service['name'], $when, $reference)
                : sprintf('Waiting for the artist to confirm %s on %s.', $service['name'], $when),
            $link
        );
    }

    /**
     * Apply a status change with authorisation and sensible transition rules.
     *
     * @return array{ok:bool, error:?string}
     */
    public static function transition(int $bookingId, string $target, int $actorId, string $actorRole, ?string $reason = null): array
    {
        $booking = Booking::find($bookingId);
        if ($booking === null) {
            return ['ok' => false, 'error' => 'That booking no longer exists.'];
        }

        $isCustomer = (int) $booking['customer_id'] === $actorId;
        $isArtist   = (int) $booking['artist_id'] === $actorId;
        $isAdmin    = $actorRole === 'admin';

        if (!$isCustomer && !$isArtist && !$isAdmin) {
            return ['ok' => false, 'error' => 'You are not allowed to change that booking.'];
        }

        $current = (string) $booking['status'];

        // Who may move a booking to which state.
        $permitted = match ($target) {
            'confirmed' => ($isArtist || $isAdmin) && $current === 'pending',
            'declined'  => ($isArtist || $isAdmin) && $current === 'pending',
            'completed' => ($isArtist || $isAdmin) && in_array($current, ['confirmed', 'pending'], true),
            'no_show'   => ($isArtist || $isAdmin) && in_array($current, ['confirmed', 'pending'], true),
            'cancelled' => in_array($current, ['pending', 'confirmed'], true),
            default     => false,
        };

        if (!$permitted) {
            return ['ok' => false, 'error' => 'That change is not allowed for this booking.'];
        }

        if ($target === 'completed' && strtotime((string) $booking['starts_at']) > time()) {
            return ['ok' => false, 'error' => 'You can only complete an appointment after it has started.'];
        }

        if (!Booking::updateStatus($bookingId, $target, $actorId, $reason)) {
            return ['ok' => false, 'error' => 'We could not update that booking.'];
        }

        if (in_array($target, ['completed', 'no_show'], true)) {
            Artist::refreshCompletedCount((int) $booking['artist_id']);
        }

        self::notifyTransition($booking, $target, $actorId, $reason);

        return ['ok' => true, 'error' => null];
    }

    private static function notifyTransition(array $booking, string $target, int $actorId, ?string $reason): void
    {
        $bookingId  = (int) $booking['id'];
        $customerId = (int) $booking['customer_id'];
        $artistId   = (int) $booking['artist_id'];
        $link       = Url::to('/bookings/' . $bookingId);
        $when       = date('M j \a\t g:i A', strtotime((string) $booking['starts_at']));
        $service    = (string) $booking['service_name'];

        switch ($target) {
            case 'confirmed':
                Notification::push($customerId, 'booking.confirmed', 'Booking confirmed',
                    sprintf('%s on %s is confirmed.', $service, $when), $link);
                break;

            case 'declined':
                Notification::push($customerId, 'booking.declined', 'Booking declined',
                    $reason !== null && $reason !== ''
                        ? sprintf('%s on %s was declined: %s', $service, $when, $reason)
                        : sprintf('%s on %s was declined.', $service, $when),
                    $link);
                break;

            case 'cancelled':
                // Tell the other party, not the person who just did it.
                $recipient = $actorId === $customerId ? $artistId : $customerId;
                Notification::push($recipient, 'booking.cancelled', 'Booking cancelled',
                    sprintf('%s on %s was cancelled.%s', $service, $when,
                        $reason !== null && $reason !== '' ? ' Reason: ' . $reason : ''),
                    $link);
                break;

            case 'completed':
                Notification::push($customerId, 'booking.completed', 'How was your appointment?',
                    sprintf('Leave %s a review for %s.', $booking['display_name'] ?? 'your artist', $service),
                    $link);
                break;

            case 'no_show':
                Notification::push($customerId, 'booking.no_show', 'Marked as a no-show',
                    sprintf('%s on %s was marked as a no-show.', $service, $when), $link);
                break;
        }
    }

    /**
     * Whether the customer is still inside the free-cancellation window.
     */
    public static function isFreeCancellation(array $booking): bool
    {
        $hours = (int) Config::get('app.booking.free_cancel_hours', 24);
        return strtotime((string) $booking['starts_at']) - time() > $hours * 3600;
    }

    public static function canReview(array $booking, int $customerId): bool
    {
        return (int) $booking['customer_id'] === $customerId
            && $booking['status'] === 'completed'
            && empty($booking['review_id']);
    }

    /** @return array{ok:false, booking_id:null, reference:null, error:string} */
    private static function error(string $message): array
    {
        return ['ok' => false, 'booking_id' => null, 'reference' => null, 'error' => $message];
    }
}
