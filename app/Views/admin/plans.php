<?php
/**
 * @var array<int, array<string, mixed>> $plans
 * @var bool $paypalReady
 * @var int $pendingArtists
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-admin', ['pendingArtists' => $pendingArtists]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Plans</h1>
        <p>What each tier costs and what it unlocks.</p>
    </div>
</header>

<?php if (!$paypalReady): ?>
    <div class="alert alert--info mb-6">
        <?= icon('info') ?>
        <span>
            <strong>PayPal is not configured.</strong>
            Add <code>PAYPAL_CLIENT_ID</code> and <code>PAYPAL_CLIENT_SECRET</code> to your
            <code>.env</code>, then run <code>php bin/paypal-plans.php</code> to create the
            billing plans and fill in the ids below automatically.
        </span>
    </div>
<?php endif; ?>

<div class="alert alert--warning mb-8">
    <?= icon('alert') ?>
    <span>
        Changing a price here updates what <em>new</em> subscribers pay. Existing PayPal
        subscriptions keep billing at their agreed amount until they are cancelled and re-created —
        that is PayPal's rule, not something this app can override.
    </span>
</div>

<?php foreach ($plans as $plan): ?>
    <section class="card mb-6">
        <div class="row row--between mb-5">
            <div>
                <h2 class="heading-md">
                    <?= e($plan['name']) ?>
                    <span class="badge badge--outline"><?= e($plan['code']) ?></span>
                </h2>
                <p class="muted small mt-1"><?= e($plan['tagline'] ?? '') ?></p>
            </div>

            <?php if ((int) $plan['is_active'] === 1): ?>
                <span class="badge badge--positive badge--dot">Active</span>
            <?php else: ?>
                <span class="badge badge--dot">Hidden</span>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/admin/plans/' . (int) $plan['id'])) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="name-<?= (int) $plan['id'] ?>">Name</label>
                    <input class="input" type="text" id="name-<?= (int) $plan['id'] ?>" name="name"
                           maxlength="60" required value="<?= e($plan['name']) ?>">
                </div>

                <div class="field">
                    <label class="field__label" for="tagline-<?= (int) $plan['id'] ?>">Tagline</label>
                    <input class="input" type="text" id="tagline-<?= (int) $plan['id'] ?>" name="tagline"
                           maxlength="160" value="<?= e($plan['tagline'] ?? '') ?>">
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="pm-<?= (int) $plan['id'] ?>">Monthly price</label>
                    <div class="input-prefix">
                        <span class="input-prefix__label">₱</span>
                        <input class="input" type="number" id="pm-<?= (int) $plan['id'] ?>" name="price_monthly"
                               min="0" step="0.01" required value="<?= e((string) $plan['price_monthly']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="py-<?= (int) $plan['id'] ?>">Yearly price</label>
                    <div class="input-prefix">
                        <span class="input-prefix__label">₱</span>
                        <input class="input" type="number" id="py-<?= (int) $plan['id'] ?>" name="price_yearly"
                               min="0" step="0.01" required value="<?= e((string) $plan['price_yearly']) ?>">
                    </div>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="ms-<?= (int) $plan['id'] ?>">Max active services</label>
                    <input class="input" type="number" id="ms-<?= (int) $plan['id'] ?>" name="max_services"
                           min="0" placeholder="Leave blank for unlimited"
                           value="<?= $plan['max_services'] === null ? '' : (int) $plan['max_services'] ?>">
                    <p class="field__hint">Blank means unlimited.</p>
                </div>

                <div class="field">
                    <label class="field__label" for="mp-<?= (int) $plan['id'] ?>">Max portfolio photos</label>
                    <input class="input" type="number" id="mp-<?= (int) $plan['id'] ?>" name="max_portfolio_items"
                           min="0" placeholder="Leave blank for unlimited"
                           value="<?= $plan['max_portfolio_items'] === null ? '' : (int) $plan['max_portfolio_items'] ?>">
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="fw-<?= (int) $plan['id'] ?>">Search placement weight</label>
                <input class="input" type="number" id="fw-<?= (int) $plan['id'] ?>" name="featured_weight"
                       min="0" max="100" value="<?= (int) $plan['featured_weight'] ?>">
                <p class="field__hint">
                    Higher wins on the &ldquo;Recommended&rdquo; sort. Rating and review count still break ties,
                    and every other sort order ignores this entirely.
                </p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="ppm-<?= (int) $plan['id'] ?>">PayPal plan ID (monthly)</label>
                    <input class="input" type="text" id="ppm-<?= (int) $plan['id'] ?>" name="paypal_plan_id_monthly"
                           maxlength="60" placeholder="P-XXXXXXXXXXXX"
                           value="<?= e($plan['paypal_plan_id_monthly'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field__label" for="ppy-<?= (int) $plan['id'] ?>">PayPal plan ID (yearly)</label>
                    <input class="input" type="text" id="ppy-<?= (int) $plan['id'] ?>" name="paypal_plan_id_yearly"
                           maxlength="60" placeholder="P-XXXXXXXXXXXX"
                           value="<?= e($plan['paypal_plan_id_yearly'] ?? '') ?>">
                </div>
            </div>

            <div class="stack-sm mb-5">
                <label class="checkbox">
                    <input type="checkbox" name="has_analytics" value="1" <?= (int) $plan['has_analytics'] === 1 ? 'checked' : '' ?>>
                    <span>Includes the analytics dashboard</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="has_instant_book" value="1" <?= (int) $plan['has_instant_book'] === 1 ? 'checked' : '' ?>>
                    <span>Allows instant booking</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $plan['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Show this plan to artists</span>
                </label>
            </div>

            <button class="btn btn--primary" type="submit">Save <?= e($plan['name']) ?></button>
        </form>
    </section>
<?php endforeach; ?>
