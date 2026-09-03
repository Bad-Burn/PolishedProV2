<?php
/**
 * Artist dashboard navigation, with a live plan-usage meter so listing limits
 * are visible before they bite rather than as an error at save time.
 *
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

use App\Models\Booking;
use App\Core\Auth;

$plan      = $plan ?? [];
$usedSlots = $usedSlots ?? 0;
$limit     = $plan['max_services'] === null ? null : (int) $plan['max_services'];

$pending = (int) (Booking::artistStats((int) Auth::id())['pending'] ?? 0);

$percent = $limit === null || $limit === 0 ? 0 : min(100, (int) round(($usedSlots / $limit) * 100));
$isFull  = $limit !== null && $usedSlots >= $limit;

$uri = $_SERVER['REQUEST_URI'] ?? '';
?>
<nav class="side-nav" aria-label="Artist dashboard">
    <p class="side-nav__section">Overview</p>

    <a class="side-nav__link <?= preg_match('#/artist/?($|\?)#', $uri) ? 'is-active' : '' ?>"
       href="<?= e(url('/artist')) ?>">
        <?= icon('grid') ?> Dashboard
    </a>

    <p class="side-nav__section">Work</p>

    <a class="side-nav__link <?= e(active_when('/artist/bookings')) ?>" href="<?= e(url('/artist/bookings')) ?>">
        <?= icon('calendar') ?> Bookings
        <?php if ($pending > 0): ?>
            <span class="side-nav__count"><?= (int) $pending ?></span>
        <?php endif; ?>
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/schedule')) ?>" href="<?= e(url('/artist/schedule')) ?>">
        <?= icon('clock') ?> Schedule
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/services')) ?>" href="<?= e(url('/artist/services')) ?>">
        <?= icon('scissors') ?> Services
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/portfolio')) ?>" href="<?= e(url('/artist/portfolio')) ?>">
        <?= icon('image') ?> Portfolio
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/clients')) ?>" href="<?= e(url('/artist/clients')) ?>">
        <?= icon('users') ?> Clients
    </a>

    <p class="side-nav__section">Growth</p>

    <a class="side-nav__link <?= e(active_when('/artist/analytics')) ?>" href="<?= e(url('/artist/analytics')) ?>">
        <?= icon('chart') ?> Analytics
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/reviews')) ?>" href="<?= e(url('/artist/reviews')) ?>">
        <?= icon('star') ?> Reviews
    </a>

    <a class="side-nav__link <?= e(active_when('/artist/subscription')) ?>" href="<?= e(url('/artist/subscription')) ?>">
        <?= icon('sparkle') ?> Plan &amp; billing
    </a>

    <p class="side-nav__section">Account</p>

    <a class="side-nav__link <?= e(active_when('/artist/profile')) ?>" href="<?= e(url('/artist/profile')) ?>">
        <?= icon('user') ?> Public profile
    </a>

    <a class="side-nav__link <?= e(active_when('/settings')) ?>" href="<?= e(url('/settings')) ?>">
        <?= icon('settings') ?> Settings
    </a>
</nav>

<!-- Plan usage -->
<div class="plan-card">
    <p class="plan-card__name">
        <?= e($plan['name'] ?? 'Starter') ?> plan
        <?php if (!empty($plan['badge'])): ?>
            <span class="plan-badge" style="margin-left:.3rem;"><?= e($plan['badge']) ?></span>
        <?php endif; ?>
    </p>

    <?php if (($plan['subscription_status'] ?? 'none') === 'past_due'): ?>
        <p class="plan-card__meta text-critical">Payment overdue</p>
    <?php elseif (!empty($plan['cancel_at_period_end'])): ?>
        <p class="plan-card__meta">Ends <?= e(date_label($plan['current_period_end'] ?? null, 'M j')) ?></p>
    <?php elseif (!empty($plan['current_period_end'])): ?>
        <p class="plan-card__meta">Renews <?= e(date_label($plan['current_period_end'], 'M j')) ?></p>
    <?php else: ?>
        <p class="plan-card__meta">Free forever</p>
    <?php endif; ?>

    <div class="meter">
        <div class="meter__track">
            <div class="meter__fill <?= $isFull ? 'is-full' : '' ?>" style="width: <?= (int) $percent ?>%;"></div>
        </div>
        <p class="meter__label">
            <span>Active services</span>
            <span><?= (int) $usedSlots ?> / <?= $limit === null ? '∞' : (int) $limit ?></span>
        </p>
    </div>

    <?php if ($limit !== null): ?>
        <a class="btn btn--secondary btn--sm btn--block mt-3" href="<?= e(url('/artist/subscription')) ?>">
            <?= $isFull ? 'Upgrade for more' : 'View plans' ?>
        </a>
    <?php endif; ?>
</div>
