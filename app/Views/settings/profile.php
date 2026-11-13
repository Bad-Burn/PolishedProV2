<?php
/** @var array<string, mixed> $user */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-auto');
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Profile</h1>
        <p>How you appear across PolishedPro.</p>
    </div>
</header>

<?= view_partial('verify-email-notice') ?>

<div class="card" style="max-width: 42rem;">
    <form method="post" action="<?= e(url('/settings')) ?>" enctype="multipart/form-data" data-once>
        <?= csrf_field() ?>

        <!-- Photo -->
        <div class="field">
            <span class="field__label">Profile photo</span>

            <div class="row" style="gap: var(--sp-5);">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img class="avatar avatar--xl" data-preview-for="avatar"
                         src="<?= e(upload_url($user['avatar_path'])) ?>" alt="" width="80" height="80">
                <?php else: ?>
                    <span class="avatar avatar--xl" data-preview-for="avatar"
                          style="background-size:cover; background-position:center;"><?= e(initials($user['full_name'])) ?></span>
                <?php endif; ?>

                <div>
                    <input class="input" type="file" id="avatar" name="avatar"
                           accept="image/jpeg,image/png,image/webp">
                    <p class="field__hint">JPG, PNG or WebP. Up to 5&nbsp;MB — we resize and strip metadata on upload.</p>
                    <?= field_error('avatar') ?>

                    <?php if (!empty($user['avatar_path'])): ?>
                        <button class="btn btn--ghost btn--sm mt-2" type="submit"
                                formaction="<?= e(url('/settings/avatar/remove')) ?>" formnovalidate>
                            Remove photo
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr>

        <div class="field">
            <label class="field__label" for="full_name">Full name</label>
            <input class="input" type="text" id="full_name" name="full_name" maxlength="120" required
                   value="<?= old('full_name', $user['full_name']) ?>"
                   <?= has_error('full_name') ? 'aria-invalid="true"' : '' ?>>
            <?= field_error('full_name') ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" required
                       value="<?= old('email', $user['email']) ?>"
                       <?= has_error('email') ? 'aria-invalid="true"' : '' ?>>
                <p class="field__hint">Changing this requires your password.</p>
                <?= field_error('email') ?>
            </div>

            <div class="field">
                <label class="field__label" for="phone">Mobile <span class="optional">optional</span></label>
                <input class="input" type="tel" id="phone" name="phone" maxlength="30"
                       value="<?= old('phone', $user['phone']) ?>"
                       <?= has_error('phone') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('phone') ?>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="current_password">
                Current password <span class="optional">only needed to change your email</span>
            </label>
            <input class="input" type="password" id="current_password" name="current_password"
                   autocomplete="current-password"
                   <?= has_error('current_password') ? 'aria-invalid="true"' : '' ?>>
            <?= field_error('current_password') ?>
        </div>

        <button class="btn btn--primary" type="submit" data-busy-text="Saving…">Save changes</button>
    </form>
</div>

<?php if (($user['role'] ?? '') === 'artist'): ?>
    <div class="card card--quiet mt-6" style="max-width: 42rem;">
        <p class="strong mb-2">Looking for your public profile?</p>
        <p class="muted small mb-4">Your display name, bio, location and portfolio live in your artist settings.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('/artist/profile')) ?>">Edit artist profile</a>
    </div>
<?php endif; ?>
