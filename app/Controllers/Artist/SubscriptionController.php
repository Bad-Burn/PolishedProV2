<?php

declare(strict_types=1);

namespace App\Controllers\Artist;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Url;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Services\PayPalClient;
use App\Services\PayPalSubscriptionPaymentService;

/**
 * Artist billing: choose a plan, pay through PayPal, manage or cancel.
 *
 * PayPal is the source of truth for whether money changed hands. This app
 * never marks a subscription active from a user-controlled redirect alone —
 * it re-reads the subscription from the API before believing it.
 */
final class SubscriptionController extends Controller
{
    public function index(Request $request): never
    {
        $artistId = (int) Auth::id();
        $cycle    = $request->query('cycle') === 'yearly' ? 'yearly' : 'monthly';
        $premium  = Plan::findByCode('premium');
        $premiumPlanId = (string) ($premium['paypal_plan_id_monthly'] ?? '');
        $premiumYearlyPlanId = (string) ($premium['paypal_plan_id_yearly'] ?? '');

        $this->view('artist/subscription', [
            'title'        => 'Plan and billing',
            'plans'        => Plan::all(),
            'current'      => Subscription::planFor($artistId),
            'subscription' => Subscription::activeFor($artistId),
            'history'      => Subscription::historyFor($artistId),
            'payments'     => Payment::historyFor($artistId),
            'cycle'        => $cycle,
            'allowance'    => Subscription::serviceAllowance($artistId),
            'portfolioAllowance' => Subscription::portfolioAllowance($artistId),
            'paypalReady'  => PayPalClient::isConfigured(),
            'sandbox'      => PayPalClient::isSandbox(),
            'paypalPremium' => [
                'ready' => PayPalClient::isConfigured()
                    && PayPalClient::isSandbox()
                    && $premiumPlanId !== ''
                    && trim((string) Config::get('paypal.webhook_id', '')) !== ''
                    && strtoupper((string) Config::get('paypal.currency', 'PHP')) === 'PHP',
                'yearly_ready' => PayPalClient::isConfigured()
                    && PayPalClient::isSandbox()
                    && $premiumYearlyPlanId !== ''
                    && trim((string) Config::get('paypal.webhook_id', '')) !== ''
                    && strtoupper((string) Config::get('paypal.currency', 'PHP')) === 'PHP',
                'sandbox_only' => true,
                'client_id' => PayPalClient::clientId(),
                'plan_id' => $premiumPlanId,
                'customer_reference' => Subscription::paypalCustomerReference($artistId),
                'confirm_url' => Url::to('/artist/subscription/confirm'),
            ],
            'plan'         => Subscription::planFor($artistId),
            'usedSlots'    => Service::countActive($artistId),
        ]);
    }

    /**
     * Start a checkout: create the subscription at PayPal and send the artist
     * to the approval page.
     */
    public function checkout(Request $request): never
    {
        $artistId = (int) Auth::id();

        $planId = $request->int('plan_id');
        $cycle  = $request->string('cycle') === 'yearly' ? 'yearly' : 'monthly';

        $plan = Plan::find($planId);
        if ($plan === null || (int) $plan['is_active'] !== 1 || ($plan['code'] ?? '') !== 'premium') {
            $this->failure('That plan is not available.', '/artist/subscription');
        }

        if (!Plan::isPaid($plan)) {
            $this->failure('That plan is not available for checkout.', '/artist/subscription');
        }

        if (Plan::priceFor($plan, $cycle) <= 0) {
            $this->failure('That plan is offered monthly only.', '/artist/subscription');
        }

        if (($plan['code'] ?? '') === 'premium' && !PayPalClient::isSandbox()) {
            $this->failure('The PolishedProV2 Premium plan is configured for PayPal Sandbox only.', '/artist/subscription');
        }

        $currentPlan = Subscription::planFor($artistId);
        if ((int) ($currentPlan['id'] ?? 0) === $planId
            && ($currentPlan['subscription_status'] ?? '') === 'active'
            && (int) ($currentPlan['cancel_at_period_end'] ?? 0) === 0
        ) {
            $this->failure('You are already on that plan.', '/artist/subscription');
        }

        $startAt = ($plan['code'] ?? '') === 'premium' ? null : $this->firstChargeAt($artistId);
        $this->startCheckout($artistId, $plan, $cycle, $startAt);
    }

