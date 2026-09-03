<?php
/**
 * @var array<string, mixed> $bookings
 * @var array<int, array{label:string, bookings:int}> $trend
 * @var array<string, int> $counts
 * @var float $mrr
 * @var array<int, array<string, mixed>> $planMix, $recentLog
 * @var int $pendingArtists
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-admin', ['pendingArtists' => $pendingArtists]);
end_section();

$maxBookings = max(1, max(array_map(static fn ($m) => (int) $m['bookings'], $trend)));
?>
<header class="dash__head">
    <div>
        <h1>Admin console</h1>
        <p>Everything happening on the platform.</p>
    </div>
    <span class="badge badge--outline"><?= e(date('D, M j')) ?></span>
</header>

<?php if ($pendingArtists > 0): ?>
    <div class="alert alert--warning mb-6">
        <?= icon('alert') ?>
        <span>
            <strong><?= e(plural($pendingArtists, 'artist')) ?> waiting for approval.</strong>
            They cannot appear in search or take bookings until you review them.
            <a href="<?= e(url('/admin/users', ['awaiting' => 1])) ?>">Review now</a>.
        </span>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('users') ?> Clients</p>
        <p class="stat__value"><?= e(number_format($counts['customers'])) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('scissors') ?> Artists</p>
        <p class="stat__value"><?= e(number_format($counts['artists'])) ?></p>
        <?php if ($counts['pending'] > 0): ?>
            <p class="stat__delta text-caution"><?= (int) $counts['pending'] ?> awaiting approval</p>
        <?php endif; ?>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('calendar') ?> Bookings</p>
        <p class="stat__value"><?= e(number_format((int) ($bookings['total'] ?? 0))) ?></p>
        <p class="stat__delta muted"><?= (int) ($bookings['today'] ?? 0) ?> made today</p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Subscription MRR</p>
        <p class="stat__value"><?= e(money_compact($mrr)) ?></p>
        <p class="stat__delta muted">Recurring, PayPal only</p>
    </div>
</div>

<div class="grid grid--2 mb-8" style="align-items:start;">
    <!-- Booking volume -->
    <section class="card">
        <h2 class="heading-md mb-1">Appointments booked, last 6 months</h2>
        <p class="muted small mb-5">
            How busy the platform is. Booking prices are between the artist and their client —
            the platform takes no commission, so they are not reported here.
        </p>

        <div class="chart">
            <?php foreach ($trend as $month): ?>
                <div class="chart__col">
                    <span class="tiny muted numeric">
                        <?= $month['bookings'] > 0 ? (int) $month['bookings'] : '' ?>
                    </span>
                    <div class="chart__bar" style="height: <?= (int) round(($month['bookings'] / $maxBookings) * 100) ?>%;"></div>
                    <span class="chart__label"><?= e($month['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Plan mix -->
    <section class="card">
        <h2 class="heading-md mb-1">Plan mix</h2>
        <p class="muted small mb-5">Active subscriptions by tier.</p>

        <?php $maxSubs = max(1, max(array_map(static fn ($p) => (int) $p['subscribers'], $planMix ?: [['subscribers' => 1]]))); ?>

        <?php foreach ($planMix as $planRow): ?>
            <div class="rank-bar">
                <span class="rank-bar__name">
                    <?= e($planRow['name']) ?>
                    <span class="muted tiny">
                        <?= (float) $planRow['price_monthly'] > 0 ? e(money((float) $planRow['price_monthly'])) . '/mo' : 'free' ?>
                    </span>
                </span>
                <span class="rank-bar__value"><?= (int) $planRow['subscribers'] ?></span>
                <span class="rank-bar__track">
                    <span class="rank-bar__fill" style="width: <?= (int) round(((int) $planRow['subscribers'] / $maxSubs) * 100) ?>%;"></span>
                </span>
            </div>
        <?php endforeach; ?>

        <a class="link-arrow small mt-4" href="<?= e(url('/admin/subscriptions')) ?>">Manage subscriptions</a>
    </section>
</div>

<div class="grid grid--2" style="align-items:start;">
    <!-- Booking health -->
    <section class="card">
        <h2 class="heading-md mb-5">Booking health</h2>

        <dl>
            <div class="summary-line">
                <dt>Awaiting artist confirmation</dt>
                <dd><?= (int) ($bookings['pending'] ?? 0) ?></dd>
            </div>
            <div class="summary-line">
                <dt>Completed</dt>
                <dd><?= (int) ($bookings['completed'] ?? 0) ?></dd>
            </div>
            <div class="summary-line">
                <dt>Cancelled or declined</dt>
                <dd><?= (int) ($bookings['cancelled'] ?? 0) ?></dd>
            </div>
            <div class="summary-line summary-total">
                <dt>Completed in the last 30 days</dt>
                <dd><?= (int) ($bookings['completed_30d'] ?? 0) ?></dd>
            </div>
        </dl>

        <p class="tiny muted mt-3">
            Artist earnings are not shown in the console.
        </p>

        <a class="link-arrow small mt-4" href="<?= e(url('/admin/bookings')) ?>">View all bookings</a>
    </section>

    <!-- Audit trail -->
    <section class="card">
        <h2 class="heading-md mb-1">Recent sign-in activity</h2>
        <p class="muted small mb-5">Who signed in or out most recently.</p>

        <?php if ($recentLog === []): ?>
            <p class="muted small">Nothing logged yet.</p>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($recentLog as $entry): ?>
                    <div class="timeline__item">
                        <p class="small strong"><?= e(str_replace(['.', '_'], [' · ', ' '], (string) $entry['action'])) ?></p>
                        <p class="tiny muted">
                            <?= e($entry['actor_name'] ?? 'System') ?> &middot; <?= e(relative_time($entry['created_at'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a class="link-arrow small mt-4" href="<?= e(url('/admin/audit-log')) ?>">Full sign-in log</a>
    </section>
</div>
