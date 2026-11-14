<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

/** Shared verification and entitlement rules for PayPal subscription sales. */
final class PayPalSubscriptionPaymentService
{
    /**
     * Record one PayPal-confirmed completed sale and update the paid period.
     * Returns false when the sale was already refunded/reversed and cannot grant access.
     *
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $sale
     * @param array<string, mixed>|null $remoteSubscription
     */
    public static function recordCompletedSale(
        array $subscription,
        array $sale,
        ?string $eventId = null,
        ?array $remoteSubscription = null
    ): bool {
        $paypalSubscriptionId = (string) ($subscription['paypal_subscription_id'] ?? '');
        $remote = $remoteSubscription ?? PayPalClient::getSubscription($paypalSubscriptionId);
        self::assertRemoteMatchesLocal($subscription, $remote, false);

        $saleSubscriptionId = (string) ($sale['billing_agreement_id'] ?? $paypalSubscriptionId);
        if ($paypalSubscriptionId === '' || !hash_equals($paypalSubscriptionId, $saleSubscriptionId)) {
            throw new \RuntimeException('PayPal sale does not match the stored subscription.');
        }

        $state = strtoupper((string) ($sale['state'] ?? $sale['status'] ?? 'COMPLETED'));
        if (!in_array($state, ['COMPLETED', 'SUCCESS'], true)) {
            throw new \RuntimeException('PayPal sale is not completed.');
        }

        $transactionId = (string) ($sale['id'] ?? '');
        $money = self::moneyOf($sale);
        $paidAt = self::timeOf($sale['create_time'] ?? $sale['time'] ?? $sale['update_time'] ?? null);
        if ($transactionId === '' || $money === null || $paidAt === null) {
            throw new \RuntimeException('PayPal sale response is missing verified transaction details.');
        }

        $plan = Plan::find((int) $subscription['plan_id']);
        if ($plan === null) {
            throw new \RuntimeException('The local subscription plan no longer exists.');
        }

        $expectedAmount = number_format(Plan::priceFor($plan, (string) ($subscription['billing_cycle'] ?? 'monthly')), 2, '.', '');
        $expectedCurrency = strtoupper((string) ($plan['currency'] ?? 'PHP'));
        if ($money['amount'] !== $expectedAmount || $money['currency'] !== $expectedCurrency) {
            Logger::security('PayPal subscription sale amount did not match its plan', [
                'subscription_id' => (int) $subscription['id'],
                'currency' => $money['currency'],
            ]);
            throw new \RuntimeException('PayPal payment amount does not match the configured subscription plan.');
        }

        Payment::recordCompleted(
            (int) $subscription['user_id'],
            (int) $subscription['id'],
            $transactionId,
            (float) $money['amount'],
            $money['currency'],
            $paidAt,
            $eventId
        );
        $storedPayment = Payment::findByPaypalTransaction($transactionId);
        if (in_array($storedPayment['status'] ?? '', ['refunded', 'reversed'], true)) {
            Subscription::revokeForPaymentReversal((int) $subscription['id']);
            self::cancelAtPayPal($paypalSubscriptionId, 'A subscription payment was refunded or reversed');
            return false;
        }

        $localPlanId = (string) ($remote['plan_id'] ?? '');
        Subscription::recordSuccessfulPaymentMetadata((int) $subscription['id'], $localPlanId, $paidAt);

        $nextBilling = self::timeOf($remote['billing_info']['next_billing_time'] ?? null)
            ?? self::periodEndFrom($paidAt, (string) ($subscription['billing_cycle'] ?? 'monthly'));

        if ((string) ($subscription['status'] ?? '') === 'pending') {
            return SubscriptionService::activate((int) $subscription['id'], $paidAt, $nextBilling);
        }

        SubscriptionService::recordRenewal($subscription, $nextBilling);
        return !in_array((string) ($subscription['status'] ?? ''), ['cancelled'], true);
    }

