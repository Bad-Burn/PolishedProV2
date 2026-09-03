<?php
/**
 * Site header. Adapts to the signed-in role.
 *
 * @var array<string, mixed>|null $currentUser
 * @var int $unreadNotifications
 */

declare(strict_types=1);

use App\Models\Notification;

$user = $currentUser ?? null;
$role = $user['role'] ?? null;
$unread = $unreadNotifications ?? 0;
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            Polished<em>Pro</em>
        </a>

        <button class="nav-toggle" type="button" data-nav-toggle aria-controls="primary-nav" aria-expanded="false" aria-label="Open menu">
            <?= icon('menu') ?>
        </button>

        <nav class="nav" id="primary-nav" aria-label="Main">
            <a class="nav__link <?= e(active_when('/artists')) ?>" href="<?= e(url('/artists')) ?>">Browse artists</a>
            <a class="nav__link <?= e(active_when('/categories')) ?>" href="<?= e(url('/categories')) ?>">Services</a>

            <?php if ($role !== 'artist' && $role !== 'admin'): ?>
                <a class="nav__link <?= e(active_when('/for-artists')) ?>" href="<?= e(url('/for-artists')) ?>">For artists</a>
            <?php endif; ?>

            <?php if ($user === null): ?>
                <span class="nav__divider" aria-hidden="true"></span>
                <a class="nav__link" href="<?= e(url('/login')) ?>">Sign in</a>
                <a class="btn btn--primary btn--sm" href="<?= e(url('/register')) ?>">Create account</a>
            <?php else: ?>
                <span class="nav__divider" aria-hidden="true"></span>

                <!-- Notifications -->
                <details class="dropdown notif-bell">
                    <summary class="btn btn--ghost btn--icon" aria-label="Notifications<?= $unread > 0 ? ', ' . $unread . ' unread' : '' ?>" role="button">
                        <?= icon('bell') ?>
                        <?php if ($unread > 0): ?>
                            <span class="notif-bell__dot"><?= $unread > 9 ? '9+' : (int) $unread ?></span>
                        <?php endif; ?>
                    </summary>

                    <div class="dropdown__panel dropdown__panel--wide">
                        <div class="dropdown__head row row--between">
                            <strong class="small">Notifications</strong>
                            <?php if ($unread > 0): ?>
                                <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="tiny" type="submit">Mark all read</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php $items = Notification::recentFor((int) $user['id'], 6); ?>

                        <?php if ($items === []): ?>
                            <p class="small muted" style="padding: var(--sp-4); text-align:center;">Nothing here yet.</p>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <a class="notif <?= $item['read_at'] === null ? 'is-unread' : '' ?>"
                                   href="<?= e($item['link'] ?: url('/notifications')) ?>">
                                    <div>
                                        <div class="notif__title"><?= e($item['title']) ?></div>
                                        <?php if (!empty($item['body'])): ?>
                                            <div class="notif__body"><?= e(excerpt($item['body'], 90)) ?></div>
                                        <?php endif; ?>
                                        <div class="notif__time"><?= e(relative_time($item['created_at'])) ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                            <div class="dropdown__sep"></div>
                            <a class="dropdown__item" href="<?= e(url('/notifications')) ?>">View all notifications</a>
                        <?php endif; ?>
                    </div>
                </details>

                <!-- Account menu -->
                <details class="dropdown">
                    <summary aria-label="Account menu" role="button">
                        <?= view_partial('avatar', ['user' => $user, 'size' => 'sm']) ?>
                        <?= icon('chevron-down', 'muted') ?>
                    </summary>

                    <div class="dropdown__panel">
                        <div class="dropdown__head">
                            <div class="strong small truncate"><?= e($user['full_name']) ?></div>
                            <div class="tiny muted truncate"><?= e($user['email']) ?></div>
                        </div>

                        <?php if ($role === 'admin'): ?>
                            <a class="dropdown__item" href="<?= e(url('/admin')) ?>"><?= icon('shield') ?> Admin console</a>
                        <?php elseif ($role === 'artist'): ?>
                            <a class="dropdown__item" href="<?= e(url('/artist')) ?>"><?= icon('grid') ?> Dashboard</a>
                            <a class="dropdown__item" href="<?= e(url('/artist/bookings')) ?>"><?= icon('calendar') ?> Bookings</a>
                            <a class="dropdown__item" href="<?= e(url('/artist/subscription')) ?>"><?= icon('sparkle') ?> Plan &amp; billing</a>
                        <?php else: ?>
                            <a class="dropdown__item" href="<?= e(url('/account')) ?>"><?= icon('grid') ?> My bookings</a>
                            <a class="dropdown__item" href="<?= e(url('/account/saved')) ?>"><?= icon('heart') ?> Saved artists</a>
                        <?php endif; ?>

                        <a class="dropdown__item" href="<?= e(url('/settings')) ?>"><?= icon('settings') ?> Settings</a>
                        <a class="dropdown__item" href="<?= e(url('/settings/security')) ?>"><?= icon('lock') ?> Security</a>

                        <div class="dropdown__sep"></div>

                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown__item dropdown__item--danger" type="submit">
                                <?= icon('logout') ?> Sign out
                            </button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>

            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch theme">
                <?= icon('sun', 'icon-sun') ?>
                <?= icon('moon', 'icon-moon') ?>
            </button>
        </nav>
    </div>
</header>
