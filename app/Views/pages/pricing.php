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
                you ever pay us. Every artist starts with a 14-day trial on the full Studio plan — no card needed to begin.
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

        <div class="plan-grid">
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
                <p class="tiny muted mt-3">Every plan starts with a 14-day free trial. No card needed to begin.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- What the plans actually change -->
<section class="section section--sunk">
    <div class="container container--narrow">
        <h2 class="display-2 mb-8">What a paid plan actually changes</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">More services you can publish at once</h3>
                <p class="muted">
                    Studio covers fifteen live services. If your menu is longer than that — a colourist
                    with six variations of balayage, plus treatments and consultations — Signature lifts
                    the cap entirely. Services beyond your limit are hidden, never deleted.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Higher placement on the Recommended sort</h3>
                <p class="muted">
                    Artists on the higher tier rank above the rest when someone browses with the default sort.
                    Rating and review count still break ties, and every other sort — highest rated,
                    price, newest — ignores your plan completely. We are not going to pretend a paid
                    listing is the best match when it is not.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">A badge, analytics, and instant booking</h3>
                <p class="muted">
                    A plan badge on your profile, a proper analytics dashboard, and the option to let
                    clients book without waiting for you to confirm.
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
