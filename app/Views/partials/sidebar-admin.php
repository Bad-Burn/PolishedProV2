<?php
/**
 * @var int $pendingArtists
 */

declare(strict_types=1);

$pendingArtists = $pendingArtists ?? 0;
$uri = $_SERVER['REQUEST_URI'] ?? '';
?>
<nav class="side-nav" aria-label="Admin console">
    <p class="side-nav__section">Overview</p>

    <a class="side-nav__link <?= preg_match('#/admin/?($|\?)#', $uri) ? 'is-active' : '' ?>"
       href="<?= e(url('/admin')) ?>">
        <?= icon('grid') ?> Dashboard
    </a>

    <p class="side-nav__section">Manage</p>

    <a class="side-nav__link <?= e(active_when('/admin/users')) ?>" href="<?= e(url('/admin/users')) ?>">
        <?= icon('users') ?> Users
        <?php if ($pendingArtists > 0): ?>
            <span class="side-nav__count"><?= (int) $pendingArtists ?></span>
        <?php endif; ?>
    </a>

    <a class="side-nav__link <?= e(active_when('/admin/bookings')) ?>" href="<?= e(url('/admin/bookings')) ?>">
        <?= icon('calendar') ?> Bookings
    </a>

    <a class="side-nav__link <?= e(active_when('/admin/reviews')) ?>" href="<?= e(url('/admin/reviews')) ?>">
        <?= icon('star') ?> Reviews
    </a>

    <p class="side-nav__section">Revenue</p>

    <a class="side-nav__link <?= e(active_when('/admin/subscriptions')) ?>" href="<?= e(url('/admin/subscriptions')) ?>">
        <?= icon('wallet') ?> Subscriptions
    </a>

    <a class="side-nav__link <?= e(active_when('/admin/plans')) ?>" href="<?= e(url('/admin/plans')) ?>">
        <?= icon('sparkle') ?> Plans
    </a>

    <p class="side-nav__section">System</p>

    <a class="side-nav__link <?= e(active_when('/admin/audit-log')) ?>" href="<?= e(url('/admin/audit-log')) ?>">
        <?= icon('shield') ?> Sign-in log
    </a>

    <a class="side-nav__link <?= e(active_when('/admin/settings')) ?>" href="<?= e(url('/admin/settings')) ?>">
        <?= icon('settings') ?> Settings
    </a>

    <p class="side-nav__section">Your account</p>

    <a class="side-nav__link <?= e(active_when('/settings/security')) ?>" href="<?= e(url('/settings/security')) ?>">
        <?= icon('lock') ?> Security
    </a>
</nav>
