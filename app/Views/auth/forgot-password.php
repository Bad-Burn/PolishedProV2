<?php
declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <div class="card" style="max-width: 26rem; margin-inline: auto;">
        <h1 class="heading-lg mb-2">Reset your password</h1>
        <p class="muted small mb-6">
            Enter the email on your account and we will send a link to set a new password.
        </p>

        <form method="post" action="<?= e(url('/forgot-password')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email"
                       value="<?= old('email') ?>" autocomplete="username" required autofocus
                       <?= has_error('email') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('email') ?>
            </div>

            <button class="btn btn--primary btn--block" type="submit" data-busy-text="Sending…">
                Send reset link
            </button>
        </form>

        <p class="small muted mt-6 text-center">
            <a href="<?= e(url('/login')) ?>">Back to sign in</a>
        </p>
    </div>
</div>
