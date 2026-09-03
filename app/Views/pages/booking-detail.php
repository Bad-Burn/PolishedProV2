<?php
/**
 * @var array<string, mixed> $booking
 * @var array<int, array<string, mixed>> $timeline
 * @var array<string, mixed>|null $review
 * @var bool $isCustomer, $isArtist, $canReview, $freeCancel
 */

declare(strict_types=1);

use App\Core\Auth;

layout('app');

$status  = (string) $booking['status'];
$isPast  = strtotime((string) $booking['starts_at']) < time();
$isAdmin = Auth::isAdmin();

$eventLabels = [
    'created'   => 'Booking requested',
    'confirmed' => 'Confirmed by the artist',
    'completed' => 'Marked as completed',
    'cancelled' => 'Cancelled',
    'declined'  => 'Declined by the artist',
    'no_show'   => 'Marked as a no-show',
];
?>
<div class="container container--narrow section--tight">
    <a class="link-back small muted" href="<?= e(url($isArtist ? '/artist/bookings' : '/account')) ?>">
        <?= icon('arrow-left') ?> Back to bookings
    </a>

    <header class="mt-6 mb-8">
        <div class="row row--tight mb-3">
            <?= view_partial('status-badge', ['status' => $status]) ?>
            <span class="badge badge--outline numeric"><?= e($booking['reference']) ?></span>
        </div>

        <h1 class="display-2 mb-2"><?= e($booking['service_name']) ?></h1>
        <p class="lede">
            <?= e(date_label($booking['starts_at'], 'l, F j, Y')) ?>
            at <?= e(time_label($booking['starts_at'])) ?>
        </p>
    </header>

    <div class="grid grid--2 mb-8" style="align-items:start;">
        <!-- Who -->
        <div class="card">
            <p class="eyebrow mb-4"><?= $isArtist ? 'Client' : 'Artist' ?></p>

            <div class="row row--top">
                <?= view_partial('avatar', [
                    'user' => $isArtist
                        ? ['full_name' => $booking['customer_name'], 'avatar_path' => $booking['customer_avatar']]
                        : ['full_name' => $booking['display_name'] ?? $booking['artist_name'], 'avatar_path' => $booking['artist_avatar']],
                    'size' => 'lg',
                ]) ?>

                <div style="min-width:0;">
                    <p class="strong">
                        <?php if ($isArtist): ?>
                            <?= e($booking['customer_name']) ?>
                        <?php else: ?>
                            <a href="<?= e(url('/artists/' . $booking['artist_slug'])) ?>">
                                <?= e($booking['display_name'] ?? $booking['artist_name']) ?>
                            </a>
                        <?php endif; ?>
                    </p>

                    <?php
                    // Contact details are only exposed once a booking is real,
                    // and never on a request the artist has not accepted.
                    $showContact = in_array($status, ['confirmed', 'completed'], true) || $isAdmin;
                    ?>
                    <?php if ($showContact): ?>
                        <p class="small muted">
                            <?= e($isArtist ? $booking['customer_email'] : $booking['artist_email']) ?>
                        </p>
                        <?php $phone = $isArtist ? $booking['customer_phone'] : $booking['artist_phone']; ?>
                        <?php if (!empty($phone)): ?>
                            <p class="small muted"><?= e($phone) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="tiny muted">Contact details appear once the booking is confirmed.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="card">
            <p class="eyebrow mb-4">Details</p>

            <dl>
                <div class="summary-line">
                    <dt>Duration</dt>
                    <dd><?= e(duration_label((int) $booking['duration_min'])) ?></dd>
                </div>
                <div class="summary-line">
                    <dt>Ends around</dt>
                    <dd><?= e(time_label($booking['ends_at'])) ?></dd>
                </div>
                <?php if (!empty($booking['studio_address'])): ?>
                    <div class="summary-line">
                        <dt>Location</dt>
                        <dd><?= e($booking['studio_address']) ?></dd>
                    </div>
                <?php elseif (!empty($booking['city'])): ?>
                    <div class="summary-line">
                        <dt>Area</dt>
                        <dd><?= e(trim(($booking['area'] ?? '') . ', ' . $booking['city'], ', ')) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($isCustomer || $isArtist): ?>
                    <div class="summary-line summary-total">
                        <dt>Price</dt>
                        <dd><?= e(money((float) $booking['price'])) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($isCustomer || $isArtist): ?>
                <p class="tiny muted mt-3">Payable directly to the artist.</p>
            <?php else: ?>
                <?php /* An admin opening someone else's booking: the money is not theirs to see. */ ?>
                <p class="tiny muted mt-3">The price is visible only to the client and the artist.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($booking['notes'])): ?>
        <div class="card mb-8">
            <p class="eyebrow mb-3">Notes from the client</p>
            <p class="muted"><?= e($booking['notes']) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($booking['cancellation_reason'])): ?>
        <div class="alert alert--warning mb-8">
            <?= icon('info') ?>
            <span><strong>Reason given:</strong> <?= e($booking['cancellation_reason']) ?></span>
        </div>
    <?php endif; ?>

    <!-- ------------------------- Actions ------------------------- -->
    <?php
    $canConfirm  = ($isArtist || $isAdmin) && $status === 'pending';
    $canComplete = ($isArtist || $isAdmin) && in_array($status, ['confirmed', 'pending'], true) && $isPast;
    $canCancel   = in_array($status, ['pending', 'confirmed'], true);
    ?>

    <?php if ($canConfirm || $canComplete || $canCancel): ?>
        <div class="card mb-8">
            <p class="eyebrow mb-4">Actions</p>

            <div class="row">
                <?php if ($canConfirm): ?>
                    <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="confirmed">
                        <button class="btn btn--primary" type="submit"><?= icon('check') ?> Confirm booking</button>
                    </form>
                <?php endif; ?>

                <?php if ($canComplete): ?>
                    <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="completed">
                        <button class="btn btn--secondary" type="submit">Mark completed</button>
                    </form>

                    <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="no_show">
                        <button class="btn btn--ghost" type="submit">Client did not show</button>
                    </form>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                    <details class="confirm">
                        <summary class="btn btn--danger-quiet">
                            <?= $canConfirm ? 'Decline' : 'Cancel booking' ?>
                        </summary>

                        <div class="confirm__panel">
                            <p>
                                <?php if (!$freeCancel && $isCustomer): ?>
                                    This appointment is within the free-cancellation window your artist set.
                                    Please message them directly as a courtesy.
                                <?php else: ?>
                                    Let them know why — it saves an awkward follow-up message.
                                <?php endif; ?>
                            </p>

                            <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $canConfirm && $isArtist ? 'declined' : 'cancelled' ?>">

                                <div class="field">
                                    <label class="sr-only" for="reason">Reason</label>
                                    <textarea class="textarea" id="reason" name="reason" rows="2" maxlength="300"
                                              placeholder="Optional reason" style="min-height:4rem;"></textarea>
                                </div>

                                <button class="btn btn--danger btn--block btn--sm" type="submit">
                                    Yes, <?= $canConfirm && $isArtist ? 'decline' : 'cancel' ?> this booking
                                </button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ------------------------- Review ------------------------- -->
    <?php if ($canReview): ?>
        <div class="card mb-8">
            <p class="eyebrow mb-2">How did it go?</p>
            <h2 class="heading-md mb-4">Leave a review</h2>
            <p class="muted small mb-5">Only clients who completed an appointment can review — so yours carries weight.</p>

            <form method="post" action="<?= e(url('/bookings/' . (int) $booking['id'] . '/review')) ?>" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <span class="field__label">Your rating</span>
                    <div class="rating-input">
                        <?php foreach ([5, 4, 3, 2, 1] as $value): ?>
                            <input type="radio" id="rate-<?= $value ?>" name="rating" value="<?= $value ?>" required>
                            <label for="rate-<?= $value ?>"><span class="sr-only"><?= $value ?> stars</span></label>
                        <?php endforeach; ?>
                    </div>
                    <?= field_error('rating') ?>
                </div>

                <div class="field">
                    <label class="field__label" for="comment">Your review <span class="optional">optional</span></label>
                    <textarea class="textarea" id="comment" name="comment" maxlength="1500"
                              placeholder="What was the experience like? Would you go back?"><?= old('comment') ?></textarea>
                    <p class="field__hint"><span data-counter-for="comment"></span></p>
                    <?= field_error('comment') ?>
                </div>

                <button class="btn btn--primary" type="submit" data-busy-text="Posting…">Post review</button>
            </form>
        </div>
    <?php elseif ($review !== null): ?>
        <div class="card mb-8">
            <p class="eyebrow mb-4">Your review</p>
            <?= stars((float) $review['rating']) ?>
            <?php if (!empty($review['comment'])): ?>
                <p class="muted mt-3"><?= e($review['comment']) ?></p>
            <?php endif; ?>
            <?php if (!empty($review['artist_reply'])): ?>
                <div class="review__reply">
                    <p class="tiny eyebrow mb-2">Reply from the artist</p>
                    <p><?= e($review['artist_reply']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ------------------------- Timeline ------------------------- -->
    <div class="card">
        <p class="eyebrow mb-5">History</p>

        <div class="timeline">
            <?php foreach ($timeline as $i => $event): ?>
                <div class="timeline__item <?= $i === count($timeline) - 1 ? 'is-current' : '' ?>">
                    <p class="small strong"><?= e($eventLabels[$event['event']] ?? ucfirst((string) $event['event'])) ?></p>
                    <p class="tiny muted">
                        <?= e(date_label($event['created_at'], 'M j, Y')) ?> at <?= e(time_label($event['created_at'])) ?>
                        <?php if (!empty($event['actor_name'])): ?>
                            &middot; <?= e($event['actor_name']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($event['note'])): ?>
                        <p class="tiny muted mt-2"><?= e($event['note']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
