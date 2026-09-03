<?php
/**
 * @var array<string, string|null> $settings
 * @var int $pendingArtists
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-admin', ['pendingArtists' => $pendingArtists]);
end_section();

$get = static fn (string $key, string $default = ''): string => (string) ($settings[$key] ?? $default);
$on  = static fn (string $key, bool $default = false): bool =>
    in_array($settings[$key] ?? ($default ? '1' : '0'), ['1', 'true', 'yes', 'on'], true);
?>
<header class="dash__head">
    <div>
        <h1>Platform settings</h1>
        <p>Things you can change without touching code.</p>
    </div>
</header>

<form method="post" action="<?= e(url('/admin/settings')) ?>" data-once style="max-width: 42rem;">
    <?= csrf_field() ?>

    <section class="card mb-6">
        <h2 class="heading-md mb-5">Identity</h2>

        <div class="field">
            <label class="field__label" for="platform_name">Platform name</label>
            <input class="input" type="text" id="platform_name" name="platform_name" maxlength="60"
                   value="<?= e($get('platform_name', 'PolishedPro')) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="platform_tagline">Tagline</label>
            <input class="input" type="text" id="platform_tagline" name="platform_tagline" maxlength="160"
                   value="<?= e($get('platform_tagline')) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="support_email">Support email</label>
            <input class="input" type="email" id="support_email" name="support_email" maxlength="190"
                   value="<?= e($get('support_email')) ?>"
                   <?= has_error('support_email') ? 'aria-invalid="true"' : '' ?>>
            <p class="field__hint">Shown to anyone locked out of their account.</p>
            <?= field_error('support_email') ?>
        </div>
    </section>

    <section class="card mb-6">
        <h2 class="heading-md mb-5">Access</h2>

        <div class="stack">
            <label class="checkbox">
                <input type="checkbox" name="allow_registrations" value="1" <?= $on('allow_registrations', true) ? 'checked' : '' ?>>
                <span>
                    <strong>Allow new registrations</strong>
                    Turn this off to pause sign-ups without taking the site down.
                </span>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="require_artist_approval" value="1" <?= $on('require_artist_approval', true) ? 'checked' : '' ?>>
                <span>
                    <strong>Review artists before they go live</strong>
                    Strongly recommended. Without it, anyone who signs up appears in search immediately.
                </span>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="maintenance_mode" value="1" <?= $on('maintenance_mode') ? 'checked' : '' ?>>
                <span>
                    <strong>Maintenance mode</strong>
                    Shows a holding page to everyone except admins.
                </span>
            </label>
        </div>
    </section>

    <button class="btn btn--primary btn--lg" type="submit" data-busy-text="Saving…">Save settings</button>
</form>

<section class="card card--quiet mt-8" style="max-width: 42rem;">
    <h2 class="heading-sm mb-3">Things that live in <code>.env</code>, not here</h2>
    <p class="muted small mb-4">
        Database credentials, the application key, PayPal keys and session timeouts are deliberately
        kept out of the database — a compromised admin session should not be able to read or change them.
    </p>
    <p class="muted small">
        Edit <code>.env</code> on the server and reload. See <code>docs/DEPLOYMENT.md</code>.
    </p>
</section>
