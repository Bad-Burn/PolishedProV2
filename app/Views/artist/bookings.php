<?php
/**
 * @var array<int, array<string, mixed>> $bookings
 * @var string $scope
 * @var array<string, int> $counts
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$tabs = [
    'upcoming'  => 'Upcoming',
    'pending'   => 'Requests',
    'past'      => 'Past',
    'cancelled' => 'Cancelled',
];
?>
<header class="dash__head">
    <div>
        <h1>Bookings</h1>
        <p>Confirm requests, mark appointments complete, and keep your calendar honest.</p>
    </div>
    <a class="btn btn--secondary" href="<?= e(url('/artist/schedule')) ?>">Manage availability</a>
</header>

<nav class="tabs" aria-label="Booking filter">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="tabs__link <?= $scope === $key ? 'is-active' : '' ?>"
           href="<?= e(url('/artist/bookings', $key === 'upcoming' ? [] : ['tab' => $key])) ?>">
            <?= e($label) ?>
            <?php if (($counts[$key] ?? 0) > 0): ?>
                <span class="tabs__count"><?= (int) $counts[$key] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($bookings === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'calendar',
        'title'   => match ($scope) {
            'pending'   => 'No requests waiting',
            'past'      => 'No past appointments',
            'cancelled' => 'Nothing cancelled',
            default     => 'No upcoming appointments',
        },
        'message' => match ($scope) {
            'pending'   => 'When someone requests a slot it lands here for you to confirm or decline.',
            'past'      => 'Completed appointments will be listed here.',
            'cancelled' => 'Cancelled and declined bookings appear here.',
            default     => 'Share your profile link, and make sure your working hours are up to date.',
        },
        'actionLabel' => $scope === 'upcoming' ? 'Check your schedule' : null,
        'actionUrl'   => $scope === 'upcoming' ? url('/artist/schedule') : null,
    ]) ?>
<?php else: ?>
    <?php foreach ($bookings as $booking): ?>
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
                    <span><?= e($booking['customer_name']) ?></span>
                    <span><?= e(duration_label((int) $booking['duration_min'])) ?></span>
                    <span class="numeric"><?= e($booking['reference']) ?></span>
                </div>
                <?php if (!empty($booking['notes'])): ?>
                    <p class="tiny muted mt-2">&ldquo;<?= e(excerpt($booking['notes'], 110)) ?>&rdquo;</p>
                <?php endif; ?>
            </div>

            <div class="booking-item__side">
                <?php if ($booking['status'] === 'pending'): ?>
                    <div class="row row--tight row--nowrap">
                        <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="confirmed">
                            <button class="btn btn--primary btn--sm" type="submit">Confirm</button>
                        </form>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>">Details</a>
                    </div>
                <?php elseif ($booking['status'] === 'confirmed' && strtotime((string) $booking['starts_at']) < time()): ?>
                    <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="completed">
                        <button class="btn btn--secondary btn--sm" type="submit">Mark complete</button>
                    </form>
                <?php else: ?>
                    <?= view_partial('status-badge', ['status' => $booking['status']]) ?>
                <?php endif; ?>

                <span class="strong numeric"><?= e(money((float) $booking['price'])) ?></span>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
