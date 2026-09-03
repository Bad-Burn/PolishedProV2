<?php
/**
 * @var array<string, mixed> $stats, $profile, $plan
 * @var array<int, array{label:string, revenue:float, bookings:int}> $trend
 * @var array<int, array<string, mixed>> $topServices
 * @var array<int, int> $distribution
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$maxRevenue  = max(1.0, max(array_map(static fn ($m) => (float) $m['revenue'], $trend)));
$maxService  = max(1, max(array_map(static fn ($s) => (int) $s['bookings'], $topServices ?: [['bookings' => 1]])));
$totalRated  = array_sum($distribution);

$completed = (int) ($stats['completed'] ?? 0);
$cancelled = (int) ($stats['cancelled'] ?? 0);
$total     = max(1, $completed + $cancelled);
?>
<header class="dash__head">
    <div>
        <h1>Analytics</h1>
        <p>How your business is actually doing.</p>
    </div>
    <span class="plan-badge"><?= e($plan['name']) ?></span>
</header>

<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Total earned</p>
        <p class="stat__value"><?= e(money_compact((float) ($stats['revenue_total'] ?? 0))) ?></p>
        <p class="stat__delta muted"><?= e(money_compact((float) ($stats['revenue_30d'] ?? 0))) ?> in the last 30 days</p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('check-circle') ?> Completed</p>
        <p class="stat__value"><?= (int) $completed ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('users') ?> Unique clients</p>
        <p class="stat__value"><?= (int) ($stats['clients'] ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('x') ?> Cancellation rate</p>
        <p class="stat__value"><?= e(number_format(($cancelled / $total) * 100, 0)) ?>%</p>
        <p class="stat__delta muted"><?= e(plural($cancelled, 'cancelled booking')) ?></p>
    </div>
</div>

<div class="grid grid--2 mb-8" style="align-items:start;">
    <!-- Revenue trend -->
    <section class="card">
        <h2 class="heading-md mb-1">Revenue, last 6 months</h2>
        <p class="muted small mb-5">Completed appointments only.</p>

        <div class="chart">
            <?php foreach ($trend as $month): ?>
                <div class="chart__col">
                    <span class="tiny muted numeric">
                        <?= $month['revenue'] > 0 ? e(money_compact($month['revenue'])) : '' ?>
                    </span>
                    <div class="chart__bar"
                         style="height: <?= (int) round(($month['revenue'] / $maxRevenue) * 100) ?>%;"
                         title="<?= e($month['label'] . ': ' . money($month['revenue'])) ?>"></div>
                    <span class="chart__label"><?= e($month['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Top services -->
    <section class="card">
        <h2 class="heading-md mb-1">Most booked services</h2>
        <p class="muted small mb-5">Where your time actually goes.</p>

        <?php if ($topServices === []): ?>
            <p class="muted small">Nothing booked yet.</p>
        <?php else: ?>
            <?php foreach ($topServices as $service): ?>
                <div class="rank-bar">
                    <span class="rank-bar__name"><?= e($service['service_name']) ?></span>
                    <span class="rank-bar__value"><?= (int) $service['bookings'] ?></span>
                    <span class="rank-bar__track">
                        <span class="rank-bar__fill" style="width: <?= (int) round(((int) $service['bookings'] / $maxService) * 100) ?>%;"></span>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<!-- Ratings -->
<section class="card">
    <h2 class="heading-md mb-5">Rating breakdown</h2>

    <?php if ($totalRated === 0): ?>
        <p class="muted small">No reviews yet. They start arriving once clients complete appointments.</p>
    <?php else: ?>
        <div class="rating-summary">
            <div class="text-center">
                <p class="rating-summary__score"><?= e(number_format((float) $profile['rating_avg'], 1)) ?></p>
                <?= stars((float) $profile['rating_avg'], false) ?>
                <p class="tiny muted mt-2"><?= e(plural($totalRated, 'review')) ?></p>
            </div>

            <div>
                <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                    <?php $count = $distribution[$star] ?? 0; ?>
                    <div class="rating-bar">
                        <span class="muted"><?= $star ?>★</span>
                        <span class="rating-bar__track">
                            <span class="rating-bar__fill" style="width: <?= (int) round(($count / $totalRated) * 100) ?>%;"></span>
                        </span>
                        <span class="muted numeric"><?= (int) $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
