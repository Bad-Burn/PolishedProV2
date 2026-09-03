<?php
declare(strict_types=1);

layout('app');
?>
<div class="auth">
    <div class="auth__form">
        <div class="auth__inner">
            <h1 class="display-2 mb-2">Welcome back</h1>
            <p class="muted mb-8">Sign in to manage your bookings.</p>

            <form method="post" action="<?= e(url('/login')) ?>" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="field__label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email"
                           value="<?= old('email') ?>"
                           autocomplete="username" required autofocus
                           <?= has_error('email') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('email') ?>
                </div>

                <div class="field">
                    <div class="row row--between" style="margin-bottom: var(--sp-2);">
                        <label class="field__label" for="password" style="margin-bottom:0;">Password</label>
                        <a class="tiny" href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
                    </div>
                    <input class="input" type="password" id="password" name="password"
                           autocomplete="current-password" required
                           <?= has_error('password') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('password') ?>
                </div>

                <label class="checkbox mb-6">
                    <input type="checkbox" name="remember" value="1">
                    <span>Keep me signed in on this device</span>
                </label>

                <button class="btn btn--primary btn--block btn--lg" type="submit" data-busy-text="Signing in…">
                    Sign in
                </button>
            </form>

            <p class="small muted mt-6 text-center">
                New here? <a href="<?= e(url('/register')) ?>">Create an account</a>
            </p>
        </div>
    </div>

    <aside class="auth__aside">
        <p class="eyebrow">PolishedPro</p>
        <blockquote class="auth__quote">
            &ldquo;I stopped losing appointments in my Messenger requests folder.&rdquo;
        </blockquote>
        <p class="auth__cite">Mariel S. — nail artist, Makati</p>
    </aside>
</div>
