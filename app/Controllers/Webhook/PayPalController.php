<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\Notification;
use App\Models\Subscription;

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

        // Idempotency: PayPal retries until it gets a 2xx.
        if ($eventId !== '' && Subscription::eventAlreadyHandled($eventId)) {
            Response::json(['status' => 'already processed']);
        }

        $paypalSubId = (string) ($resource['id'] ?? $resource['billing_agreement_id'] ?? '');
        $subscription = $paypalSubId !== '' ? Subscription::findByPaypalId($paypalSubId) : null;

        Subscription::recordEvent(
            $subscription === null ? null : (int) $subscription['id'],
            $eventType,
            $eventId !== '' ? $eventId : null,
            $event
        );

        if ($subscription === null) {
            // Nothing local to update — still a success as far as PayPal is
            // concerned, so it stops retrying.
            Logger::info('PayPal webhook for an unknown subscription', [
                'event' => $eventType,
                'sub'   => $paypalSubId,
            ]);
            Response::json(['status' => 'ignored']);
        }

        $subscriptionId = (int) $subscription['id'];
        $userId         = (int) $subscription['user_id'];

        switch ($eventType) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                Subscription::activate(
                    $subscriptionId,
                    $this->timeOf($resource, ['billing_info', 'last_payment', 'time']) ?? date('Y-m-d H:i:s'),
                    $this->timeOf($resource, ['billing_info', 'next_billing_time'])
                );
                $this->notify($userId, 'Your plan is active', 'Your listing limits and placement have been upgraded.');
                break;

            case 'PAYMENT.SALE.COMPLETED':
                // A renewal payment: roll the billing period forward.
                $nextBilling = $this->timeOf($resource, ['billing_info', 'next_billing_time']);
                if ($nextBilling === null) {
                    $cycle = (string) $subscription['billing_cycle'];
                    $nextBilling = date('Y-m-d H:i:s', strtotime($cycle === 'yearly' ? '+1 year' : '+1 month'));
                }
                Subscription::extendPeriod($subscriptionId, $nextBilling);
                break;

            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                Subscription::setStatus($subscriptionId, 'past_due');
                $this->notify(
                    $userId,
                    'Payment failed',
                    'PayPal could not take your subscription payment. Update your payment method to keep your plan.',
                    Url::to('/artist/subscription')
                );
                break;

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                Subscription::setStatus($subscriptionId, 'past_due');
                $this->notify($userId, 'Your plan is paused', 'Your subscription has been suspended at PayPal.');
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                Subscription::setStatus($subscriptionId, 'cancelled');
                $this->notify(
                    $userId,
                    'Your plan was cancelled',
                    'Your subscription has ended. Your profile is hidden from search until you subscribe again — nothing has been deleted.'
                );
                break;

            case 'BILLING.SUBSCRIPTION.EXPIRED':
                Subscription::setStatus($subscriptionId, 'expired');
                $this->notify($userId, 'Your plan expired', 'Renew any time to restore your listing limits.');
                break;

            default:
                Logger::info('Unhandled PayPal event', ['event' => $eventType]);
                break;
        }

        Response::json(['status' => 'ok']);
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
