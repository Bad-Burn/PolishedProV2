<?php
/** @var array<int, array<string, mixed>> $notifications */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-auto');
end_section();

$unread = count(array_filter($notifications, static fn ($n) => $n['read_at'] === null));
?>
<header class="dash__head">
    <div>
        <h1>Notifications</h1>
        <p><?= $unread > 0 ? e(plural($unread, 'unread notification')) : 'You are all caught up.' ?></p>
    </div>

    <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--secondary btn--sm" type="submit">Mark all as read</button>
        </form>
    <?php endif; ?>
</header>

<?php if ($notifications === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'bell',
        'title'   => 'No notifications',
        'message' => 'Booking updates, reviews and account alerts land here.',
    ]) ?>
<?php else: ?>
    <div class="card card--flush">
        <?php foreach ($notifications as $item): ?>
            <div class="row row--top" style="padding: var(--sp-4) var(--sp-5); border-bottom: 1px solid var(--line); gap: var(--sp-4);">
                <span style="width:.5rem; height:.5rem; border-radius:50%; margin-top:.55rem; flex-shrink:0;
                             background: <?= $item['read_at'] === null ? 'var(--accent)' : 'transparent' ?>;"
                      aria-hidden="true"></span>

                <div style="flex:1; min-width:0;">
                    <p class="strong small mb-1">
                        <?php if (!empty($item['link'])): ?>
                            <a href="<?= e($item['link']) ?>" style="color:inherit;"><?= e($item['title']) ?></a>
                        <?php else: ?>
                            <?= e($item['title']) ?>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($item['body'])): ?>
                        <p class="small muted mb-1"><?= e($item['body']) ?></p>
                    <?php endif; ?>

                    <p class="tiny muted"><?= e(relative_time($item['created_at'])) ?></p>
                </div>

                <form method="post" action="<?= e(url('/notifications/' . (int) $item['id'] . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost btn--icon btn--sm" type="submit" aria-label="Remove notification">
                        <?= icon('x') ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
