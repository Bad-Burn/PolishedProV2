<?php
/**
 * Booking page: date rail, slot grid, and a live summary.
 *
 * @var array<string, mixed> $service, $artist
 * @var string $date
 * @var array<int, array{time:string,label:string,ends:string}> $slots
 * @var array<int, array<string, mixed>> $calendar
 * @var int $freeCancelHours
 */

declare(strict_types=1);

use App\Core\Auth;

layout('app');

$isGuest = Auth::guest();
?>
<div class="container section--tight">
    <a class="link-back small muted" href="<?= e(url('/artists/' . $artist['slug'])) ?>">
        <?= icon('arrow-left') ?> Back to <?= e($artist['display_name']) ?>
    </a>

    <div class="profile-hero" style="padding-block: var(--sp-8);">
        <div>
            <p class="eyebrow mb-3">Book an appointment</p>
            <h1 class="display-2 mb-3"><?= e($service['name']) ?></h1>

            <p class="muted mb-8">
                with <a href="<?= e(url('/artists/' . $artist['slug'])) ?>"><?= e($artist['display_name']) ?></a>
                &middot; <?= e(duration_label((int) $service['duration_min'])) ?>
                &middot; <?= e(money((float) $service['price'])) ?>
            </p>

            <?php if ($isGuest): ?>
                <div class="alert alert--info mb-8">
                    <?= icon('info') ?>
                    <span>
                        <strong>Sign in to finish booking.</strong>
                        You can pick a slot first —
                        <a href="<?= e(url('/login')) ?>">sign in</a> or
                        <a href="<?= e(url('/register')) ?>">create an account</a> when you are ready.
                    </span>
                </div>
            <?php else: ?>
                <?= view_partial('verify-email-notice') ?>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/bookings')) ?>" id="booking-form" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">

                <!-- ------------------------- Date ------------------------- -->
                <section class="mb-8">
                    <h2 class="heading-sm mb-4">1. Pick a date</h2>

                    <div class="date-rail">
                        <?php foreach ($calendar as $day): ?>
                            <a class="date-pill <?= $day['date'] === $date ? 'is-active' : '' ?> <?= $day['has_slots'] ? '' : 'is-full' ?>"
                               href="<?= e(url('/book/' . (int) $service['id'], ['date' => $day['date']])) ?>"
                               <?= $day['has_slots'] ? '' : 'aria-disabled="true" tabindex="-1"' ?>
                               aria-label="<?= e(date_label($day['date'], 'l, F j')) ?><?= $day['has_slots'] ? ', ' . $day['slot_count'] . ' slots' : ', fully booked' ?>">
                                <span class="date-pill__wd"><?= e($day['weekday']) ?></span>
                                <span class="date-pill__day"><?= e($day['day']) ?></span>
                                <?php if ($day['has_slots']): ?>
                                    <span class="date-pill__dot" aria-hidden="true"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- ------------------------- Time ------------------------- -->
                <section class="mb-8">
                    <h2 class="heading-sm mb-1">2. Pick a time</h2>
                    <p class="small muted mb-4"><?= e(date_label($date, 'l, F j')) ?></p>

                    <?php if ($slots === []): ?>
                        <div class="empty">
                            <div class="empty__icon"><?= icon('calendar') ?></div>
                            <h3 class="heading-sm">Nothing free on this date</h3>
                            <p>Either the artist is not working, or the day is fully booked. Try another date above.</p>
                        </div>
                    <?php else: ?>
                        <div class="slot-grid">
                            <?php foreach ($slots as $slot): ?>
                                <label class="slot">
                                    <input type="radio" name="time" value="<?= e($slot['time']) ?>"
                                           data-slot-input data-label="<?= e($slot['label']) ?>" required>
                                    <span><?= e($slot['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="field__hint">Ends around <?= e($slots[0]['ends']) ?> for the first slot shown.</p>
                    <?php endif; ?>
                </section>

                <!-- ------------------------- Notes ------------------------- -->
                <section class="mb-8">
                    <h2 class="heading-sm mb-4">3. Anything they should know?</h2>

                    <div class="field">
                        <label class="sr-only" for="notes">Notes for the artist</label>
                        <textarea class="textarea" id="notes" name="notes" maxlength="500"
                                  placeholder="Inspiration photos, allergies, whether you are coming from another artist…"><?= old('notes') ?></textarea>
                        <p class="field__hint"><span data-counter-for="notes"></span></p>
                        <?= field_error('notes') ?>
                    </div>
                </section>
            </form>
        </div>

        <!-- ------------------------- Summary ------------------------- -->
        <aside class="booking-panel">
            <p class="eyebrow mb-4">Your appointment</p>

            <dl>
                <div class="summary-line">
                    <dt>Service</dt>
                    <dd><?= e($service['name']) ?></dd>
                </div>
                <div class="summary-line">
                    <dt>Artist</dt>
                    <dd><?= e($artist['display_name']) ?></dd>
                </div>
                <div class="summary-line">
                    <dt>Date</dt>
                    <dd><?= e(date_label($date, 'D, M j')) ?></dd>
                </div>
                <div class="summary-line">
                    <dt>Time</dt>
                    <dd data-slot-display>Not selected</dd>
                </div>
                <div class="summary-line">
                    <dt>Duration</dt>
                    <dd><?= e(duration_label((int) $service['duration_min'])) ?></dd>
                </div>

                <div class="summary-line summary-total">
                    <dt>Total</dt>
                    <dd><?= e(money((float) $service['price'])) ?></dd>
                </div>
            </dl>

            <p class="tiny muted mt-3">Paid directly to your artist. No booking fee.</p>

            <?php if ($isGuest): ?>
                <a class="btn btn--primary btn--block btn--lg mt-6" href="<?= e(url('/login')) ?>">
                    Sign in to book
                </a>
            <?php else: ?>
                <button class="btn btn--primary btn--block btn--lg mt-6" type="submit"
                        form="booking-form" data-booking-submit
                        data-busy-text="Sending request…"
                        <?= $slots === [] ? 'disabled' : '' ?>>
                    <?= !empty($artist['instant_book']) ? 'Book instantly' : 'Request this slot' ?>
                </button>

                <p class="tiny muted mt-3 text-center">
                    <?= !empty($artist['instant_book'])
                        ? 'Confirmed straight away.'
                        : 'The artist confirms your request, usually within a few hours.' ?>
                </p>
            <?php endif; ?>

            <div class="divider mt-6" style="padding-top: var(--sp-4);">
                <p class="tiny muted">
                    Free cancellation up to <?= (int) $freeCancelHours ?> hours before your appointment.
                    <?php if (!empty($artist['cancellation_policy'])): ?>
                        <br><br><?= e($artist['cancellation_policy']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </aside>
    </div>
</div>
