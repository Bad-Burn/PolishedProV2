<?php
/**
 * One client's record with this artist.
 *
 * Reached from the Clients list. Everything here is scoped to the signed-in
 * artist — it is their own booking history with this person, not a profile of
 * the client on the wider platform.
 *
 * @var array<string, mixed> $client
 * @var array<int, array<string, mixed>> $history
 * @var array<int, array{service_name:string, times:int}> $favourites
 * @var array<int, array<string, mixed>> $reviews
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$visits    = (int) $client['visits'];
$completed = (int) $client['completed'];
$cancelled = (int) $client['cancelled'] + (int) $client['no_shows'];
$upcoming  = (int) $client['upcoming'] + (int) $client['pending'];

// Someone who keeps coming back is worth flagging at a glance.
$isRegular = $completed >= 3;
?>
<a class="link-back small muted mb-5" href="<?= e(url('/artist/clients')) ?>">
    <?= icon('arrow-left') ?> Back to clients
</a>

<header class="dash__head">
    <div class="person">
        <?= view_partial('avatar', ['user' => $client, 'size' => 'xl']) ?>
        <div class="person__text">
            <h1><?= e($client['full_name']) ?></h1>
            <p>
                First booked you <?= e(date_label($client['first_visit'], 'M Y')) ?>
                <?php if ($isRegular): ?>
                    &middot; <span class="badge badge--positive">Regular</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</header>

<!-- ----------------------------- At a glance ----------------------------- -->
<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('calendar') ?> Appointments</p>
        <p class="stat__value"><?= e(number_format($visits)) ?></p>
        <p class="stat__delta muted"><?= (int) $completed ?> completed</p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Total spent</p>
        <p class="stat__value"><?= e(money_compact((float) $client['total_spent'])) ?></p>
        <p class="stat__delta muted">Completed work only</p>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('clock') ?> Upcoming</p>
        <p class="stat__value"><?= (int) $upcoming ?></p>
        <?php if ((int) $client['pending'] > 0): ?>
            <p class="stat__delta text-caution"><?= (int) $client['pending'] ?> awaiting your reply</p>
        <?php endif; ?>
    </div>
    <div class="stat">
        <p class="stat__label"><?= icon('x') ?> Cancelled or no-show</p>
        <p class="stat__value"><?= (int) $cancelled ?></p>
        <?php if ((int) $client['no_shows'] > 0): ?>
            <p class="stat__delta text-caution"><?= e(plural((int) $client['no_shows'], 'no-show')) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--2 mb-8" style="align-items:start;">
    <!-- -------------------------- Contact card -------------------------- -->
    <section class="card">
        <h2 class="heading-md mb-5">Contact</h2>

        <dl>
            <div class="summary-line">
                <dt>Email</dt>
                <dd><a href="mailto:<?= e($client['email']) ?>"><?= e($client['email']) ?></a></dd>
            </div>
            <div class="summary-line">
                <dt>Phone</dt>
                <dd>
                    <?php if (!empty($client['phone'])): ?>
                        <a href="tel:<?= e($client['phone']) ?>"><?= e($client['phone']) ?></a>
                    <?php else: ?>
                        <span class="muted">Not given</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="summary-line">
                <dt>Last visit</dt>
                <dd><?= e(date_label($client['last_visit'], 'M j, Y')) ?></dd>
            </div>
            <div class="summary-line">
                <dt>Account created</dt>
                <dd><?= e(date_label($client['joined_at'], 'M Y')) ?></dd>
            </div>
        </dl>

        <div class="row mt-5">
            <a class="btn btn--secondary btn--sm" href="mailto:<?= e($client['email']) ?>">
                <?= icon('mail') ?> Email
            </a>
            <?php if (!empty($client['phone'])): ?>
                <a class="btn btn--ghost btn--sm" href="tel:<?= e($client['phone']) ?>">
                    <?= icon('phone') ?> Call
                </a>
            <?php endif; ?>
        </div>

        <p class="tiny muted mt-4">
            These details come from the client's own account. They are here so you can reach them
            about their bookings, nothing else.
        </p>
    </section>

    <!-- ------------------------ What they book -------------------------- -->
    <section class="card">
        <h2 class="heading-md mb-1">What they book</h2>
        <p class="muted small mb-5">Completed appointments, most frequent first.</p>

        <?php if ($favourites === []): ?>
            <p class="muted small">Nothing completed yet, so there is no pattern to show.</p>
        <?php else: ?>
            <?php $maxTimes = max(array_map(static fn ($f) => (int) $f['times'], $favourites)); ?>
            <?php foreach ($favourites as $favourite): ?>
                <div class="rank-bar">
                    <span class="rank-bar__name"><?= e($favourite['service_name']) ?></span>
                    <span class="rank-bar__value"><?= (int) $favourite['times'] ?>&times;</span>
                    <span class="rank-bar__track">
                        <span class="rank-bar__fill"
                              style="width: <?= (int) round(((int) $favourite['times'] / $maxTimes) * 100) ?>%;"></span>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<!-- --------------------------- Booking history --------------------------- -->
<section class="mb-8">
    <div class="section-head" style="margin-bottom: var(--sp-4);">
        <h2 class="heading-md">Booking history</h2>
        <span class="muted small"><?= e(plural($visits, 'appointment')) ?></span>
    </div>

    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Service</th>
                        <th scope="col">Reference</th>
                        <th scope="col" class="table__num">Price</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $booking): ?>
                        <tr>
                            <td class="nowrap" data-label="When">
                                <p><?= e(date_label($booking['starts_at'], 'M j, Y')) ?></p>
                                <p class="table__sub"><?= e(time_label($booking['starts_at'])) ?></p>
                            </td>
                            <td data-label="Service">
                                <p class="table__primary"><?= e($booking['service_name']) ?></p>
                                <p class="table__sub"><?= e(duration_label((int) $booking['duration_min'])) ?></p>
                            </td>
                            <td class="numeric table__sub nowrap" data-label="Reference"><?= e($booking['reference']) ?></td>
                            <td class="table__num" data-label="Price"><?= e(money((float) $booking['price'])) ?></td>
                            <td data-label="Status"><?= view_partial('status-badge', ['status' => $booking['status']]) ?></td>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm"
                                   href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>">Open</a>
                            </td>
                        </tr>

                        <?php if (!empty($booking['notes'])): ?>
                            <tr>
                                <td colspan="6" class="table__sub" style="padding-top:0;">
                                    <span class="muted"><?= icon('info') ?> Note:</span>
                                    <?= e($booking['notes']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ------------------------- Reviews they left --------------------------- -->
<?php if ($reviews !== []): ?>
    <section class="card">
        <h2 class="heading-md mb-1">What they said about you</h2>
        <p class="muted small mb-5"><?= e(plural(count($reviews), 'review')) ?> from this client.</p>

        <?php foreach ($reviews as $review): ?>
            <?= view_partial('review', [
                'review' => $review + [
                    'customer_name'   => $client['full_name'],
                    'customer_avatar' => $client['avatar_path'],
                ],
            ]) ?>
        <?php endforeach; ?>

        <a class="link-arrow small mt-4" href="<?= e(url('/artist/reviews')) ?>">Manage all reviews</a>
    </section>
<?php endif; ?>