    /** Sync completed transactions during the approval return/callback flow. */
    public static function syncRecentCompletedTransactions(array $subscription, array $remote): bool
    {
        self::assertRemoteMatchesLocal($subscription, $remote, false);

        $created = strtotime((string) ($subscription['created_at'] ?? '')) ?: (time() - 86400);
        $to = time() + 60;
        // PayPal's transaction-list endpoint accepts a bounded time window.
        $from = max($created - 86400, $to - (30 * 86400));
        $transactions = PayPalClient::subscriptionTransactions(
            (string) $subscription['paypal_subscription_id'],
            gmdate('Y-m-d\TH:i:s\Z', $from),
            gmdate('Y-m-d\TH:i:s\Z', $to)
        );

        usort($transactions, static function (array $left, array $right): int {
            return strcmp((string) ($left['time'] ?? ''), (string) ($right['time'] ?? ''));
        });

        $recorded = false;
        foreach ($transactions as $transaction) {
            if (strtoupper((string) ($transaction['status'] ?? '')) !== 'COMPLETED') {
                continue;
            }
            $recorded = self::recordCompletedSale($subscription, $transaction, null, $remote) || $recorded;
            // Refresh after the first payment changes the local status.
            $subscription = Subscription::findByIdForUser(
                (int) $subscription['id'],
                (int) $subscription['user_id']
            ) ?? $subscription;
        }

        return $recorded;
    }

    /** Verify a local row against PayPal's authoritative subscription details. */
    public static function assertRemoteMatchesLocal(array $subscription, array $remote, bool $requireReference): void
    {
        $storedPaypalId = (string) ($subscription['paypal_subscription_id'] ?? '');
        if ($storedPaypalId === '' || !hash_equals($storedPaypalId, (string) ($remote['id'] ?? ''))) {
            throw new \RuntimeException('PayPal subscription ID does not match the local subscription.');
        }

        $plan = Plan::find((int) ($subscription['plan_id'] ?? 0));
        if ($plan === null) {
            throw new \RuntimeException('The local subscription plan no longer exists.');
        }

        $cycle = (string) ($subscription['billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $expectedPlanId = (string) ($subscription['paypal_plan_id']
            ?? $plan[$cycle === 'yearly' ? 'paypal_plan_id_yearly' : 'paypal_plan_id_monthly']
            ?? '');
        if ($expectedPlanId === '' || !hash_equals($expectedPlanId, (string) ($remote['plan_id'] ?? ''))) {
            throw new \RuntimeException('PayPal subscription plan does not match the local subscription.');
        }

        $reference = (string) ($remote['custom_id'] ?? '');
        if ($requireReference || $reference !== '') {
            $expectedReference = Subscription::paypalCustomerReference((int) $subscription['user_id']);
            if ($reference === '' || !hash_equals($expectedReference, $reference)) {
                throw new \RuntimeException('PayPal subscription customer reference does not match this account.');
            }
        }
    }

    /** @return array{amount:string,currency:string}|null */
    public static function moneyOf(array $resource): ?array
    {
        $money = $resource['amount_with_breakdown']['gross_amount']
            ?? $resource['amount']
            ?? null;

        if (is_array($money) && isset($money['total']) && !isset($money['value'])) {
            $money = ['value' => $money['total'], 'currency_code' => $money['currency'] ?? $money['currency_code'] ?? ''];
        }
        if (!is_array($money) || !is_numeric($money['value'] ?? null)) {
            return null;
        }

        $amount = (float) $money['value'];
        $currency = strtoupper((string) ($money['currency_code'] ?? $money['currency'] ?? ''));
        if ($amount < 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return null;
        }

        return ['amount' => number_format($amount, 2, '.', ''), 'currency' => $currency];
    }

    private static function periodEndFrom(string $paidAt, string $cycle): string
    {
        $timestamp = strtotime($paidAt);
        if ($timestamp === false) {
            $timestamp = time();
        }
        $interval = $cycle === 'yearly' ? '+1 year' : '+1 month';
        return date('Y-m-d H:i:s', (int) strtotime($interval, $timestamp));
    }

    private static function timeOf(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private static function cancelAtPayPal(string $paypalSubscriptionId, string $reason): void
    {
        try {
            PayPalClient::cancelSubscription($paypalSubscriptionId, $reason);
        } catch (\Throwable $e) {
            Logger::error('Could not cancel refunded/reversed PayPal subscription', [
                'subscription_id' => $paypalSubscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
