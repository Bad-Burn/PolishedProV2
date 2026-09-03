<?php
/**
 * @var array<int, array<string, mixed>> $plans, $history
 * @var array<string, mixed> $current, $plan
 * @var array<string, mixed>|null $subscription
 * @var string $cycle
 * @var array{allowed:bool, used:int, limit:?int, message:?string} $allowance, $portfolioAllowance
 * @var bool $paypalReady, $sandbox
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$status       = $current['subscription_status'] ?? 'none';
$isPaid       = ($current['price_monthly'] ?? 0) > 0;
$endingSoon   = !empty($current['cancel_at_period_end']);
?>
<header class="dash__head">
    <div>
        <h1>Plan &amp; billing</h1>
        <p>What your plan covers, and how to change it.</p>
    </div>
</header>

<?php if ($sandbox && $paypalReady): ?>
    <div class="alert alert--warning mb-6">
        <?= icon('alert') ?>
        <span>
            <strong>PayPal is in sandbox mode.</strong>
            Payments here are simulated and no real money moves. Switch <code>PAYPAL_MODE</code>
            to <code>live</code> in your <code>.env</code> when you are ready to charge properly.
        </span>
    </div>
<?php endif; ?>

<?php if (!$paypalReady): ?>
    <div class="alert alert--info mb-6">
        <?= icon('info') ?>
        <span>
            <strong>Online payments are not switched on yet.</strong>
            Plans are shown for reference. An admin can grant a plan manually in the meantime.
        </span>
    </div>
<?php endif; ?>

<?php if ($status === 'past_due'): ?>
    <div class="alert alert--error mb-6">
        <?= icon('alert') ?>
        <span>
            <strong>Your last payment failed.</strong>
            Update your payment method in PayPal to keep your plan. Your listings stay live until
            the end of the current period.
        </span>
    </div>
<?php endif; ?>

<!-- ------------------------- Current plan ------------------------- -->
<section class="card mb-8">
    <div class="row row--between mb-5">
        <div>
            <p class="eyebrow mb-2">Current plan</p>
            <h2 class="display-2" style="font-size:2rem;">
                <?= e($current['name']) ?>
                <?php if (!empty($current['badge']) && ($current['subscription_status'] ?? '') !== 'trialing'): ?>
                    <span class="plan-badge" style="vertical-align:middle;"><?= e($current['badge']) ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <div class="text-right">
            <?php if ($isPaid && !empty($current['current_period_end'])): ?>
                <p class="small muted"><?= $endingSoon ? 'Access ends' : 'Renews' ?></p>
                <p class="strong"><?= e(date_label($current['current_period_end'], 'F j, Y')) ?></p>
            <?php elseif (($current['subscription_status'] ?? '') === 'trialing'): ?>
                <p class="small muted">Trial ends</p>
                <p class="strong"><?= e(date_label($current['trial_ends_at'] ?? null, 'F j, Y')) ?></p>
            <?php else: ?>
                <span class="badge badge--caution badge--dot">No active plan</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($endingSoon): ?>
        <div class="alert alert--warning mb-5">
            <?= icon('info') ?>
            <span>
                Your plan is set to end on <?= e(date_label($current['current_period_end'], 'F j')) ?>.
                You keep every benefit until then — nothing is switched off early.
            </span>
        </div>
    <?php endif; ?>

    <!-- Usage -->
    <div class="grid grid--2 mb-5">
        <div>
            <div class="meter">
                <div class="meter__track">
                    <div class="meter__fill <?= $allowance['allowed'] ? '' : 'is-full' ?>"
                         style="width: <?= $allowance['limit'] === null ? 8 : (int) min(100, round(($allowance['used'] / max(1, $allowance['limit'])) * 100)) ?>%;"></div>
                </div>
                <p class="meter__label">
                    <span>Active services</span>
                    <span><?= (int) $allowance['used'] ?> / <?= $allowance['limit'] === null ? '∞' : (int) $allowance['limit'] ?></span>
                </p>
            </div>
        </div>

        <div>
            <div class="meter">
                <div class="meter__track">
                    <div class="meter__fill <?= $portfolioAllowance['allowed'] ? '' : 'is-full' ?>"
                         style="width: <?= $portfolioAllowance['limit'] === null ? 8 : (int) min(100, round(($portfolioAllowance['used'] / max(1, $portfolioAllowance['limit'])) * 100)) ?>%;"></div>
                </div>
                <p class="meter__label">
                    <span>Portfolio photos</span>
                    <span><?= (int) $portfolioAllowance['used'] ?> / <?= $portfolioAllowance['limit'] === null ? '∞' : (int) $portfolioAllowance['limit'] ?></span>
                </p>
            </div>
        </div>
    </div>

    <?php if ($isPaid && $subscription !== null): ?>
        <div class="row">
            <?php if ($endingSoon): ?>
                <form method="post" action="<?= e(url('/artist/subscription/resume')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary btn--sm" type="submit">Keep my plan</button>
                </form>
            <?php else: ?>
                <details class="confirm">
                    <summary class="btn btn--ghost btn--sm">Cancel plan</summary>
                    <div class="confirm__panel">
                        <p>
                            You keep everything until
                            <?= e(date_label($current['current_period_end'] ?? null, 'F j')) ?>.
                            After that your profile comes down and you stop taking new bookings — nothing is deleted, and subscribing again puts you straight back.
                        </p>
                        <form method="post" action="<?= e(url('/artist/subscription/cancel')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger btn--block btn--sm" type="submit">Cancel my plan</button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ------------------------- Plan chooser ------------------------- -->
<section class="mb-8">
    <div class="section-head" style="margin-bottom: var(--sp-5);">
        <div>
            <h2 class="heading-lg">Change plan</h2>
            <p>Upgrade or downgrade whenever. No lock-in.</p>
        </div>

        <div class="billing-switch">
            <a class="<?= $cycle === 'monthly' ? 'is-active' : '' ?>"
               href="<?= e(url('/artist/subscription', ['cycle' => 'monthly'])) ?>">Monthly</a>
            <a class="<?= $cycle === 'yearly' ? 'is-active' : '' ?>"
               href="<?= e(url('/artist/subscription', ['cycle' => 'yearly'])) ?>">Yearly</a>
        </div>
    </div>

    <div class="plan-grid">
        <?php foreach ($plans as $planOption): ?>
            <?= view_partial('plan-card', [
                'plan'          => $planOption,
                'cycle'         => $cycle,
                'currentPlanId' => (int) ($current['id'] ?? 0),
                'paypalReady'   => $paypalReady,
            ]) ?>
        <?php endforeach; ?>
    </div>

    <p class="tiny muted mt-5">
        Prices in Philippine pesos. Paid plans bill through PayPal and can be cancelled from this page
        or from your PayPal account at any time. We never take a commission on your bookings —
        the subscription is the only thing you pay us.
    </p>
</section>

<!-- ------------------------- History ------------------------- -->
<?php if ($history !== []): ?>
    <section>
        <h2 class="heading-md mb-4">Billing history</h2>

        <div class="table-wrap">
            <div class="scroll-x">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Plan</th>
                            <th scope="col">Cycle</th>
                            <th scope="col">Started</th>
                            <th scope="col">Period end</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="table__primary" data-label="Plan"><?= e($row['plan_name']) ?></td>
                                <td class="muted" data-label="Cycle"><?= e(ucfirst((string) $row['billing_cycle'])) ?></td>
                                <td class="muted nowrap" data-label="Started"><?= e(date_label($row['started_at'] ?? $row['created_at'])) ?></td>
                                <td class="muted nowrap" data-label="Period end"><?= e(date_label($row['current_period_end'])) ?></td>
                                <td data-label="Status"><?= view_partial('status-badge', ['status' => $row['status']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
