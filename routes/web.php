<?php

declare(strict_types=1);

/**
 * Route table.
 *
 * Authorisation lives here, not in the controllers, so the whole access model
 * can be read on one screen. Middleware runs in order, left to right.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\AdminController;
use App\Controllers\Artist\DashboardController as ArtistDashboard;
use App\Controllers\Artist\ScheduleController;
use App\Controllers\Artist\ServiceController;
use App\Controllers\Artist\SubscriptionController;
use App\Controllers\ArtistDirectoryController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\SettingsController;
use App\Controllers\Webhook\PayPalController;

// ---------------------------------------------------------------------------
// Public
// ---------------------------------------------------------------------------

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/for-artists', [HomeController::class, 'forArtists'])->name('for-artists');
$router->get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
$router->get('/categories', [HomeController::class, 'categories'])->name('categories');

$router->get('/artists', [ArtistDirectoryController::class, 'index'])->name('artists');
$router->get('/artists/{slug}', [ArtistDirectoryController::class, 'show'])->name('artist.show');
$router->get('/artists/{slug}/reviews', [ArtistDirectoryController::class, 'reviews'])->name('artist.reviews');
$router->get('/artists/{slug}/portfolio', [ArtistDirectoryController::class, 'portfolio'])->name('artist.portfolio.public');

// Static content pages.
$router->get('/{slug:help|terms|privacy}', [HomeController::class, 'page'])->name('page');

// For search engines.
$router->get('/robots.txt', [HomeController::class, 'robots']);
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);

// ---------------------------------------------------------------------------
// Guest-only authentication
// ---------------------------------------------------------------------------

$router->group('/', ['guest', 'nocache'], function ($router): void {
    $router->get('/register', [AuthController::class, 'showRegister'])->name('register');
    $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
    $router->get('/login/two-factor', [AuthController::class, 'showTwoFactor'])->name('login.2fa');
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
    $router->get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
});

// Write endpoints are throttled per IP on top of the app-level limiter.
$router->group('/', ['guest', 'throttle:20,10'], function ($router): void {
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/login/two-factor', [AuthController::class, 'verifyTwoFactor']);
    $router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

$router->post('/logout', [AuthController::class, 'logout'])->name('logout');

// Email confirmation: the link works signed in or out; resending needs a session.
$router->get('/verify-email', [AuthController::class, 'verifyEmail'])->name('email.verify');
$router->group('/', ['auth', 'throttle:3,15'], function ($router): void {
    $router->post('/verify-email/resend', [AuthController::class, 'resendVerification']);
});

// ---------------------------------------------------------------------------
// Booking (browsing is public, committing is not)
// ---------------------------------------------------------------------------

$router->get('/book/{service}', [BookingController::class, 'create'])->name('book');

// Booking requests hold an artist's time, so they are throttled on top of the
// per-account cap on open requests in BookingController::store().
$router->group('/', ['auth', 'throttle:10,10'], function ($router): void {
    $router->post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
});

$router->group('/', ['auth', 'nocache'], function ($router): void {
    $router->get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
    $router->post('/bookings/{id}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    $router->post('/bookings/{id}/review', [BookingController::class, 'review'])->name('bookings.review');
});

// Saving an artist needs a session but answers JSON, so it sits on its own.
$router->post('/artists/{id}/favorite', [ArtistDirectoryController::class, 'toggleFavorite'])->name('artist.favorite');

// ---------------------------------------------------------------------------
// Notifications (any signed-in role)
// ---------------------------------------------------------------------------

$router->group('/notifications', ['auth', 'nocache'], function ($router): void {
    $router->get('/', [NotificationController::class, 'index'])->name('notifications');
    $router->post('/read-all', [NotificationController::class, 'markAllRead']);
    $router->post('/{id}/read', [NotificationController::class, 'markRead']);
    $router->post('/{id}/delete', [NotificationController::class, 'destroy']);
});

// ---------------------------------------------------------------------------
// Account settings (any signed-in role)
// ---------------------------------------------------------------------------

$router->group('/settings', ['auth', 'nocache'], function ($router): void {
    $router->get('/', [SettingsController::class, 'profile'])->name('settings');
    $router->post('/', [SettingsController::class, 'updateProfile']);
    $router->post('/avatar/remove', [SettingsController::class, 'removeAvatar']);

    $router->get('/security', [SettingsController::class, 'security'])->name('settings.security');
    $router->post('/password', [SettingsController::class, 'updatePassword']);

    $router->post('/two-factor/start', [SettingsController::class, 'startTwoFactor']);
    $router->post('/two-factor/confirm', [SettingsController::class, 'confirmTwoFactor']);
    $router->post('/two-factor/disable', [SettingsController::class, 'disableTwoFactor']);

    $router->post('/devices/revoke', [SettingsController::class, 'revokeDevices']);
    $router->post('/delete', [SettingsController::class, 'deleteAccount']);
});

// ---------------------------------------------------------------------------
// Customer area
// ---------------------------------------------------------------------------

$router->group('/account', ['auth', 'role:customer', 'nocache'], function ($router): void {
    $router->get('/', [CustomerDashboard::class, 'index'])->name('account');
    $router->get('/saved', [CustomerDashboard::class, 'saved'])->name('account.saved');
    $router->get('/reviews', [CustomerDashboard::class, 'reviews'])->name('account.reviews');
});

// ---------------------------------------------------------------------------
// Artist area
// ---------------------------------------------------------------------------

$router->group('/artist', ['auth', 'role:artist', 'subscription', 'nocache'], function ($router): void {
    $router->get('/', [ArtistDashboard::class, 'index'])->name('artist.dashboard');

    $router->get('/bookings', [ArtistDashboard::class, 'bookings'])->name('artist.bookings');
    $router->get('/clients', [ArtistDashboard::class, 'clients'])->name('artist.clients');
    $router->get('/clients/{id}', [ArtistDashboard::class, 'client'])->name('artist.client');
    $router->get('/analytics', [ArtistDashboard::class, 'analytics'])->name('artist.analytics');

    $router->get('/reviews', [ArtistDashboard::class, 'reviews'])->name('artist.reviews.manage');
    $router->post('/reviews/{id}/reply', [ArtistDashboard::class, 'replyToReview']);

    $router->get('/profile', [ArtistDashboard::class, 'profile'])->name('artist.profile');
    $router->post('/profile', [ArtistDashboard::class, 'updateProfile']);

    // Services
    $router->get('/services', [ServiceController::class, 'index'])->name('artist.services');
    $router->post('/services', [ServiceController::class, 'store']);
    $router->post('/services/{id}', [ServiceController::class, 'update']);
    $router->post('/services/{id}/toggle', [ServiceController::class, 'toggle']);
    $router->post('/services/{id}/delete', [ServiceController::class, 'destroy']);

    // Portfolio
    $router->get('/portfolio', [ServiceController::class, 'portfolio'])->name('artist.portfolio');
    $router->post('/portfolio', [ServiceController::class, 'storePortfolio']);
    $router->post('/portfolio/{id}/delete', [ServiceController::class, 'destroyPortfolio']);

    // Schedule
    $router->get('/schedule', [ScheduleController::class, 'index'])->name('artist.schedule');
    $router->post('/schedule', [ScheduleController::class, 'updateWeek']);
    $router->post('/schedule/blocks', [ScheduleController::class, 'addBlock']);
    $router->post('/schedule/blocks/{id}/delete', [ScheduleController::class, 'deleteBlock']);

    // Billing
    $router->get('/subscription', [SubscriptionController::class, 'index'])->name('artist.subscription');
    $router->post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
    $router->get('/subscription/return', [SubscriptionController::class, 'returned']);
    $router->get('/subscription/cancel', [SubscriptionController::class, 'cancelled']);
    $router->post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    $router->post('/subscription/resume', [SubscriptionController::class, 'resume']);
});

// ---------------------------------------------------------------------------
// Admin area
// ---------------------------------------------------------------------------

$router->group('/admin', ['auth', 'role:admin', 'nocache'], function ($router): void {
    $router->get('/', [AdminController::class, 'dashboard'])->name('admin');

    $router->get('/users', [AdminController::class, 'users'])->name('admin.users');
    $router->post('/users/{id}/approve', [AdminController::class, 'approveArtist']);
    $router->post('/users/{id}/status', [AdminController::class, 'setUserStatus']);
    $router->post('/users/{id}/verify', [AdminController::class, 'toggleVerified']);
    $router->post('/users/{id}/delete', [AdminController::class, 'deleteUser']);

    $router->get('/bookings', [AdminController::class, 'bookings'])->name('admin.bookings');

    $router->get('/reviews', [AdminController::class, 'reviews'])->name('admin.reviews');
    $router->post('/reviews/{id}/visibility', [AdminController::class, 'toggleReviewVisibility']);

    $router->get('/subscriptions', [AdminController::class, 'subscriptions'])->name('admin.subscriptions');
    $router->post('/subscriptions/grant', [AdminController::class, 'grantPlan']);

    $router->get('/plans', [AdminController::class, 'plans'])->name('admin.plans');
    $router->post('/plans/{id}', [AdminController::class, 'updatePlan']);

    $router->get('/audit-log', [AdminController::class, 'auditLog'])->name('admin.audit');

    $router->get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    $router->post('/settings', [AdminController::class, 'updateSettings']);
});

// ---------------------------------------------------------------------------
// Webhooks
//
// Exempt from CSRF (see Kernel) because there is no session — authenticated
// instead by verifying the signature with PayPal on every request.
// ---------------------------------------------------------------------------

$router->post('/webhooks/paypal', [PayPalController::class, 'handle'])->name('webhooks.paypal');
