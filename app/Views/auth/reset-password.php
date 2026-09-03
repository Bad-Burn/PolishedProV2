<?php
/** @var string $token */

declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <div class="card" style="max-width: 26rem; margin-inline: auto;">
        <h1 class="heading-lg mb-2">Choose a new password</h1>
        <p class="muted small mb-6">
            Setting a new password signs you out everywhere else.
        </p>

        <form method="post" action="<?= e(url('/reset-password')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="field">
                <label class="field__label" for="password">New password</label>
                <input class="input" type="password" id="password" name="password"
                       autocomplete="new-password" required minlength="10" autofocus
                       <?= has_error('password') ? 'aria-invalid="true"' : '' ?>>

                <div class="strength" data-strength-for="password">
                    <div class="strength__track"><div class="strength__fill"></div></div>
                    <p class="strength__label"></p>
                </div>

                <?= field_error('password') ?>
            </div>

            <div class="field">
                <label class="field__label" for="password_confirmation">Confirm new password</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password" required
                       <?= has_error('password_confirmation') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('password_confirmation') ?>
            </div>

            <button class="btn btn--primary btn--block" type="submit" data-busy-text="Saving…">
                Save new password
            </button>
        </form>
    </div>
</div>