    /**
     * When a new subscription should take its first payment.
     *
     * Time the artist already has — the rest of their trial, or the paid
     * period of a plan they cancelled — is not charged for again. Switching
     * from a plan that is still renewing starts straight away; the old
     * agreement is cancelled when the new one activates.
     */
    private function firstChargeAt(int $artistId): ?string
    {
        $active = Subscription::activeFor($artistId);

        if ($active !== null) {
            return (int) $active['cancel_at_period_end'] === 1 ? $active['current_period_end'] : null;
        }

        $access = Subscription::accessFor($artistId);

        return $access['state'] === 'trialing' ? $access['trial_ends_at'] : null;
    }

    /** @param array<string, mixed> $plan */
    private function startCheckout(int $artistId, array $plan, string $cycle, ?string $startAt): never
    {
        $user   = Auth::user();
        $planId = (int) $plan['id'];

        if (!PayPalClient::isConfigured()) {
            $this->failure(
                'Online payment is not configured yet. Contact ' . Config::get('app.support_email') . ' to activate a plan.',
                '/artist/subscription'
            );
        }

        $paypalPlanId = $cycle === 'yearly'
            ? ($plan['paypal_plan_id_yearly'] ?? null)
            : ($plan['paypal_plan_id_monthly'] ?? null);

        if (empty($paypalPlanId)) {
            Logger::error('Missing PayPal plan id', ['plan' => $plan['code'], 'cycle' => $cycle]);
            $this->failure(
                'That billing option is not set up yet. Please try the other billing cycle or contact support.',
                '/artist/subscription'
            );
        }

        $nameParts = preg_split('/\s+/', trim((string) $user['full_name'])) ?: [''];

        try {
            $result = PayPalClient::createSubscription(
                (string) $paypalPlanId,
                [
                    'first_name' => $nameParts[0] ?? 'Artist',
                    'last_name'  => count($nameParts) > 1 ? (string) end($nameParts) : '',
                    'email'      => (string) $user['email'],
                ],
                Url::absolute('/artist/subscription/return'),
                Url::absolute('/artist/subscription/cancel'),
                $startAt,
                Subscription::paypalCustomerReference($artistId)
            );
        } catch (\Throwable $e) {
            Logger::error('PayPal checkout failed: ' . $e->getMessage(), ['user_id' => $artistId]);
            $this->failure('We could not reach PayPal just now. Please try again in a moment.', '/artist/subscription');
        }

        // Record the intent so the return handler and the webhook can both
        // find this row, whichever arrives first.
        $subscriptionId = Subscription::createPending($artistId, $planId, $cycle, $result['id'], (string) $paypalPlanId);

        AuditLog::record('subscription.checkout_started', 'subscription', $subscriptionId, [
            'plan'     => $plan['code'],
            'cycle'    => $cycle,
            'start_at' => $startAt,
        ]);

        \App\Core\Response::redirect($result['approve_url']);
    }

    /**
     * PayPal sends the artist back here after approval.
     *
     * The subscription id in the query string is attacker-controllable, so it
     * is only used to look up a row *we* created for *this* artist, and the
     * real status still comes from the API.
     */
    public function returned(Request $request): never
    {
        $artistId       = (int) Auth::id();
        $paypalSubId    = (string) $request->query('subscription_id', '');

        if ($paypalSubId === '') {
            $this->failure('We did not get a confirmation from PayPal. Check your plan below.', '/artist/subscription');
        }

        $subscription = Subscription::findByPaypalId($paypalSubId);

        if ($subscription === null || (int) $subscription['user_id'] !== $artistId) {
            Logger::security('Subscription return mismatch', [
                'user_id'   => $artistId,
                'paypal_id' => $paypalSubId,
            ]);
            $this->failure('That subscription does not belong to your account.', '/artist/subscription');
        }

        try {
            $remote = PayPalClient::getSubscription($paypalSubId);
        } catch (\Throwable $e) {
            Logger::error('Could not verify subscription on return: ' . $e->getMessage());
            $this->failure(
                'Your payment is being processed. This page will update once PayPal confirms it.',
                '/artist/subscription'
            );
        }

        $status = strtoupper((string) ($remote['status'] ?? ''));

        try {
            PayPalSubscriptionPaymentService::assertRemoteMatchesLocal($subscription, $remote, false);
        } catch (\Throwable $e) {
            Logger::security('PayPal subscription did not match its local record', [
                'user_id' => $artistId,
                'subscription_id' => (int) $subscription['id'],
            ]);
            $this->failure('PayPal could not verify this subscription against your selected plan.', '/artist/subscription');
        }

        if ($status === 'ACTIVE' || $status === 'APPROVED') {
            try {
                $paid = PayPalSubscriptionPaymentService::syncRecentCompletedTransactions($subscription, $remote);
            } catch (\Throwable $e) {
                Logger::warning('Could not confirm the first PayPal payment yet', [
                    'subscription_id' => (int) $subscription['id'],
                    'error' => $e->getMessage(),
                ]);
                $paid = false;
            }

            $current = Subscription::activeFor($artistId);
            if ($paid && $current !== null && (int) $current['id'] === (int) $subscription['id']) {
                Notification::push(
                    $artistId,
                    'subscription.activated',
                    'Your plan is active',
                    'PayPal confirmed your first payment. Your plan benefits are now live.',
                    Url::to('/artist/subscription')
                );
                AuditLog::record('subscription.activated', 'subscription', (int) $subscription['id'], [
                    'paypal_status' => $status,
                    'payment_verified' => true,
                ]);
                $this->success('PayPal confirmed your payment. Your plan is now active.', '/artist/subscription');
            }

            $this->success(
                'PayPal approved the subscription. Your plan will activate as soon as the first payment is confirmed.',
                '/artist/subscription'
            );
        }

        if ($status === 'APPROVAL_PENDING') {
            $this->failure('PayPal is still waiting for approval. Finish the payment to activate your plan.', '/artist/subscription');
        }

        Subscription::setStatus((int) $subscription['id'], 'pending');
        $this->failure('PayPal has not confirmed that subscription yet. We will update it automatically.', '/artist/subscription');
    }

