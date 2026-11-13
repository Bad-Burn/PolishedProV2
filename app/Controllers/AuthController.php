<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Url;
use App\Models\Artist;
use App\Models\Availability;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailVerification;
use App\Services\Emails;

final class AuthController extends Controller
{
    // -- Registration -------------------------------------------------------

    public function showRegister(Request $request): never
    {
        $as = $request->query('as') === 'artist' ? 'artist' : 'customer';

        $this->view('auth/register', [
            'title'   => 'Create your account',
            'role'    => old_raw('role', $as),
            'openFor' => Setting::bool('allow_registrations', true),
        ]);
    }

    public function register(Request $request): never
    {
        if (!Setting::bool('allow_registrations', true)) {
            $this->failure('New registrations are paused at the moment. Please check back soon.', '/register');
        }

        // Registration is throttled per IP: an open sign-up form is the
        // cheapest way to flood a database with junk accounts.
        $throttleKey = 'register:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5, 3600)) {
            $this->failure('Too many accounts created from this connection. Please try again later.', '/register');
        }

        $role = $request->string('role') === 'artist' ? 'artist' : 'customer';

        $rules = [
            'full_name' => 'required|min:2|max:120',
            'email'     => 'required|email',
            'password'  => 'required|password|confirmed',
            'phone'     => 'max:30|phone',
            'terms'     => 'required',
        ];

        if ($role === 'artist') {
            $rules['display_name'] = 'required|min:2|max:120';
            $rules['city']         = 'required|max:90';
        }

        $data = $this->validate($request, $rules, [
            'full_name'    => 'Your name',
            'display_name' => 'Business or working name',
            'terms'        => 'The terms checkbox',
            'city'         => 'City',
        ], '/register?as=' . $role);

        if (User::emailExists((string) $data['email'])) {
            $this->failValidation($request, [
                'email' => 'An account already exists with that email. Try signing in instead.',
            ], '/register?as=' . $role);
        }

        RateLimiter::hit($throttleKey, 3600);

        try {
            $userId = Database::transaction(static function () use ($data, $role): int {
                $userId = User::create([
                    'role'      => $role,
                    'full_name' => (string) $data['full_name'],
                    'email'     => (string) $data['email'],
                    'phone'     => $data['phone'] ?? null,
                    'password'  => (string) $data['password'],
                ]);

                if ($role === 'artist') {
                    Artist::createProfile($userId, (string) $data['display_name']);
                    Artist::updateProfile($userId, ['city' => (string) $data['city']]);
                    Availability::seedDefaults($userId);
                }

                return $userId;
            });
        } catch (\Throwable $e) {
            Logger::error('Registration failed: ' . $e->getMessage());
            $this->failure('We could not create your account. Please try again.', '/register?as=' . $role);
        }

        AuditLog::record('user.registered', 'user', $userId, ['role' => $role], $userId);

        $user = User::find($userId);
        if ($user === null) {
            $this->failure('Your account was created but we could not sign you in. Please sign in manually.', '/login');
        }

        Auth::login($user);
        EmailVerification::send($user);

        if ($role === 'artist') {
            Notification::push(
                $userId,
                'account.welcome',
                'Welcome to PolishedPro',
                'Add your services and set your working hours — your profile goes live once an admin approves it.',
                Url::to('/artist/services')
            );

            $this->success(
                'Account created. Set up your services and schedule while we review your profile.',
                '/artist'
            );
        }

        Notification::push(
            $userId,
            'account.welcome',
            'Welcome to PolishedPro',
            'Browse artists and book your first appointment.',
            Url::to('/artists')
        );

