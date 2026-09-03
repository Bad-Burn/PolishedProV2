<?php
/**
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Analytics</h1>
        <p>Revenue trends, top services and rating breakdowns.</p>
    </div>
</header>

<div class="card" style="max-width: 40rem;">
    <span class="avatar avatar--lg mb-5" style="background: var(--accent-soft); color: var(--accent);">
        <?= icon('chart') ?>
    </span>

    <h2 class="heading-lg mb-3">Analytics comes with a paid plan</h2>

    <p class="muted mb-6">
        You are on the <strong><?= e($plan['name']) ?></strong> plan. Upgrading unlocks a six-month
        revenue trend, your most-booked services, your cancellation rate, and a full rating breakdown —
        alongside more service listings and higher placement in search.
    </p>

    <p class="muted small mb-6">
        In the meantime, your dashboard still shows your bookings, earnings for the last 30 days,
        and your current rating.
    </p>

    <div class="row">
        <a class="btn btn--primary" href="<?= e(url('/artist/subscription')) ?>">Compare plans</a>
        <a class="btn btn--ghost" href="<?= e(url('/artist')) ?>">Back to dashboard</a>
    </div>
</div>
