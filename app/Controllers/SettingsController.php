<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Core\Hash;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Uploader;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Account settings shared by every role: profile, password, two-factor,
 * active sessions and account deletion.
 */
final class SettingsController extends Controller
{
    public function profile(Request $request): never
    {
        $this->view('settings/profile', [
            'title' => 'Profile settings',
            'user'  => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request): never
    {
        $user   = Auth::user();
        $userId = (int) $user['id'];

        $data = $this->validate($request, [
            'full_name' => 'required|min:2|max:120',
            'email'     => 'required|email',
            'phone'     => 'max:30|phone',
        ], ['full_name' => 'Your name'], '/settings');

        $emailChanged = strtolower((string) $data['email']) !== strtolower((string) $user['email']);

        if ($emailChanged) {
            if (User::emailExists((string) $data['email'], $userId)) {
                $this->failValidation($request, ['email' => 'That email is already in use.'], '/settings');
            }

            // Changing the address a reset link goes to is a credential-level
            // change, so it requires the current password.
            $password = (string) $request->input('current_password', '');
            if ($password === '' || !Hash::verify($password, (string) $user['password_hash'])) {
                $this->failValidation($request, [
                    'current_password' => 'Enter your current password to change your email address.',
                ], '/settings');
            }
        }

        $fields = [
            'full_name' => (string) $data['full_name'],
            'phone'     => $data['phone'] ?: null,
        ];

        if ($emailChanged) {
            $fields['email'] = strtolower((string) $data['email']);
            $fields['email_verified_at'] = null;
        }

        // Avatar is optional; a bad upload should not lose the other edits.
        $avatar = $request->file('avatar');
        if ($avatar !== null) {
            try {
                $fields['avatar_path'] = Uploader::image($avatar, 'avatars', 'avatar');
                Uploader::delete($user['avatar_path'] ?? null);
            } catch (ValidationException $e) {
                $this->failValidation($request, $e->errors(), '/settings');
            }
        }

        User::update($userId, $fields);
        AuditLog::record('user.profile_updated', 'user', $userId, ['email_changed' => $emailChanged]);

        $this->success('Your profile has been updated.', '/settings');
    }

    public function removeAvatar(Request $request): never
    {
        $user = Auth::user();

        Uploader::delete($user['avatar_path'] ?? null);
        User::update((int) $user['id'], ['avatar_path' => null]);

        $this->success('Profile photo removed.', '/settings');
    }

    // -- Security -----------------------------------------------------------

    public function security(Request $request): never
    {
        $user = Auth::user();

        $sessions = Database::select(
            'SELECT id, created_at, expires_at FROM remember_tokens WHERE user_id = :u ORDER BY created_at DESC',
            ['u' => (int) $user['id']]
        );

        $this->view('settings/security', [
            'title'          => 'Security',
            'user'           => $user,
            'twoFactorOn'    => User::hasTwoFactor($user),
            'pendingSecret'  => Session::get('2fa_setup_secret'),
            'recoveryCodes'  => Session::pull('2fa_recovery_codes'),
            'rememberedDevices' => $sessions,
        ]);
    }

    public function updatePassword(Request $request): never
    {
        $user   = Auth::user();
        $userId = (int) $user['id'];

        $throttleKey = 'pwchange:' . $userId;
        if (RateLimiter::tooManyAttempts($throttleKey, 5, 900)) {
            $this->failure('Too many attempts. Please wait a few minutes.', '/settings/security');
        }

        $current = (string) $request->input('current_password', '');

        if (!Hash::verify($current, (string) $user['password_hash'])) {
            RateLimiter::hit($throttleKey, 900);
            Logger::security('Failed password change — wrong current password', ['user_id' => $userId]);
            $this->failValidation($request, ['current_password' => 'That is not your current password.'], '/settings/security');
        }

        $data = $this->validate($request, [
            'password' => 'required|password|confirmed',
        ], [], '/settings/security');

        if (Hash::verify((string) $data['password'], (string) $user['password_hash'])) {
            $this->failValidation($request, ['password' => 'Your new password must be different from the current one.'], '/settings/security');
        }

        RateLimiter::clear($throttleKey);

        User::updatePassword($userId, (string) $data['password']);

        // updatePassword invalidates every other session; re-stamp this one so
        // the person who just changed it is not logged out of their own tab.
        Auth::refresh();
        $refreshed = User::find($userId);
        if ($refreshed !== null) {
            Auth::login($refreshed);
        }

        AuditLog::record('user.password_changed', 'user', $userId);
        Logger::security('Password changed', ['user_id' => $userId]);

        $this->success('Your password has been changed. Other devices have been signed out.', '/settings/security');
    }

    /** Step 1: generate a secret and show the setup key. */
    public function startTwoFactor(Request $request): never
    {
        $user = Auth::user();

        if (User::hasTwoFactor($user)) {
            $this->failure('Two-factor authentication is already switched on.', '/settings/security');
        }

        $secret = Totp::generateSecret();
        Session::put('2fa_setup_secret', $secret);

        $this->success('Scan the setup key with your authenticator app, then enter a code to confirm.', '/settings/security');
    }

    /** Step 2: prove the app is working before switching it on. */
    public function confirmTwoFactor(Request $request): never
    {
        $user   = Auth::user();
        $userId = (int) $user['id'];
        $secret = Session::get('2fa_setup_secret');

        if (!is_string($secret) || $secret === '') {
            $this->failure('Start the two-factor setup again.', '/settings/security');
        }

        $code = preg_replace('/\s+/', '', $request->string('code'));

        if (!Totp::verify($secret, (string) $code)) {
            $this->failValidation($request, ['code' => 'That code did not match. Check your app\'s clock and try again.'], '/settings/security');
        }

        $recoveryCodes = Totp::generateRecoveryCodes();

        User::setTwoFactorSecret($userId, $secret);
        User::confirmTwoFactor($userId, $recoveryCodes);

        Session::forget('2fa_setup_secret');
        Session::put('2fa_recovery_codes', $recoveryCodes);

        AuditLog::record('user.2fa_enabled', 'user', $userId);
        Logger::security('Two-factor enabled', ['user_id' => $userId]);

        $this->success('Two-factor authentication is on. Save your recovery codes somewhere safe.', '/settings/security');
    }

    public function disableTwoFactor(Request $request): never
    {
        $user   = Auth::user();
        $userId = (int) $user['id'];

        // Turning off a security control requires proof of identity.
        $password = (string) $request->input('current_password', '');
        if (!Hash::verify($password, (string) $user['password_hash'])) {
            $this->failValidation($request, ['current_password' => 'Enter your password to switch off two-factor authentication.'], '/settings/security');
        }

        User::disableTwoFactor($userId);
        Session::forget('2fa_setup_secret');

        AuditLog::record('user.2fa_disabled', 'user', $userId);
        Logger::security('Two-factor disabled', ['user_id' => $userId]);

        $this->success('Two-factor authentication has been switched off.', '/settings/security');
    }

    /** Revoke every "stay signed in" token for this account. */
    public function revokeDevices(Request $request): never
    {
        $userId = (int) Auth::id();

        \App\Core\RememberToken::clearForUser($userId);
        AuditLog::record('user.devices_revoked', 'user', $userId);

        $this->success('All remembered devices have been signed out.', '/settings/security');
    }

    // -- Account deletion ---------------------------------------------------

    public function deleteAccount(Request $request): never
    {
        $user   = Auth::user();
        $userId = (int) $user['id'];

        if ($user['role'] === 'admin') {
            $this->failure('Admin accounts cannot be deleted from here. Use the command line.', '/settings/security');
        }

        $password = (string) $request->input('current_password', '');
        if (!Hash::verify($password, (string) $user['password_hash'])) {
            $this->failValidation($request, ['current_password' => 'Enter your password to confirm.'], '/settings/security');
        }

        // Refuse while money or commitments are outstanding rather than
        // silently cancelling other people's appointments.
        $upcoming = (int) Database::scalar(
            'SELECT COUNT(*) FROM bookings
              WHERE (customer_id = :u1 OR artist_id = :u2)
                AND status IN ("pending","confirmed") AND ends_at >= NOW()',
            ['u1' => $userId, 'u2' => $userId]
        );

        if ($upcoming > 0) {
            $this->failure(
                'You still have ' . plural($upcoming, 'upcoming appointment') . '. Cancel or complete them first.',
                '/settings/security'
            );
        }

        Uploader::delete($user['avatar_path'] ?? null);

        AuditLog::record('user.deleted_self', 'user', $userId, ['role' => $user['role']], $userId);
        Logger::security('Account self-deleted', ['user_id' => $userId, 'role' => $user['role']]);

        Auth::logout();
        User::delete($userId);

        Session::flash('success', 'Your account has been deleted. We are sorry to see you go.');
        $this->redirect('/');
    }
}
