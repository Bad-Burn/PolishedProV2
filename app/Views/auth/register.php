<?php
/**
 * @var string $role
 * @var bool $openFor
 */

declare(strict_types=1);

layout('app');

$role = in_array($role ?? 'customer', ['customer', 'artist'], true) ? $role : 'customer';
?>
<div class="auth">
    <div class="auth__form">
        <div class="auth__inner">
            <h1 class="display-2 mb-2">Create your account</h1>
            <p class="muted mb-8">Free for clients. Artists get a 14-day trial, no card needed.</p>

            <?php if (!$openFor): ?>
                <div class="alert alert--warning mb-6">
                    <?= icon('alert') ?>
                    <span>New registrations are paused right now. Please check back soon.</span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/register')) ?>" data-once>
                <?= csrf_field() ?>

                <!-- Account type -->
                <fieldset class="field">
                    <legend class="field__label">I want to</legend>
                    <div class="choice-grid">
                        <label class="choice">
                            <input type="radio" name="role" value="customer" <?= $role === 'customer' ? 'checked' : '' ?>>
                            <span class="choice__title">Book appointments</span>
                            <span class="choice__desc">Find artists and manage your bookings.</span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="role" value="artist" <?= $role === 'artist' ? 'checked' : '' ?>>
                            <span class="choice__title">Offer my services</span>
                            <span class="choice__desc">List services and take bookings.</span>
                        </label>
                    </div>
                    <?= field_error('role') ?>
                </fieldset>

                <div class="field">
                    <label class="field__label" for="full_name">Your full name</label>
                    <input class="input" type="text" id="full_name" name="full_name"
                           value="<?= old('full_name') ?>" maxlength="120"
                           autocomplete="name" required
                           <?= has_error('full_name') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('full_name') ?>
                </div>

                <!-- Artist-only fields. Shown always so the form works without
                     JavaScript; the server ignores them for customer sign-ups. -->
                <div class="field">
                    <label class="field__label" for="display_name">
                        Business or working name
                        <span class="optional">— artists only</span>
                    </label>
                    <input class="input" type="text" id="display_name" name="display_name"
                           value="<?= old('display_name') ?>" maxlength="120"
                           placeholder="e.g. Studio Kim — Lashes"
                           <?= has_error('display_name') ? 'aria-invalid="true"' : '' ?>>
                    <p class="field__hint">This is the name clients will see on your profile.</p>
                    <?= field_error('display_name') ?>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="city">
                            City <span class="optional">— artists only</span>
                        </label>
                        <input class="input" type="text" id="city" name="city"
                               value="<?= old('city') ?>" maxlength="90" placeholder="Makati"
                               <?= has_error('city') ? 'aria-invalid="true"' : '' ?>>
                        <?= field_error('city') ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="phone">
                            Mobile <span class="optional">optional</span>
                        </label>
                        <input class="input" type="tel" id="phone" name="phone"
                               value="<?= old('phone') ?>" maxlength="30"
                               autocomplete="tel" placeholder="+63 917 000 0000"
                               <?= has_error('phone') ? 'aria-invalid="true"' : '' ?>>
                        <?= field_error('phone') ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email"
                           value="<?= old('email') ?>" autocomplete="email" required
                           <?= has_error('email') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('email') ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <input class="input" type="password" id="password" name="password"
                           autocomplete="new-password" required minlength="10"
                           <?= has_error('password') ? 'aria-invalid="true"' : '' ?>>

                    <div class="strength" data-strength-for="password">
                        <div class="strength__track"><div class="strength__fill"></div></div>
                        <p class="strength__label"></p>
                    </div>

                    <p class="field__hint">At least 10 characters. A short phrase is easier to remember and harder to guess.</p>
                    <?= field_error('password') ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password_confirmation">Confirm password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                           autocomplete="new-password" required
                           <?= has_error('password_confirmation') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('password_confirmation') ?>
                </div>

                <label class="checkbox mb-6">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>
                        I agree to the <a href="<?= e(url('/terms')) ?>">terms of service</a>
                        and <a href="<?= e(url('/privacy')) ?>">privacy policy</a>.
                    </span>
                </label>
                <?= field_error('terms') ?>

                <button class="btn btn--primary btn--block btn--lg" type="submit"
                        data-busy-text="Creating account…" <?= $openFor ? '' : 'disabled' ?>>
                    Create account
                </button>
            </form>

            <p class="small muted mt-6 text-center">
                Already have an account? <a href="<?= e(url('/login')) ?>">Sign in</a>
            </p>
        </div>
    </div>

    <aside class="auth__aside">
        <p class="eyebrow">Why join</p>
        <blockquote class="auth__quote">
            No commission on your bookings. Your clients stay yours.
        </blockquote>
        <ul class="plan__features">
            <li><?= icon('check') ?><span>14 days free, no card needed</span></li>
            <li><?= icon('check') ?><span>Real calendar, no double bookings</span></li>
            <li><?= icon('check') ?><span>Reviews only from real appointments</span></li>
        </ul>
    </aside>
</div>
