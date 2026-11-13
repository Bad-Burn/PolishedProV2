<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Url;
use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;

/**
 * The admin console.
 *
 * Every mutating action writes to the audit log, and admins can never silently
 * act as another user — actions are recorded against the admin who took them.
 */
final class AdminController extends Controller
{
    public function dashboard(Request $request): never
    {
        // Opportunistic housekeeping — cheap, and keeps stats honest without
        // needing a cron job on shared hosting.
        Booking::autoCompleteOverdue();
        Subscription::expireLapsed();

        // Volume, not value. The console reports how busy the platform is;
        // what each artist earns from their own appointments stays with them.
        $this->view('admin/dashboard', [
            'title'    => 'Admin console',
            'bookings' => Booking::platformVolumeStats(),
            'trend'    => Booking::platformVolumeTrend(6),
            'counts'   => [
                'customers' => User::countByRole('customer'),
                'artists'   => User::countByRole('artist'),
                'pending'   => User::countPendingArtists(),
            ],
            'mrr'       => Subscription::mrr(),
            'planMix'   => Subscription::planBreakdown(),
            'recentLog' => AuditLog::recent(8, AuditLog::SESSION_ACTIONS),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    // -- Users --------------------------------------------------------------

    public function users(Request $request): never
    {
        $filters = [
            'role'   => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
            'search' => (string) $request->query('q', ''),
            'awaiting_approval' => $request->query('awaiting') === '1',
            'page'   => (int) $request->query('page', 1),
        ];

        $this->view('admin/users', [
            'title'   => 'Users',
            'results' => User::paginate($filters),
            'filters' => $filters,
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    public function approveArtist(Request $request): never
    {
        $userId = $request->intParam('id');
        $user   = User::find($userId);

        if ($user === null || $user['role'] !== 'artist') {
            $this->failure('That artist could not be found.', '/admin/users');
        }

        $fields = ['approved_at' => date('Y-m-d H:i:s')];

        // The trial is for trading, and an artist cannot trade until they are
        // approved. Restart it on first approval so a slow review does not eat
        // the days they were promised.
        if (empty($user['approved_at']) && Subscription::activeFor($userId) === null) {
            $fields['trial_ends_at'] = date('Y-m-d H:i:s', strtotime('+' . Subscription::trialDays() . ' days'));
        }

        User::update($userId, $fields);
        Availability::seedDefaults($userId);

        Notification::push(
            $userId,
            'account.approved',
            'Your profile is live',
            'You now appear in search and clients can book you.',
            Url::to('/artist')
        );

        AuditLog::record('artist.approved', 'user', $userId);
        Logger::security('Artist approved', ['user_id' => $userId, 'by' => Auth::id()]);

        $this->success($user['full_name'] . ' is now live on the platform.', '/admin/users?awaiting=1');
    }

    public function setUserStatus(Request $request): never
    {
        $userId = $request->intParam('id');
        $status = $request->string('status');

        if (!in_array($status, ['active', 'suspended'], true)) {
            $this->failure('Unknown status.', '/admin/users');
        }

        $user = User::find($userId);
        if ($user === null) {
            $this->failure('That user could not be found.', '/admin/users');
        }

        // An admin locking themselves out helps nobody.
        if ($userId === Auth::id()) {
            $this->failure('You cannot suspend your own account.', '/admin/users');
        }

        if ($user['role'] === 'admin' && $status === 'suspended') {
            $this->failure('Admin accounts cannot be suspended from the console.', '/admin/users');
        }

        User::update($userId, ['status' => $status]);

        if ($status === 'suspended') {
            // Kill their remembered devices immediately.
            \App\Core\RememberToken::clearForUser($userId);
        }

        AuditLog::record('user.status_changed', 'user', $userId, ['status' => $status]);
        Logger::security('User status changed', ['user_id' => $userId, 'status' => $status, 'by' => Auth::id()]);

        $this->success(
            $status === 'suspended'
                ? $user['full_name'] . ' has been suspended and signed out.'
                : $user['full_name'] . ' has been reactivated.',
            '/admin/users'
        );
    }

    public function toggleVerified(Request $request): never
    {
        $userId  = $request->intParam('id');
        $profile = Artist::findByUserId($userId);

        if ($profile === null) {
            $this->failure('That artist could not be found.', '/admin/users');
        }

        $verified = (int) $profile['is_verified'] === 1 ? 0 : 1;
        Artist::updateProfile($userId, ['is_verified' => $verified]);

        AuditLog::record('artist.verification_changed', 'user', $userId, ['verified' => $verified]);

        $this->success(
            $verified === 1 ? 'Artist marked as verified.' : 'Verification removed.',
            '/admin/users'
        );
    }

    public function deleteUser(Request $request): never
    {
        $userId = $request->intParam('id');
        $user   = User::find($userId);

        if ($user === null) {
            $this->failure('That user could not be found.', '/admin/users');
        }

        if ($userId === Auth::id()) {
            $this->failure('You cannot delete your own account from here.', '/admin/users');
        }

        if ($user['role'] === 'admin') {
            $this->failure('Admin accounts must be removed from the command line.', '/admin/users');
        }

        // Deleting the row would orphan their PayPal agreement and leave it
        // charging someone who no longer has an account.
        if (!SubscriptionService::cancelAllBilling($userId, 'Account deleted by PolishedPro')) {
            $this->failure(
                'Their PayPal subscription could not be cancelled, so the account was kept. Cancel it in the PayPal dashboard, then try again.',
                '/admin/users'
            );
        }

        \App\Core\Uploader::delete($user['avatar_path'] ?? null);

        // Deleted first so the log survives the cascade.
        AuditLog::record('user.deleted', 'user', $userId, [
            'role'  => $user['role'],
            'email' => $user['email'],
        ]);
        Logger::security('User deleted by admin', ['user_id' => $userId, 'by' => Auth::id()]);

        User::delete($userId);

        $this->success('Account deleted along with its bookings and reviews.', '/admin/users');
    }

    // -- Bookings -----------------------------------------------------------

    public function bookings(Request $request): never
    {
        $status = (string) $request->query('status', '');

        $filters = [
            'status' => $status === '' ? [] : [$status],
            'search' => (string) $request->query('q', ''),
            'from'   => (string) $request->query('from', ''),
            'to'     => (string) $request->query('to', ''),
            'page'   => (int) $request->query('page', 1),
        ];

        $this->view('admin/bookings', [
            'title'   => 'Bookings',
            'results' => Booking::paginate($filters),
            'filters' => $filters,
            'status'  => $status,
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    // -- Reviews ------------------------------------------------------------

    public function reviews(Request $request): never
    {
        $this->view('admin/reviews', [
            'title'   => 'Reviews',
            'reviews' => Review::recent(50),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    public function toggleReviewVisibility(Request $request): never
    {
        $reviewId = $request->intParam('id');

        $review = Database::selectOne('SELECT is_visible FROM reviews WHERE id = :id', ['id' => $reviewId]);
        if ($review === null) {
            $this->failure('That review could not be found.', '/admin/reviews');
        }

        $visible = (int) $review['is_visible'] === 1 ? false : true;
        Review::setVisibility($reviewId, $visible);

        AuditLog::record('review.visibility_changed', 'review', $reviewId, ['visible' => $visible]);

        $this->success(
            $visible ? 'Review is visible again.' : 'Review hidden and excluded from the artist\'s rating.',
            '/admin/reviews'
        );
    }

    // -- Subscriptions ------------------------------------------------------

    public function subscriptions(Request $request): never
    {
        $filters = [
            'status'  => (string) $request->query('status', ''),
            'plan_id' => (int) $request->query('plan_id', 0),
            'page'    => (int) $request->query('page', 1),
        ];

        $this->view('admin/subscriptions', [
            'title'   => 'Subscriptions',
            'results' => Subscription::paginate($filters),
            'filters' => $filters,
            'plans'   => Plan::all(false),
            'mrr'     => Subscription::mrr(),
            'planMix' => Subscription::planBreakdown(),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    /** Grant a plan without PayPal — for comped accounts or offline payment. */
    public function grantPlan(Request $request): never
    {
        $userId = $request->int('user_id');
        $planId = $request->int('plan_id');
        $months = max(1, min(24, $request->int('months', 1)));

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if ($user === null || $user['role'] !== 'artist') {
            $this->failure('Pick an artist account.', '/admin/subscriptions');
        }

        if ($plan === null) {
            $this->failure('Pick a valid plan.', '/admin/subscriptions');
        }

        $subscriptionId = SubscriptionService::grantManual($userId, $planId, $months);

        Notification::push(
            $userId,
            'subscription.granted',
            'Your plan has been upgraded',
            sprintf('You are on the %s plan for the next %s.', $plan['name'], plural($months, 'month')),
            Url::to('/artist/subscription')
        );

        AuditLog::record('subscription.granted_manually', 'subscription', $subscriptionId, [
            'user_id' => $userId,
            'plan'    => $plan['code'],
            'months'  => $months,
        ]);

        $this->success($user['full_name'] . ' is now on the ' . $plan['name'] . ' plan.', '/admin/subscriptions');
    }

    // -- Plans --------------------------------------------------------------

    public function plans(Request $request): never
    {
        $this->view('admin/plans', [
            'title' => 'Plans',
            'plans' => Plan::all(false),
            'paypalReady' => \App\Services\PayPalClient::isConfigured(),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    public function updatePlan(Request $request): never
    {
        $planId = $request->intParam('id');

        if (Plan::find($planId) === null) {
            $this->failure('That plan could not be found.', '/admin/plans');
        }

        $data = $this->validate($request, [
            'name'          => 'required|max:60',
            'tagline'       => 'max:160',
            'price_monthly' => 'required|numeric|gte:0',
            'price_yearly'  => 'required|numeric|gte:0',
            'featured_weight' => 'integer|gte:0|lte:100',
        ], [], '/admin/plans');

        $maxServices  = $request->string('max_services');
        $maxPortfolio = $request->string('max_portfolio_items');

        Plan::update($planId, [
            'name'                => (string) $data['name'],
            'tagline'             => $data['tagline'] ?: null,
            'price_monthly'       => (float) $data['price_monthly'],
            'price_yearly'        => (float) $data['price_yearly'],
            // Blank means unlimited.
            'max_services'        => $maxServices === '' ? null : max(0, (int) $maxServices),
            'max_portfolio_items' => $maxPortfolio === '' ? null : max(0, (int) $maxPortfolio),
            'featured_weight'     => (int) ($data['featured_weight'] ?? 0),
            'has_analytics'       => $request->bool('has_analytics') ? 1 : 0,
            'has_instant_book'    => $request->bool('has_instant_book') ? 1 : 0,
            'is_active'           => $request->bool('is_active') ? 1 : 0,
            'paypal_plan_id_monthly' => $request->string('paypal_plan_id_monthly') ?: null,
            'paypal_plan_id_yearly'  => $request->string('paypal_plan_id_yearly') ?: null,
        ]);

        AuditLog::record('plan.updated', 'plan', $planId);

        $this->success('Plan updated. Existing subscribers keep their current billing amount until renewal.', '/admin/plans');
    }

    // -- Audit log ----------------------------------------------------------

    /**
     * Sign-in and sign-out records.
     *
     * The log table still holds every recorded action, but this screen is
     * deliberately narrowed to session events. Who reached the platform and
     * when is an admin's business; what a client booked, paid or reviewed
     * afterwards is not.
     */
    public function auditLog(Request $request): never
    {
        $action = (string) $request->query('action', '');

        // Ignore an action outside the allowed set rather than showing nothing.
        if (!in_array($action, AuditLog::SESSION_ACTIONS, true)) {
            $action = '';
        }

        $filters = [
            'action'  => $action,
            'actions' => AuditLog::SESSION_ACTIONS,
            'search'  => (string) $request->query('q', ''),
            'page'    => (int) $request->query('page', 1),
        ];

        $this->view('admin/audit-log', [
            'title'   => 'Sign-in activity',
            'results' => AuditLog::paginate($filters),
            'filters' => $filters,
            'actions' => AuditLog::actions(AuditLog::SESSION_ACTIONS),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    // -- Settings -----------------------------------------------------------

    public function settings(Request $request): never
    {
        $this->view('admin/settings', [
            'title'    => 'Platform settings',
            'settings' => Setting::all(),
            'pendingArtists' => User::countPendingArtists(),
        ]);
    }

    public function updateSettings(Request $request): never
    {
        $values = [
            'platform_name'    => mb_substr($request->string('platform_name'), 0, 60),
            'platform_tagline' => mb_substr($request->string('platform_tagline'), 0, 160),
            'support_email'    => mb_substr($request->string('support_email'), 0, 190),
            'allow_registrations'     => $request->bool('allow_registrations') ? '1' : '0',
            'require_artist_approval' => $request->bool('require_artist_approval') ? '1' : '0',
            'maintenance_mode'        => $request->bool('maintenance_mode') ? '1' : '0',
        ];

        if ($values['support_email'] !== '' && filter_var($values['support_email'], FILTER_VALIDATE_EMAIL) === false) {
            $this->failValidation($request, ['support_email' => 'Enter a valid support email address.'], '/admin/settings');
        }

        Setting::putMany($values);
        AuditLog::record('settings.updated', 'settings', null, array_keys($values));

        $this->success('Settings saved.', '/admin/settings');
    }
}
