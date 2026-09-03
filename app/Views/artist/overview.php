<?php
/**
 * @var array<string, mixed> $stats, $profile, $plan
 * @var array<int, array<string, mixed>> $today, $pending, $upcoming
 * @var array<int, array{label:string, done:bool, url:string}> $checklist
 * @var bool $isApproved
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$outstanding = array_filter($checklist, static fn ($item) => !$item['done']);
?>
<header class="dash__head">
    <div>
        <h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', (string) $currentUser['full_name'])[0]) ?></h1>
        <p>
            <?php if ($today !== []): ?>
                You have <?= e(plural(count($today), 'appointment')) ?> today.
            <?php else: ?>
                Nothing on the books for today.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($isApproved && !empty($profile['slug'])): ?>
        <a class="btn btn--secondary" href="<?= e(url('/artists/' . $profile['slug'])) ?>">
            <?= icon('eye') ?> View public profile
        </a>
    <?php endif; ?>
</header>

<?php if (!$isApproved): ?>
    <div class="alert alert--info mb-8">
        <?= icon('info') ?>
        <span>
            <strong>Your profile is being reviewed.</strong>
            You will not appear in search until an admin approves you — usually within a day.
            Everything below still works, so get your services and hours ready now.
        </span>
    </div>
<?php endif; ?>

<?php if ($outstanding !== []): ?>
    <section class="card mb-8">
        <div class="row row--between mb-4">
            <h2 class="heading-md">Finish setting up</h2>
            <span class="badge"><?= count($checklist) - count($outstanding) ?> of <?= count($checklist) ?> done</span>
        </div>

        <ul style="list-style:none; padding:0; display:grid; gap: var(--sp-3);">
            <?php foreach ($checklist as $item): ?>
                <li class="row row--nowrap" style="gap: var(--sp-3);">
                    <span style="color: <?= $item['done'] ? 'var(--positive)' : 'var(--ink-faint)' ?>; flex-shrink:0;">
                        <?= icon($item['done'] ? 'check-circle' : 'circle') ?>
                    </span>
                    <span class="<?= $item['done'] ? 'muted' : 'strong' ?>" style="flex:1;">
                        <?= e($item['label']) ?>
                    </span>
                    <?php if (!$item['done']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e($item['url']) ?>">Do it</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('bell') ?> Awaiting your reply</p>
        <p class="stat__value"><?= (int) ($stats['pending'] ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('calendar') ?> Upcoming</p>
        <p class="stat__value"><?= (int) ($stats['upcoming'] ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Earned (30 days)</p>
        <p class="stat__value"><?= e(money_compact((float) ($stats['revenue_30d'] ?? 0))) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('star') ?> Rating</p>
        <p class="stat__value">
            <?= (int) ($profile['rating_count'] ?? 0) > 0
                ? e(number_format((float) $profile['rating_avg'], 1))
                : '—' ?>
        </p>
        <p class="stat__delta muted"><?= e(plural((int) ($profile['rating_count'] ?? 0), 'review')) ?></p>
    </div>
</div>

<div class="grid grid--2" style="align-items:start;">
    <!-- Today -->
    <section>
        <div class="row row--between mb-4">
            <h2 class="heading-md">Today</h2>
            <a class="link-arrow small" href="<?= e(url('/artist/bookings')) ?>">All bookings</a>
        </div>

        <?php if ($today === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'clock',
                'title'   => 'Nothing booked today',
                'message' => 'Enjoy the quiet, or share your profile link to fill the gap.',
            ]) ?>
        <?php else: ?>
            <?php foreach ($today as $booking): ?>
                <?= view_partial('booking-item', ['booking' => $booking, 'side' => 'artist']) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- Needs a decision -->
    <section>
        <div class="row row--between mb-4">
            <h2 class="heading-md">Requests</h2>
            <?php if ($pending !== []): ?>
                <a class="link-arrow small" href="<?= e(url('/artist/bookings', ['tab' => 'pending'])) ?>">See all</a>
            <?php endif; ?>
        </div>

        <?php if ($pending === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'check-circle',
                'title'   => 'No pending requests',
                'message' => 'Everything has been answered. Nice.',
            ]) ?>
        <?php else: ?>
            <?php foreach ($pending as $booking): ?>
                <article class="booking-item">
                    <div class="booking-item__when">
                        <div class="booking-item__month"><?= e(date_label($booking['starts_at'], 'M')) ?></div>
                        <div class="booking-item__day"><?= e(date_label($booking['starts_at'], 'j')) ?></div>
                        <div class="booking-item__time"><?= e(time_label($booking['starts_at'])) ?></div>
                    </div>

                    <div style="min-width:0;">
                        <p class="booking-item__title">
                            <a href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>"><?= e($booking['service_name']) ?></a>
                        </p>
                        <p class="booking-item__meta"><?= e($booking['customer_name']) ?></p>
                    </div>

                    <div class="booking-item__side">
                        <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="confirmed">
                            <button class="btn btn--primary btn--sm" type="submit">Confirm</button>
                        </form>
                        <span class="tiny muted"><?= e(money((float) $booking['price'])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