        $this->success('Welcome to PolishedPro. We sent a confirmation link to ' . $user['email'] . ' — confirm it before you book.', '/artists');
    }

    // -- Login --------------------------------------------------------------

    public function showLogin(Request $request): never
    {
        $this->view('auth/login', ['title' => 'Sign in']);
    }

    public function login(Request $request): never
    {
        $email    = strtolower($request->string('email'));
        $password = (string) $request->input('password', '');
        $remember = $request->bool('remember');

        $maxAttempts = (int) Config::get('app.security.login_max_attempts', 5);
        $decay       = (int) Config::get('app.security.login_decay_minutes', 15) * 60;

        // Throttle on both the account and the source address, so neither
        // password spraying nor a targeted attack gets unlimited guesses.
        $ipKey      = 'login:ip:' . $request->ip();
        $accountKey = 'login:acct:' . $email;

        foreach ([$ipKey, $accountKey] as $key) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts, $decay)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                Logger::security('Login blocked by rate limit', ['ip' => $request->ip()]);
                $this->failure(
                    "Too many sign-in attempts. Please wait about {$minutes} minute" . ($minutes === 1 ? '' : 's') . ' and try again.',
                    '/login'
                );
            }
        }

        if ($email === '' || $password === '') {
            $this->failValidation($request, ['email' => 'Enter your email and password.'], '/login');
        }

        $user = User::findByEmail($email);

        // One generic message for every failure mode: a different response for
        // "no such account" would let anyone enumerate registered emails.
        $genericFailure = 'That email and password combination does not match an account.';

        if ($user === null || !Hash::verify($password, (string) $user['password_hash'])) {
            RateLimiter::hit($ipKey, $decay);
            RateLimiter::hit($accountKey, $decay);
            Logger::security('Failed sign-in attempt', ['ip' => $request->ip(), 'email_hash' => substr(hash('sha256', $email), 0, 12)]);

            $this->failValidation($request, ['email' => $genericFailure], '/login');
        }

        if ($user['status'] !== 'active') {
            Logger::security('Sign-in blocked for inactive account', ['user_id' => $user['id']]);
            $this->failure(
                'This account has been suspended. Contact ' . Config::get('app.support_email') . ' if you think this is a mistake.',
                '/login'
            );
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($accountKey);

        User::rehashIfNeeded((int) $user['id'], (string) $user['password_hash'], $password);

        // Two-factor: hold the login in a short-lived pending state rather
        // than establishing a session before the second factor is proven.
        if (User::hasTwoFactor($user)) {
            Session::put('2fa_pending', [
                'user_id'  => (int) $user['id'],
                'remember' => $remember,
                'expires'  => time() + 300,
            ]);
            Csrf::rotate();
            $this->redirect('/login/two-factor');
        }

        Auth::login($user, $remember);
        AuditLog::record('user.signed_in', 'user', (int) $user['id'], [], (int) $user['id']);

        $this->redirectAfterLogin((string) $user['role']);
    }

    public function showTwoFactor(Request $request): never
    {
        if (!$this->pendingTwoFactor()) {
            $this->redirect('/login');
        }

        $this->view('auth/two-factor', ['title' => 'Two-step verification']);
    }

    public function verifyTwoFactor(Request $request): never
    {
        $pending = $this->pendingTwoFactor();
        if ($pending === null) {
            $this->failure('That verification step expired. Please sign in again.', '/login');
        }

        $userId = (int) $pending['user_id'];

        $throttleKey = '2fa:' . $userId;
        if (RateLimiter::tooManyAttempts($throttleKey, 6, 900)) {
            Session::forget('2fa_pending');
            Logger::security('Two-factor rate limit hit', ['user_id' => $userId]);
            $this->failure('Too many incorrect codes. Please sign in again.', '/login');
        }

        $user = User::find($userId);
        if ($user === null || !User::hasTwoFactor($user)) {
            Session::forget('2fa_pending');
            $this->failure('Please sign in again.', '/login');
        }

        $code = preg_replace('/\s+/', '', $request->string('code'));

        $verified = Totp::verify((string) $user['two_factor_secret'], (string) $code)
            || User::consumeRecoveryCode($userId, (string) $code);

        if (!$verified) {
            RateLimiter::hit($throttleKey, 900);
            $this->failValidation($request, ['code' => 'That code is not valid. Check your authenticator app and try again.'], '/login/two-factor');
        }

        RateLimiter::clear($throttleKey);
        Session::forget('2fa_pending');

        Auth::login($user, (bool) $pending['remember']);
        AuditLog::record('user.signed_in', 'user', $userId, ['two_factor' => true], $userId);

        $this->redirectAfterLogin((string) $user['role']);
    }

    /** @return array{user_id:int, remember:bool, expires:int}|null */
    private function pendingTwoFactor(): ?array
    {
        $pending = Session::get('2fa_pending');

        if (!is_array($pending) || ($pending['expires'] ?? 0) < time()) {
            Session::forget('2fa_pending');
            return null;
        }

        return $pending;
    }

    private function redirectAfterLogin(string $role): never
    {
        $intended = Session::pull('intended_url');

        // Only ever follow a same-site relative path.
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            $this->redirect($intended);
        }

        Session::flash('success', 'Signed in.');
        \App\Core\Response::redirect(Auth::homeFor($role));
    }

    public function logout(Request $request): never
    {
        $userId = Auth::id();
        Auth::logout();

        if ($userId !== null) {
            AuditLog::record('user.signed_out', 'user', $userId, [], $userId);
        }

        Session::flash('success', 'You have been signed out.');
        $this->redirect('/');
    }

    // -- Email verification -------------------------------------------------

    /**
     * The link from the verification email. Works signed in or not, since
     * people often open mail on a different device from the one they used.
     */
    public function verifyEmail(Request $request): never
    {
        $userId = EmailVerification::verify((string) $request->query('token', ''));

        if ($userId === null) {
            $this->failure(
                'That confirmation link has expired or is no longer valid. Sign in and we will send you a new one.',
                Auth::check() ? '/settings' : '/login'
            );
        }

        AuditLog::record('user.email_verified', 'user', $userId, [], $userId);

        if (Auth::id() === $userId) {
            Auth::refresh();
            Session::flash('success', 'Thanks — your email address is confirmed.');
            \App\Core\Response::redirect(Auth::homeFor(Auth::role()));
        }

        $this->success('Thanks — your email address is confirmed. Sign in to carry on.', '/login');
    }

    public function resendVerification(Request $request): never
    {
        $user = Auth::user();

        if ($user === null || EmailVerification::isVerified($user)) {
            $this->back();
        }

        EmailVerification::send($user);

        Session::flash('success', 'We sent a new confirmation link to ' . $user['email'] . '.');
        $this->back();
    }

    // -- Password reset -----------------------------------------------------

    public function showForgotPassword(Request $request): never
    {
        $this->view('auth/forgot-password', ['title' => 'Reset your password']);
    }

    public function sendResetLink(Request $request): never
    {
        $email = strtolower($request->string('email'));

        $throttleKey = 'reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5, 900)) {
            $this->failure('Too many reset requests. Please wait a few minutes.', '/forgot-password');
        }
        RateLimiter::hit($throttleKey, 900);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->failValidation($request, ['email' => 'Enter a valid email address.'], '/forgot-password');
        }

        $user = User::findByEmail($email);
        $link = null;

        if ($user !== null && $user['status'] === 'active') {
            $token = PasswordReset::issue((int) $user['id']);
            $link  = Url::absolute('/reset-password', ['token' => $token]);

            Emails::passwordReset($user, $link);

            // The link itself is a credential for the next hour, so it is
            // never logged. In debug mode it is also shown on screen below.
            Logger::info('Password reset link sent', ['user_id' => $user['id']]);

            Notification::push(
                (int) $user['id'],
                'account.reset',
                'Password reset requested',
                'If this was not you, change your password immediately.',
                Url::to('/settings/security')
            );
        }

        // The same response either way, so this form cannot be used to check
        // whether an email is registered.
        Session::flash('success', 'If that email is registered, a reset link is on its way.');

        if ($link !== null && Config::get('app.debug')) {
            Session::flash('info', 'Development mode — reset link: ' . $link);
        }

        $this->redirect('/forgot-password');
    }

    public function showResetPassword(Request $request): never
    {
        $token = (string) $request->query('token', '');

        if (PasswordReset::resolve($token) === null) {
            $this->failure('That reset link has expired or already been used. Request a new one.', '/forgot-password');
        }

        $this->view('auth/reset-password', ['title' => 'Choose a new password', 'token' => $token]);
    }

    public function resetPassword(Request $request): never
    {
        $token = $request->string('token');
        $user  = PasswordReset::resolve($token);

        if ($user === null) {
            $this->failure('That reset link has expired or already been used. Request a new one.', '/forgot-password');
        }

        $data = $this->validate($request, [
            'password' => 'required|password|confirmed',
        ], [], '/reset-password?token=' . urlencode($token));

        User::updatePassword((int) $user['id'], (string) $data['password']);
        PasswordReset::consume((int) $user['__reset_id']);

        AuditLog::record('user.password_reset', 'user', (int) $user['id'], [], (int) $user['id']);
        Logger::security('Password reset completed', ['user_id' => $user['id']]);

        $this->success('Your password has been changed. Please sign in.', '/login');
    }
}
