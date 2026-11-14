<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PayPalClient;
use App\Services\PayPalSubscriptionPaymentService;

/**
 * PayPal webhook receiver.
 *
 * This endpoint is exempt from CSRF (there is no session and no browser
 * involved) and is therefore authenticated a different way: every payload is
 * sent back to PayPal for signature verification before a single row is
 * touched. Events are also de-duplicated by their PayPal event id, because
 * PayPal retries deliveries and a replayed "payment completed" must not extend
 * a subscription twice.
 */
final class PayPalController extends Controller
{
    public function handle(Request $request): never
    {
        $raw = $request->rawBody();

        if ($raw === '' || strlen($raw) > 512_000) {
            Response::json(['error' => 'Bad payload'], 400);
        }

        $headers = $this->normalisedHeaders();

        if (!\App\Services\PayPalClient::verifyWebhookSignature($headers, $raw)) {
            Logger::security('Rejected PayPal webhook with an invalid signature', [
                'ip' => $request->ip(),
            ]);
            Response::json(['error' => 'Signature verification failed'], 401);
        }

        $event = json_decode($raw, true);
        if (!is_array($event)) {
            Response::json(['error' => 'Malformed JSON'], 400);
        }

        $eventId   = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');
        $resource  = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        if ($eventId === '' || $eventType === '') {
            Response::json(['error' => 'Event id and type are required'], 400);
        }

        // Idempotency: PayPal retries until it gets a 2xx.
        if (Subscription::eventAlreadyHandled($eventId)) {
            Response::json(['status' => 'already processed']);
        }

        $paypalSubId  = $this->subscriptionIdOf($eventType, $resource);
        $subscription = $paypalSubId !== '' ? Subscription::findByPaypalId($paypalSubId) : null;
        if ($subscription === null && str_starts_with($eventType, 'PAYMENT.SALE.')) {
            $saleId = (string) ($resource['sale_id'] ?? $resource['id'] ?? '');
            $payment = Payment::findByPaypalTransaction($saleId);
            if ($payment !== null) {
                $subscription = Subscription::find((int) $payment['subscription_id']);
                $paypalSubId = (string) ($subscription['paypal_subscription_id'] ?? '');
            }
        }

        if (!Subscription::claimEvent(
            $subscription === null ? null : (int) $subscription['id'],
            $eventType,
            $eventId,
            $event
        )) {
            if (Subscription::eventAlreadyHandled($eventId)) {
                Response::json(['status' => 'already processed']);
            }

            // A concurrent handler still owns this event. A non-2xx asks
            // PayPal to retry if that handler fails before finishing.
            Response::json(['error' => 'Event is being processed; retry shortly'], 503);
        }

        try {
            if ($subscription === null) {
                Logger::info('PayPal webhook for an unknown subscription', [
                    'event' => $eventType,
                    'sub'   => $paypalSubId,
                ]);
                Subscription::markEventProcessed($eventId);
                Response::json(['status' => 'ignored']);
            }

            $subscriptionId = (int) $subscription['id'];
            $userId         = (int) $subscription['user_id'];
            $remote = PayPalClient::getSubscription((string) $subscription['paypal_subscription_id']);
            PayPalSubscriptionPaymentService::assertRemoteMatchesLocal($subscription, $remote, false);

            switch ($eventType) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    // Approval/activation is not proof of a successful first charge.
                    // PAYMENT.SALE.COMPLETED is the only event that grants access.
                    break;

                case 'PAYMENT.SALE.COMPLETED':
                    PayPalSubscriptionPaymentService::recordCompletedSale($subscription, $resource, $eventId, $remote);
                    break;

                case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                    $plan = Plan::find((int) $subscription['plan_id']);
                    $money = PayPalSubscriptionPaymentService::moneyOf($resource);
                    Payment::recordFailedAttempt(
                        $userId,
                        $subscriptionId,
                        $eventId,
                        (float) ($money['amount'] ?? ($plan === null ? 0 : Plan::priceFor($plan, (string) $subscription['billing_cycle']))),
                        (string) ($money['currency'] ?? ($plan['currency'] ?? 'PHP')),
                        $this->timeOf($resource, ['billing_info', 'last_failed_payment', 'time'])
                    );
                    Subscription::markPaymentFailure($subscriptionId);
                    $this->notify(
                        $userId,
                        'Payment failed',
                        'PayPal could not take your subscription payment. Update your payment method to keep your plan.',
                        Url::to('/artist/subscription')
                    );
                    break;

                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    Subscription::markPaymentFailure($subscriptionId);
                    $this->notify($userId, 'Your plan is paused', 'Your subscription has been suspended at PayPal.');
                    break;

                case 'PAYMENT.SALE.REFUNDED':
                case 'PAYMENT.SALE.REVERSED':
                    $saleId = (string) ($resource['sale_id'] ?? $resource['id'] ?? '');
                    if ($saleId === '') {
                        throw new \RuntimeException('PayPal refund/reversal did not include the original sale ID.');
                    }
                    $money = PayPalSubscriptionPaymentService::moneyOf($resource);
                    $plan = Plan::find((int) $subscription['plan_id']);
                    $paymentStatus = $eventType === 'PAYMENT.SALE.REVERSED' ? 'reversed' : 'refunded';
                    Payment::markSaleStatus(
                        $userId,
                        $subscriptionId,
                        $saleId,
                        $eventId,
                        $paymentStatus,
                        (float) ($money['amount'] ?? ($plan === null ? 0 : Plan::priceFor($plan, (string) $subscription['billing_cycle']))),
                        (string) ($money['currency'] ?? ($plan['currency'] ?? 'PHP')),
                        $this->timeOf($resource, ['update_time']) ?? $this->timeOf($resource, ['create_time'])
                    );
                    Subscription::revokeForPaymentReversal($subscriptionId);
                    try {
                        if (!PayPalClient::cancelSubscription((string) $subscription['paypal_subscription_id'], 'Subscription payment was refunded or reversed')) {
                            Logger::error('PayPal did not confirm cancellation after a refund/reversal', ['subscription_id' => $subscriptionId]);
                        }
                    } catch (\Throwable $e) {
                        Logger::error('Could not cancel PayPal subscription after a refund/reversal', [
                            'subscription_id' => $subscriptionId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $this->notify($userId, 'Subscription payment reversed', 'The refunded or reversed billing period was removed. Your subscription will not renew.');
                    break;

                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    // Already retired by an upgrade: we cancelled it ourselves and
                    // the artist is on their new plan. Nothing to tell them.
                    if ($subscription['status'] === 'cancelled') {
                        break;
                    }

                    Subscription::markCancelled($subscriptionId);
                    $after = Subscription::find($subscriptionId);

                    if ($after !== null && in_array($after['status'], ['active', 'past_due'], true) && !empty($after['current_period_end'])) {
                        $this->notify(
                            $userId,
                            'Your plan will not renew',
                            'You keep everything until ' . date_label((string) $after['current_period_end'], 'F j')
                                . '. After that your profile is hidden until you subscribe again — nothing is deleted.'
                        );
                    } else {
                        $this->notify(
                            $userId,
                            'Your plan was cancelled',
                            'Your subscription has ended. Your profile is hidden from search until you subscribe again — nothing has been deleted.'
                        );
                    }
                    break;

                case 'BILLING.SUBSCRIPTION.EXPIRED':
                    Subscription::setStatus($subscriptionId, 'expired');
                    $this->notify($userId, 'Your plan expired', 'Renew any time to restore your listing limits.');
                    break;

                default:
                    Logger::info('Unhandled PayPal event', ['event' => $eventType]);
                    break;
            }

            Subscription::markEventProcessed($eventId);
        } catch (\Throwable $e) {
            try {
                Subscription::markEventFailed($eventId);
            } catch (\Throwable $markError) {
                Logger::error('Could not release failed PayPal webhook claim: ' . $markError->getMessage(), [
                    'event_id' => $eventId,
                ]);
            }
            throw $e;
        }

        Response::json(['status' => 'ok']);
    }

    /**
     * Which PayPal subscription an event is about.
     *
     * Subscription events carry the subscription itself, so its id is
     * `resource.id`. Sale events carry the *payment* — there `resource.id` is
     * the sale, and the subscription is `billing_agreement_id`. Reading the
     * wrong one silently drops every renewal.
     */
    private function subscriptionIdOf(string $eventType, array $resource): string
    {
        if (str_starts_with($eventType, 'PAYMENT.SALE.')) {
            return (string) ($resource['billing_agreement_id'] ?? '');
        }

        return (string) ($resource['id'] ?? '');
    }

    /**
     * When the period paid for by this sale ends. The sale itself does not say,
     * so ask PayPal; if that fails, count one cycle on from the period that was
     * just paid for — not from today, or a late webhook would shorten it.
     */
    private function nextBillingAfterPayment(array $subscription): string
    {
        try {
            $remote = PayPalClient::getSubscription((string) $subscription['paypal_subscription_id']);
            $next   = $this->timeOf($remote, ['billing_info', 'next_billing_time']);
            if ($next !== null) {
                return $next;
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not read next billing time from PayPal: ' . $e->getMessage(), [
                'subscription_id' => $subscription['id'],
            ]);
        }

        $from  = !empty($subscription['current_period_end']) ? (string) $subscription['current_period_end'] : 'now';
        $cycle = (string) $subscription['billing_cycle'] === 'yearly' ? '+1 year' : '+1 month';

        return date('Y-m-d H:i:s', (int) strtotime($cycle, (int) strtotime($from)));
    }

    /**
     * PayPal signs against the lower-cased header names.
     *
     * @return array<string, string>
     */
    private function normalisedHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value) || !str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }

        return $headers;
    }

    /** @param array<int, string> $path */
    private function timeOf(array $resource, array $path): ?string
    {
        $value = $resource;

        foreach ($path as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        if (!is_string($value)) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function notify(int $userId, string $title, string $body, ?string $link = null): void
    {
        Notification::push($userId, 'subscription', $title, $body, $link ?? Url::to('/artist/subscription'));
    }
}
