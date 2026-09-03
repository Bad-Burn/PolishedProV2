<?php
/**
 * Customer dashboard navigation.
 *
 * @var int $savedCount
 */

declare(strict_types=1);

$savedCount = $savedCount ?? 0;
?>
<nav class="side-nav" aria-label="Account">
    <p class="side-nav__section">Bookings</p>

    <a class="side-nav__link <?= e(active_when('/account') === 'is-active' && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/account/') ? 'is-active' : '') ?>"
       href="<?= e(url('/account')) ?>">
        <?= icon('calendar') ?> My bookings
    </a>

    <a class="side-nav__link <?= e(active_when('/account/saved')) ?>" href="<?= e(url('/account/saved')) ?>">
        <?= icon('heart') ?> Saved artists
        <?php if ($savedCount > 0): ?>
            <span class="side-nav__count side-nav__count--quiet"><?= (int) $savedCount ?></span>
        <?php endif; ?>
    </a>

    <a class="side-nav__link <?= e(active_when('/account/reviews')) ?>" href="<?= e(url('/account/reviews')) ?>">
        <?= icon('star') ?> My reviews
    </a>

    <p class="side-nav__section">Discover</p>

    <a class="side-nav__link" href="<?= e(url('/artists')) ?>">
        <?= icon('search') ?> Browse artists
    </a>

    <p class="side-nav__section">Account</p>

    <a class="side-nav__link <?= e(active_when('/notifications')) ?>" href="<?= e(url('/notifications')) ?>">
        <?= icon('bell') ?> Notifications
    </a>

    <a class="side-nav__link <?= e(active_when('/settings') === 'is-active' && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/security') ? 'is-active' : '') ?>"
       href="<?= e(url('/settings')) ?>">
        <?= icon('user') ?> Profile
    </a>

    <a class="side-nav__link <?= e(active_when('/settings/security')) ?>" href="<?= e(url('/settings/security')) ?>">
        <?= icon('lock') ?> Security
    </a>
</nav>
