<?php
declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <div class="card" style="max-width: 26rem; margin-inline: auto;">
        <div class="text-center mb-6">
            <span class="avatar avatar--lg" style="background: var(--accent-soft); color: var(--accent); margin-inline:auto;">
                <?= icon('shield') ?>
            </span>
            <h1 class="heading-lg mt-4 mb-2">Two-step verification</h1>
            <p class="muted small">Enter the 6-digit code from your authenticator app.</p>
        </div>

        <form method="post" action="<?= e(url('/login/two-factor')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="sr-only" for="code">Verification code</label>
                <input class="input code-input" type="text" id="code" name="code"
                       inputmode="numeric" autocomplete="one-time-code"
                       maxlength="14" placeholder="000000" required autofocus
                       <?= has_error('code') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('code') ?>
                <p class="field__hint text-center">You can also use one of your recovery codes.</p>
            </div>

            <button class="btn btn--primary btn--block btn--lg" type="submit" data-busy-text="Verifying…">
                Verify and sign in
            </button>
        </form>

        <p class="tiny muted mt-6 text-center">
            Lost your device? Contact <a href="mailto:<?= e($supportEmail ?? 'support@polishedpro.test') ?>">support</a>.
        </p>
    </div>
</div>
