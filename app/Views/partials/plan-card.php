<?php
/**
 * Pricing card, shared by the public pricing page and the artist billing page.
 *
 * @var array<string, mixed> $plan
 * @var string $cycle 'monthly' | 'yearly'
 * @var int $currentPlanId
 * @var bool $showAction
 * @var bool $paypalReady
 * @var array<string, mixed> $paypalPremium
 */

declare(strict_types=1);

use App\Models\Plan;

$plan          = $plan ?? [];
$cycle         = ($cycle ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$currentPlanId = (int) ($currentPlanId ?? 0);
$showAction    = $showAction ?? true;
$paypalReady   = $paypalReady ?? true;
$paypalPremium = $paypalPremium ?? [];

$price     = Plan::priceFor($plan, $cycle);
$isFree    = !Plan::isPaid($plan);
$hasPrice  = $isFree || $price > 0;
$isPremium = ($plan['code'] ?? '') === 'premium';
$isCurrent = $currentPlanId === (int) ($plan['id'] ?? -1);
$saving    = Plan::yearlySavingPercent($plan);

// The middle tier is the honest recommendation for a working artist.
$isRecommended = ($plan['code'] ?? '') === 'studio' && !$isCurrent;
?>
<article class="plan <?= $isCurrent ? 'plan--current' : ($isRecommended ? 'plan--recommended' : '') ?>">
    <?php if ($isCurrent): ?>
        <span class="plan__flag">Your plan</span>
    <?php elseif ($isRecommended): ?>
        <span class="plan__flag">Most popular</span>
    <?php endif; ?>

    <h3 class="plan__name"><?= e($plan['name']) ?></h3>
    <p class="plan__tagline">
        <?php if ($isPremium): ?>PolishedProV2 Monthly Premium &middot; <?php endif; ?>
        <?= e($plan['tagline'] ?? '') ?>
    </p>

    <p class="plan__price">
        <span class="plan__amount"><?= $isFree ? 'Free' : ($hasPrice ? e(money($price, true)) : 'Monthly only') ?></span>
        <?php if (!$isFree && $hasPrice): ?>
            <span class="plan__period">/ <?= $cycle === 'yearly' ? 'year' : 'month' ?></span>
        <?php endif; ?>
    </p>

    <?php if (!$isFree && $cycle === 'yearly' && $saving > 0): ?>
        <p class="tiny text-positive" style="margin-top:-.75rem;">Save <?= (int) $saving ?>% versus paying monthly</p>
    <?php elseif ($isFree): ?>
        <p class="tiny muted" style="margin-top:-.75rem;">Retired plan — no longer available</p>
    <?php endif; ?>

    <ul class="plan__features">
        <?php foreach (($plan['highlights'] ?? []) as $highlight): ?>
            <li><?= icon('check') ?><span><?= e($highlight) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($showAction): ?>
        <?php if ($isCurrent): ?>
            <button class="btn btn--secondary btn--block" type="button" disabled>Current plan</button>
        <?php elseif ($isFree): ?>
            <p class="tiny muted text-center">No longer offered.</p>
        <?php elseif (!$hasPrice): ?>
            <p class="tiny muted text-center">This plan is billed monthly.</p>
        <?php elseif ($isPremium && $cycle === 'monthly' && !empty($paypalPremium['ready'])): ?>
            <div class="paypal-subscription"
                 data-paypal-subscription
                 data-plan-id="<?= e((string) $paypalPremium['plan_id']) ?>"
                 data-customer-reference="<?= e((string) $paypalPremium['customer_reference']) ?>"
                 data-confirm-url="<?= e((string) $paypalPremium['confirm_url']) ?>"
                 <?php if (!empty($paypalPremium['start_time'])): ?>data-start-time="<?= e((string) $paypalPremium['start_time']) ?>"<?php endif; ?>>
                <div class="paypal-subscription__button" aria-label="Subscribe to Premium with PayPal"></div>
                <p class="tiny muted text-center" data-paypal-status aria-live="polite"></p>
            </div>
        <?php elseif ($isPremium && $cycle === 'monthly' && !empty($paypalPremium['sandbox_only'])): ?>
            <button class="btn btn--secondary btn--block" type="button" disabled>Sandbox checkout not configured</button>
        <?php elseif (!$paypalReady): ?>
            <button class="btn btn--secondary btn--block" type="button" disabled>Payments not configured</button>
        <?php else: ?>
            <form method="post" action="<?= e(url('/artist/subscription/checkout')) ?>" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <input type="hidden" name="cycle" value="<?= e($cycle) ?>">
                <button class="btn <?= $isRecommended ? 'btn--primary' : 'btn--secondary' ?> btn--block"
                        type="submit" data-busy-text="Opening PayPal…">
                    Choose <?= e($plan['name']) ?>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</article>
