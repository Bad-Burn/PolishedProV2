<?php
/**
 * @var array<string, mixed> $profile, $user, $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

use App\Models\Subscription;

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$canInstantBook = (int) ($plan['has_instant_book'] ?? 0) === 1;
?>
<header class="dash__head">
    <div>
        <h1>Public profile</h1>
        <p>What clients see before they decide to book you.</p>
    </div>

    <?php if (!empty($user['approved_at']) && !empty($profile['slug'])): ?>
        <a class="btn btn--secondary" href="<?= e(url('/artists/' . $profile['slug'])) ?>">
            <?= icon('eye') ?> Preview
        </a>
    <?php endif; ?>
</header>

<form method="post" action="<?= e(url('/artist/profile')) ?>" enctype="multipart/form-data" data-once style="max-width: 46rem;">
    <?= csrf_field() ?>

    <!-- ------------------------- Identity ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-5">Identity</h2>

        <div class="field">
            <label class="field__label" for="display_name">Display name</label>
            <input class="input" type="text" id="display_name" name="display_name" maxlength="120" required
                   value="<?= old('display_name', $profile['display_name'] ?? '') ?>"
                   <?= has_error('display_name') ? 'aria-invalid="true"' : '' ?>>
            <p class="field__hint">Your business or working name. Changing it does not change your profile link.</p>
            <?= field_error('display_name') ?>
        </div>

        <div class="field">
            <label class="field__label" for="headline">Headline</label>
            <input class="input" type="text" id="headline" name="headline" maxlength="160"
                   placeholder="Gel extensions and hand-painted nail art"
                   value="<?= old('headline', $profile['headline'] ?? '') ?>">
            <p class="field__hint">One line, shown under your name in search results.</p>
            <?= field_error('headline') ?>
        </div>

        <div class="field">
            <label class="field__label" for="bio">About you</label>
            <textarea class="textarea" id="bio" name="bio" maxlength="4000" rows="8"
                      placeholder="How long you have been doing this, what you specialise in, what a first appointment is like."><?= old('bio', $profile['bio'] ?? '') ?></textarea>
            <p class="field__hint">
                <span data-counter-for="bio"></span> — write like you talk. Clients skip marketing copy.
            </p>
            <?= field_error('bio') ?>
        </div>

        <div class="field">
            <label class="field__label" for="cover">Cover photo <span class="optional">optional</span></label>

            <?php if (!empty($profile['cover_path'])): ?>
                <img class="mb-3" data-preview-for="cover"
                     src="<?= e(upload_url($profile['cover_path'])) ?>" alt=""
                     style="width:100%; max-width:22rem; aspect-ratio:4/3; object-fit:cover; border-radius:var(--r-lg); border:1px solid var(--line);">
            <?php endif; ?>

            <input class="input" type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
            <p class="field__hint">Shown on your search result card. Landscape works best.</p>
            <?= field_error('cover') ?>
        </div>
    </section>

    <!-- ------------------------- Location ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-5">Where you work</h2>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="city">City</label>
                <input class="input" type="text" id="city" name="city" maxlength="90" required
                       placeholder="Makati"
                       value="<?= old('city', $profile['city'] ?? '') ?>"
                       <?= has_error('city') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('city') ?>
            </div>

            <div class="field">
                <label class="field__label" for="area">Area <span class="optional">optional</span></label>
                <input class="input" type="text" id="area" name="area" maxlength="120"
                       placeholder="Poblacion"
                       value="<?= old('area', $profile['area'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="studio_address">Studio address <span class="optional">optional</span></label>
            <input class="input" type="text" id="studio_address" name="studio_address" maxlength="255"
                   value="<?= old('studio_address', $profile['studio_address'] ?? '') ?>">
            <p class="field__hint">Only shown to clients once a booking is confirmed — never in public search.</p>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="accepts_home_service" value="1"
                <?= (int) old_raw('accepts_home_service', $profile['accepts_home_service'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>
                <strong>I travel to clients</strong>
                Shown as an option on your profile.
            </span>
        </label>
    </section>

    <!-- ------------------------- Details ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-5">Details</h2>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="years_experience">Years of experience</label>
                <input class="input" type="number" id="years_experience" name="years_experience"
                       min="0" max="70"
                       value="<?= old('years_experience', $profile['years_experience'] ?? 0) ?>">
                <?= field_error('years_experience') ?>
            </div>

            <div class="field">
                <label class="field__label" for="instagram">Instagram <span class="optional">optional</span></label>
                <input class="input" type="text" id="instagram" name="instagram" maxlength="80"
                       placeholder="yourhandle"
                       value="<?= old('instagram', $profile['instagram'] ?? '') ?>"
                       <?= has_error('instagram') ? 'aria-invalid="true"' : '' ?>>
                <p class="field__hint">Handle only, without the @.</p>
                <?= field_error('instagram') ?>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="website">Website <span class="optional">optional</span></label>
            <input class="input" type="url" id="website" name="website" maxlength="190"
                   placeholder="https://example.com"
                   value="<?= old('website', $profile['website'] ?? '') ?>"
                   <?= has_error('website') ? 'aria-invalid="true"' : '' ?>>
            <?= field_error('website') ?>
        </div>

        <div class="field">
            <label class="field__label" for="cancellation_policy">Cancellation policy</label>
            <textarea class="textarea" id="cancellation_policy" name="cancellation_policy" maxlength="500"
                      style="min-height:5rem;"
                      placeholder="Free cancellation up to 24 hours before. Later than that, please message me."><?= old('cancellation_policy', $profile['cancellation_policy'] ?? '') ?></textarea>
            <p class="field__hint">Shown on your profile and on every booking confirmation.</p>
            <?= field_error('cancellation_policy') ?>
        </div>
    </section>

    <!-- ------------------------- Booking mode ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-1">Booking mode</h2>
        <p class="muted small mb-5">
            By default you approve each request. Instant booking confirms straight away, which
            gets you more bookings but leaves less room to vet them.
        </p>

        <?php if ($canInstantBook): ?>
            <label class="checkbox">
                <input type="checkbox" name="instant_book" value="1"
                    <?= (int) old_raw('instant_book', $profile['instant_book'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span>
                    <strong>Turn on instant booking</strong>
                    Requests inside your working hours are confirmed automatically.
                </span>
            </label>
        <?php else: ?>
            <div class="alert alert--info">
                <?= icon('sparkle') ?>
                <span>
                    Instant booking is part of the paid plans.
                    <a href="<?= e(url('/artist/subscription')) ?>">See what is included</a>.
                </span>
            </div>
        <?php endif; ?>
    </section>

    <button class="btn btn--primary btn--lg" type="submit" data-busy-text="Saving…">Save profile</button>
</form>
