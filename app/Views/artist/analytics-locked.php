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

    <h2 class="heading-lg mb-3">Analytics isn't included with Premium</h2>

    <p class="muted mb-6">
        You are on the <strong><?= e($plan['name']) ?></strong> plan. This dashboard shows your
        bookings, recent earnings, and current rating. Contact support if you need detailed analytics.
    </p>

    <p class="muted small mb-6">
        In the meantime, your dashboard still shows your bookings, earnings for the last 30 days,
        and your current rating.
    </p>

    <div class="row">
        <a class="btn btn--primary" href="<?= e(url('/artist/subscription')) ?>">View Premium billing</a>
        <a class="btn btn--ghost" href="<?= e(url('/artist')) ?>">Back to dashboard</a>
    </div>
</div>