    /** Confirm a subscription created by the PayPal JavaScript SDK. */
    public function confirm(Request $request): never
    {
        $artistId = (int) Auth::id();
        $paypalId = $request->string('subscription_id');
        if (preg_match('/^[A-Za-z0-9_-]{8,60}$/', $paypalId) !== 1) {
            \App\Core\Response::json(['status' => 'error', 'message' => 'PayPal returned an invalid subscription reference.'], 422);
        }

        if (!PayPalClient::isConfigured() || !PayPalClient::isSandbox()) {
            \App\Core\Response::json(['status' => 'error', 'message' => 'The Premium checkout is available in Sandbox only.'], 503);
        }
        if (trim((string) Config::get('paypal.webhook_id', '')) === '') {
            \App\Core\Response::json(['status' => 'error', 'message' => 'Configure the verified PayPal Sandbox webhook before starting Premium checkout.'], 503);
        }

        $plan = Plan::findByCode('premium');
        $expectedPlanId = (string) ($plan['paypal_plan_id_monthly'] ?? '');
        if ($plan === null || (int) ($plan['is_active'] ?? 0) !== 1 || $expectedPlanId === '') {
            \App\Core\Response::json(['status' => 'error', 'message' => 'The Premium Sandbox plan is not configured yet.'], 503);
        }

        try {
            $remote = PayPalClient::getSubscription($paypalId);
        } catch (\Throwable $e) {
            Logger::error('Could not verify browser-created PayPal subscription', ['user_id' => $artistId]);
            \App\Core\Response::json(['status' => 'error', 'message' => 'PayPal could not verify the subscription. Try again shortly.'], 502);
        }

        $expectedReference = Subscription::paypalCustomerReference($artistId);
        if (($remote['id'] ?? '') !== $paypalId
            || ($remote['plan_id'] ?? '') !== $expectedPlanId
            || !hash_equals($expectedReference, (string) ($remote['custom_id'] ?? ''))
        ) {
            Logger::security('Rejected PayPal subscription claim', [
                'user_id' => $artistId,
                'subscription_id' => $paypalId,
            ]);
            \App\Core\Response::json(['status' => 'error', 'message' => 'That subscription is not linked to your account and selected plan.'], 403);
        }

        $local = Subscription::findByPaypalId($paypalId);
        if ($local !== null && ((int) $local['user_id'] !== $artistId || (int) $local['plan_id'] !== (int) $plan['id'])) {
            \App\Core\Response::json(['status' => 'error', 'message' => 'That subscription is already linked to another account.'], 403);
        }

        $current = Subscription::activeFor($artistId);
        if ($local === null && $current !== null && (int) $current['plan_id'] === (int) $plan['id']) {
            try {
                PayPalClient::cancelSubscription($paypalId, 'Duplicate Premium subscription attempt');
            } catch (\Throwable $e) {
                Logger::error('Could not cancel a duplicate Premium subscription', ['user_id' => $artistId]);
            }
            \App\Core\Response::json(['status' => 'error', 'message' => 'You already have an active Premium plan.'], 409);
        }

        $remoteStatus = strtoupper((string) ($remote['status'] ?? ''));
        if (!in_array($remoteStatus, ['ACTIVE', 'APPROVED', 'APPROVAL_PENDING'], true)) {
            \App\Core\Response::json(['status' => 'error', 'message' => 'PayPal has not approved this subscription.'], 422);
        }

        if ($local === null) {
            $localId = Subscription::createPending($artistId, (int) $plan['id'], 'monthly', $paypalId, $expectedPlanId);
            $local = Subscription::findByIdForUser($localId, $artistId);
        }

        try {
            $paid = $local !== null
                && PayPalSubscriptionPaymentService::syncRecentCompletedTransactions($local, $remote);
        } catch (\Throwable $e) {
            Logger::warning('Premium subscription is awaiting PayPal payment confirmation', [
                'user_id' => $artistId,
                'subscription_id' => (int) ($local['id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
            $paid = false;
        }

        $current = Subscription::activeFor($artistId);
        if ($paid && $current !== null && (int) $current['id'] === (int) ($local['id'] ?? 0)) {
            \App\Core\Response::json([
                'status' => 'active',
                'message' => 'PayPal confirmed your first payment. Premium is now active.',
            ]);
        }

        \App\Core\Response::json([
            'status' => 'pending',
            'message' => 'PayPal approved the subscription. Premium activates after the first payment is confirmed.',
        ], 202);
    }

    public function cancelled(Request $request): never
    {
        $this->failure('Checkout cancelled — nothing has been charged.', '/artist/subscription');
    }

    /**
     * Cancel at PayPal, then locally. The plan stays usable until the paid
     * period runs out.
     */
    public function cancel(Request $request): never
    {
        $artistId     = (int) Auth::id();
        $subscription = Subscription::activeFor($artistId);

        if ($subscription === null) {
            $this->failure('You do not have an active paid plan.', '/artist/subscription');
        }

        $this->authorizeOwner((int) $subscription['user_id'], 'subscription');

        $paypalId = $subscription['paypal_subscription_id'] ?? null;

        if ($subscription['provider'] === 'paypal' && !empty($paypalId)) {
            try {
                $cancelled = PayPalClient::cancelSubscription((string) $paypalId, 'Cancelled by the artist');
                if (!$cancelled) {
                    throw new \RuntimeException('PayPal refused the cancellation');
                }
            } catch (\Throwable $e) {
                Logger::error('PayPal cancellation failed: ' . $e->getMessage(), [
                    'subscription_id' => $subscription['id'],
                ]);
                $this->failure(
                    'We could not cancel with PayPal automatically. Please cancel from your PayPal account, or contact support.',
                    '/artist/subscription'
                );
            }
        }

        Subscription::cancelAtPeriodEnd((int) $subscription['id']);
        AuditLog::record('subscription.cancelled', 'subscription', (int) $subscription['id']);

        $end = $subscription['current_period_end'];

        $this->success(
            $end
                ? 'Your plan is cancelled. You keep your current benefits until ' . date_label($end, 'F j, Y') . '.'
                : 'Your plan has been cancelled.',
            '/artist/subscription'
        );
    }

    /**
     * Undo a pending cancellation before the period ends.
     *
     * A cancelled PayPal agreement is final — PayPal has no way to revive it.
     * So for PayPal, "keep my plan" is a fresh subscription to the same plan
     * whose first charge falls on the day the current one runs out: the artist
     * approves once and sees no gap and no double charge.
     */
    public function resume(Request $request): never
    {
        $artistId     = (int) Auth::id();
        $subscription = Subscription::activeFor($artistId);

        if ($subscription === null || (int) $subscription['cancel_at_period_end'] !== 1) {
            $this->failure('There is nothing to resume.', '/artist/subscription');
        }

        $this->authorizeOwner((int) $subscription['user_id'], 'subscription');

        if ($subscription['provider'] === 'paypal') {
            $plan = Plan::find((int) $subscription['plan_id']);
            if ($plan === null || (int) $plan['is_active'] !== 1) {
                $this->failure('That plan is no longer offered. Choose another plan below.', '/artist/subscription');
            }

            $this->startCheckout(
                $artistId,
                $plan,
                (string) $subscription['billing_cycle'],
                $subscription['current_period_end']
            );
        }

        Subscription::resume((int) $subscription['id']);
        AuditLog::record('subscription.resumed', 'subscription', (int) $subscription['id']);

        $this->success('Your plan will continue as normal.', '/artist/subscription');
    }
}
