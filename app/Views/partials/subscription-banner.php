<?php
/**
 * Trial countdown / lapsed notice, shown at the top of every artist page.
 *
 * Rendered from the dashboard layout rather than each view, because the one
 * thing an artist must never be confused about is whether clients can
 * currently find and book them.
 *
 * @var array{state:string, trial_ends_at:?string, days_left:int, is_live:bool, awaiting_approval:bool}|null $access
 */

declare(strict_types=1);

$access = $access ?? null;

if ($access === null || $access['state'] === 'subscribed') {
    return;
}

$warnAt = (int) \App\Core\Config::get('app.subscription.trial_warn_days', 3);
?>
<?php if (!empty($access['awaiting_approval'])): ?>
    <div class="alert alert--info mb-6">
        <?= icon('clock') ?>
        <span>
            <strong>Your profile is being reviewed.</strong>
            Set up your services and hours in the meantime. Your
            <?= e(plural(\App\Models\Subscription::trialDays(), 'day')) ?> free trial starts the day your profile goes live.
        </span>
    </div>
<?php elseif ($access['state'] === 'locked'): ?>
    <div class="alert alert--error mb-6">
        <?= icon('alert') ?>
        <span>
            <strong>Your trial has ended.</strong>
            Your profile is hidden from search and you cannot take new bookings. Existing
            appointments still stand, and nothing you have added has been deleted.
            <a href="<?= e(url('/artist/subscription')) ?>">Choose a plan to go live again</a>.
        </span>
    </div>
<?php elseif ($access['days_left'] <= $warnAt): ?>
    <div class="alert alert--warning mb-6">
        <?= icon('clock') ?>
        <span>
            <strong>
                <?= $access['days_left'] === 1 ? 'Last day of your trial.' : e(plural($access['days_left'], 'day')) . ' left in your trial.' ?>
            </strong>
            After <?= e(date_label($access['trial_ends_at'], 'M j')) ?> your profile comes down
            until you subscribe.
            <a href="<?= e(url('/artist/subscription')) ?>">See the plans</a>.
        </span>
    </div>
<?php else: ?>
    <div class="alert mb-6">
        <?= icon('sparkle') ?>
        <span>
            You are on a free trial — <strong><?= e(plural($access['days_left'], 'day')) ?> left</strong>,
            with full <?= e($currentPlan['name'] ?? 'Studio') ?> features.
            <a href="<?= e(url('/artist/subscription')) ?>">Pick a plan</a> any time to keep going.
        </span>
    </div>
<?php endif; ?>
