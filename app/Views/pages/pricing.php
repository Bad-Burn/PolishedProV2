<?php
/**
 * @var array<int, array<string, mixed>> $plans
 * @var string $cycle
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Models\Subscription;

layout('app');

$currentPlanId = Auth::isArtist() ? (int) (Subscription::planFor((int) Auth::id())['id'] ?? 0) : 0;
?>
<section class="section">
    <div class="container">
        <div class="text-center mb-10">
            <p class="eyebrow mb-3">For artists</p>
            <h1 class="display-1 mb-4">Try it free.<br>Then pay when it pays you back.</h1>
            <p class="lede" style="margin-inline:auto;">
                We never take a commission on your bookings. The subscription is the only thing
                you ever pay us. Every artist starts with a 14-day Premium trial — no card needed to begin.
            </p>
        </div>

        <div class="row" style="justify-content:center; margin-bottom: var(--sp-10);">
            <div class="billing-switch">
                <a class="<?= $cycle === 'monthly' ? 'is-active' : '' ?>"
                   href="<?= e(url('/pricing', ['cycle' => 'monthly'])) ?>">Monthly</a>
                <a class="<?= $cycle === 'yearly' ? 'is-active' : '' ?>"
                   href="<?= e(url('/pricing', ['cycle' => 'yearly'])) ?>">Yearly</a>
            </div>
        </div>

        <div class="plan-grid<?= count($plans) === 1 ? ' plan-grid--single' : '' ?>">
            <?php foreach ($plans as $plan): ?>
                <?= view_partial('plan-card', [
                    'plan'          => $plan,
                    'cycle'         => $cycle,
                    'currentPlanId' => $currentPlanId,
                    'showAction'    => Auth::isArtist(),
                ]) ?>
            <?php endforeach; ?>
        </div>

        <?php if (!Auth::isArtist()): ?>
            <div class="text-center mt-10">
                <a class="btn btn--primary btn--lg" href="<?= e(url('/register', ['as' => 'artist'])) ?>">
                    Create an artist account
                </a>
                <p class="tiny muted mt-3">Your 14-day Premium trial starts with no card needed.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- What Premium includes -->
<section class="section section--sunk">
    <div class="container container--narrow">
        <h2 class="display-2 mb-8">What Premium includes</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">Your services and portfolio</h3>
                <p class="muted">
                    Premium includes up to three active services and six portfolio photos, alongside
                    your booking calendar, client messages, reviews, and standard search placement.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Monthly or yearly billing</h3>
                <p class="muted">
                    Choose the payment cycle that works for you. Cancel from your billing page or
                    PayPal, and keep access through the end of your paid period.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">What it never changes</h3>
                <p class="muted">
                    Your reviews, your rating, your client list, and the commission we take on your
                    bookings — which is zero, on every plan, always.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Questions -->
<section class="section">
    <div class="container container--narrow">
        <h2 class="display-2 mb-8">Common questions</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">What happens if I cancel?</h3>
                <p class="muted">
                    You keep everything until the end of the period you already paid for. After that your
                    profile comes down from search and you stop taking new bookings — appointments already
                    in your calendar still stand. Nothing is deleted, and subscribing again puts your
                    profile straight back where it was.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">How do I pay?</h3>
                <p class="muted">
                    Through PayPal, which handles the card details — they never touch this platform's
                    database. You can cancel from your billing page here or from PayPal directly.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Do you take a cut of my bookings?</h3>
                <p class="muted">
                    No. Clients pay you directly, in whatever way you agree with them. We are not in the
                    middle of that transaction at all.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Can I switch plans later?</h3>
                <p class="muted">
                    Any time, in both directions. There is no contract and no cancellation fee.
                </p>
            </div>
        </div>
    </div>
</section>
