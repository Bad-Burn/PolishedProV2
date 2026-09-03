<?php
/**
 * Compact booking row used in both the customer and artist lists.
 *
 * @var array<string, mixed> $booking
 * @var string $side 'customer' | 'artist'
 */

declare(strict_types=1);

$booking = $booking ?? [];
$side    = $side ?? 'customer';

$counterparty = $side === 'artist'
    ? ($booking['customer_name'] ?? 'Client')
    : ($booking['display_name'] ?? $booking['artist_name'] ?? 'Artist');
?>
<article class="booking-item">
    <div class="booking-item__when">
        <div class="booking-item__month"><?= e(date_label($booking['starts_at'], 'M')) ?></div>
        <div class="booking-item__day"><?= e(date_label($booking['starts_at'], 'j')) ?></div>
        <div class="booking-item__time"><?= e(time_label($booking['starts_at'])) ?></div>
    </div>

    <div style="min-width:0;">
        <p class="booking-item__title">
            <a href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>"><?= e($booking['service_name']) ?></a>
        </p>

        <div class="booking-item__meta">
            <span><?= e($side === 'artist' ? 'with ' : 'with ') ?><?= e($counterparty) ?></span>
            <span><?= e(duration_label((int) $booking['duration_min'])) ?></span>
            <span class="numeric"><?= e($booking['reference']) ?></span>
        </div>
    </div>

    <div class="booking-item__side">
        <?= view_partial('status-badge', ['status' => $booking['status']]) ?>
        <span class="strong numeric"><?= e(money((float) $booking['price'])) ?></span>
    </div>
</article>
