<?php
/**
 * @var array<int, array<string, mixed>> $bookings
 * @var string $scope
 * @var array<string, mixed> $stats
 * @var array<string, int> $counts
 * @var int $savedCount
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-customer', ['savedCount' => $savedCount]);
end_section();

$tabs = [
    'upcoming'  => 'Upcoming',
    'past'      => 'Past',
    'cancelled' => 'Cancelled',
];
?>
<header class="dash__head">
    <div>
        <h1>My bookings</h1>
        <p>Everything you have booked, in one place.</p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('/artists')) ?>">Book something new</a>
</header>

<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('calendar') ?> Upcoming</p>
        <p class="stat__value"><?= (int) ($stats['upcoming'] ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('check-circle') ?> Completed</p>
        <p class="stat__value"><?= (int) ($stats['completed'] ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Total spent</p>
        <p class="stat__value"><?= e(money_compact((float) ($stats['spent'] ?? 0))) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('heart') ?> Saved artists</p>
        <p class="stat__value"><?= (int) $savedCount ?></p>
    </div>
</div>

<nav class="tabs" aria-label="Booking filter">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="tabs__link <?= $scope === $key ? 'is-active' : '' ?>"
           href="<?= e(url('/account', $key === 'upcoming' ? [] : ['tab' => $key])) ?>">
            <?= e($label) ?>
            <?php if (isset($counts[$key]) && $counts[$key] > 0): ?>
                <span class="tabs__count"><?= (int) $counts[$key] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($bookings === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'calendar',
        'title'   => match ($scope) {
            'past'      => 'No past appointments yet',
            'cancelled' => 'Nothing cancelled',
            default     => 'No upcoming appointments',
        },
        'message' => match ($scope) {
            'past'      => 'Once you complete an appointment it will show up here, and you can leave a review.',
            'cancelled' => 'Cancelled and declined bookings appear here.',
            default     => 'Find an artist whose work you like and book a slot that suits you.',
        },
        'actionLabel' => $scope === 'upcoming' ? 'Browse artists' : null,
        'actionUrl'   => $scope === 'upcoming' ? url('/artists') : null,
    ]) ?>
<?php else: ?>
    <?php foreach ($bookings as $booking): ?>
        <?= view_partial('booking-item', ['booking' => $booking, 'side' => 'customer']) ?>
    <?php endforeach; ?>
<?php endif; ?>
