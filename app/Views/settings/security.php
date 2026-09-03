<?php
/**
 * @var array<string, mixed> $user
 * @var bool $twoFactorOn
 * @var string|null $pendingSecret
 * @var array<int, string>|null $recoveryCodes
 * @var array<int, array<string, mixed>> $rememberedDevices
 */

declare(strict_types=1);

use App\Core\Totp;

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-auto');
end_section();

$issuer = $appName ?? 'PolishedPro';
?>
<header class="dash__head">
    <div>
        <h1>Security</h1>
        <p>Your password, two-step verification and signed-in devices.</p>
    </div>
</header>

<div style="max-width: 42rem;">

    <!-- Freshly generated recovery codes: shown exactly once. -->
    <?php if (!empty($recoveryCodes)): ?>
        <div class="card mb-6" style="border-color: var(--positive);">
            <p class="eyebrow mb-2">Save these now</p>
            <h2 class="heading-md mb-3">Your recovery codes</h2>
            <p class="muted small mb-4">
                Each code works once, and they are the only way back in if you lose your phone.
                We cannot show them again — store them somewhere safe.
            </p>

            <div class="recovery-codes mb-4">
                <?php foreach ($recoveryCodes as $code): ?>
                    <span><?= e($code) ?></span>
                <?php endforeach; ?>
            </div>

            <button class="btn btn--secondary btn--sm" type="button"
                    data-copy="<?= e(implode("\n", $recoveryCodes)) ?>">Copy all codes</button>
        </div>
    <?php endif; ?>

    <!-- ------------------------- Password ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-1">Password</h2>
        <p class="muted small mb-5">
            Changing your password signs you out of every other device.
        </p>

        <form method="post" action="<?= e(url('/settings/password')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="current_password">Current password</label>
                <input class="input" type="password" id="current_password" name="current_password"
                       autocomplete="current-password" required
                       <?= has_error('current_password') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('current_password') ?>
            </div>

            <div class="field">
                <label class="field__label" for="password">New password</label>
                <input class="input" type="password" id="password" name="password"
                       autocomplete="new-password" required minlength="10"
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
                       autocomplete="new-password" required>
                <?= field_error('password_confirmation') ?>
            </div>

            <button class="btn btn--primary" type="submit" data-busy-text="Updating…">Update password</button>
        </form>
    </section>

    <!-- ------------------------- Two-factor ------------------------- -->
    <section class="card mb-6">
        <div class="row row--between mb-1">
            <h2 class="heading-md">Two-step verification</h2>
            <?php if ($twoFactorOn): ?>
                <span class="badge badge--positive badge--dot">On</span>
            <?php else: ?>
                <span class="badge badge--dot">Off</span>
            <?php endif; ?>
        </div>

        <p class="muted small mb-5">
            Adds a 6-digit code from your phone on top of your password. Works with Google
            Authenticator, Authy, 1Password or any standard app — no SMS, no extra cost.
        </p>

        <?php if ($twoFactorOn): ?>
            <details class="confirm">
                <summary class="btn btn--danger-quiet">Turn off two-step verification</summary>
                <div class="confirm__panel">
                    <p>This makes your account easier to break into. Enter your password to confirm.</p>
                    <form method="post" action="<?= e(url('/settings/two-factor/disable')) ?>">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="sr-only" for="disable_password">Password</label>
                            <input class="input" type="password" id="disable_password" name="current_password"
                                   placeholder="Your password" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn--danger btn--block btn--sm" type="submit">Turn it off</button>
                    </form>
                </div>
            </details>

        <?php elseif (!empty($pendingSecret)): ?>
            <div class="totp-setup">
                <div>
                    <p class="eyebrow mb-3">1. Add to your app</p>
                    <p class="small muted mb-3">Enter this key manually, or use the link below on this device.</p>
                    <code class="totp-key mb-3"><?= e(chunk_split($pendingSecret, 4, ' ')) ?></code>

                    <div class="row row--tight">
                        <button class="btn btn--secondary btn--sm" type="button" data-copy="<?= e($pendingSecret) ?>">
                            Copy key
                        </button>
                        <a class="btn btn--ghost btn--sm"
                           href="<?= e(Totp::provisioningUri($pendingSecret, (string) $user['email'], $issuer)) ?>">
                            Open in app
                        </a>
                    </div>
                </div>

                <div>
                    <p class="eyebrow mb-3">2. Confirm it works</p>
                    <form method="post" action="<?= e(url('/settings/two-factor/confirm')) ?>">
                        <?= csrf_field() ?>

                        <div class="field">
                            <label class="field__label" for="code">Enter the current 6-digit code</label>
                            <input class="input code-input" type="text" id="code" name="code"
                                   inputmode="numeric" maxlength="6" placeholder="000000"
                                   autocomplete="one-time-code" required
                                   <?= has_error('code') ? 'aria-invalid="true"' : '' ?>>
                            <?= field_error('code') ?>
                        </div>

                        <button class="btn btn--primary" type="submit">Turn on two-step verification</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <form method="post" action="<?= e(url('/settings/two-factor/start')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--secondary" type="submit">
                    <?= icon('shield') ?> Set up two-step verification
                </button>
            </form>
        <?php endif; ?>
    </section>

    <!-- ------------------------- Devices ------------------------- -->
    <section class="card mb-6">
        <h2 class="heading-md mb-1">Remembered devices</h2>
        <p class="muted small mb-5">
            Devices where you ticked &ldquo;keep me signed in&rdquo;.
            <?php if ($rememberedDevices === []): ?>
                You have none right now.
            <?php endif; ?>
        </p>

        <?php if ($rememberedDevices !== []): ?>
            <ul class="stack-sm mb-5" style="list-style:none; padding:0;">
                <?php foreach ($rememberedDevices as $device): ?>
                    <li class="summary-line">
                        <span class="muted small">Signed in <?= e(relative_time($device['created_at'])) ?></span>
                        <span class="tiny muted">expires <?= e(date_label($device['expires_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" action="<?= e(url('/settings/devices/revoke')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--secondary btn--sm" type="submit">Sign out all remembered devices</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- ------------------------- Danger zone ------------------------- -->
    <?php if (($user['role'] ?? '') !== 'admin'): ?>
        <section class="card" style="border-color: var(--critical);">
            <h2 class="heading-md mb-1">Delete account</h2>
            <p class="muted small mb-5">
                Permanently removes your profile, bookings history and reviews. This cannot be undone,
                and you will need to settle any upcoming appointments first.
            </p>

            <details class="confirm">
                <summary class="btn btn--danger-quiet">Delete my account</summary>
                <div class="confirm__panel">
                    <p>This is permanent. Enter your password to confirm.</p>
                    <form method="post" action="<?= e(url('/settings/delete')) ?>">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="sr-only" for="delete_password">Password</label>
                            <input class="input" type="password" id="delete_password" name="current_password"
                                   placeholder="Your password" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn--danger btn--block btn--sm" type="submit">
                            Permanently delete my account
                        </button>
                    </form>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>
