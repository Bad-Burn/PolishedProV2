<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\Subscription;

/**
 * Subscription changes that must happen in two places at once: our tables
 * and PayPal.
 *
 * The model can retire a row in a transaction, but only PayPal can stop the
 * money. Every path that replaces or removes a paid plan goes through here so
 * an artist is never left paying for something we have already switched off.
 */
final class SubscriptionService
{
    /**
     * Make a subscription the artist's live plan and stop billing for any plan
     * it replaced.
     *
     * A row that has already been retired is left alone: a late ACTIVATED
     * webhook for the plan someone upgraded away from must not switch it back
     * on — that would retire, and cancel at PayPal, the plan they actually want.
     */
    public static function activate(int $subscriptionId, ?string $periodStart = null, ?string $periodEnd = null): bool
    {
        $row = Subscription::find($subscriptionId);
        if ($row === null || in_array($row['status'], ['cancelled', 'expired'], true)) {
            return false;
        }

        $replaced = Subscription::activate($subscriptionId, $periodStart, $periodEnd);
        self::cancelAtPaypal($replaced, 'Replaced by a new plan');

        return true;
    }

    /**
     * Admin grant without payment. Anything the artist was paying PayPal for
     * is cancelled, since the grant now covers them.
     */
    public static function grantManual(int $userId, int $planId, int $months): int
    {
        $result = Subscription::grantManual($userId, $planId, $months);
        self::cancelAtPaypal($result['replaced'], 'Replaced by a plan granted by PolishedPro');

        return $result['id'];
    }

    /**
     * A renewal payment cleared at PayPal.
     *
     * An expired row is revived only if nothing else has taken its place —
     * PayPal can collect a retried payment after our grace window closed, and
     * the artist has paid, so they get their plan back. A payment against a
     * plan we retired means PayPal is still billing something it should not
     * be: stop it, and flag it so the charge can be refunded.
     */
    public static function recordRenewal(array $subscription, string $nextBilling): void
    {
        $id     = (int) $subscription['id'];
        $status = (string) $subscription['status'];

        $retired = $status === 'cancelled'
            || ($status === 'expired' && (
                (int) $subscription['cancel_at_period_end'] === 1
                || Subscription::activeFor((int) $subscription['user_id']) !== null
            ));

        if ($retired) {
            Logger::error('PayPal collected a payment on a retired subscription — refund it', [
                'subscription_id' => $id,
                'user_id'         => (int) $subscription['user_id'],
                'paypal_id'       => $subscription['paypal_subscription_id'] ?? null,
            ]);
            self::cancelAtPaypal([(string) ($subscription['paypal_subscription_id'] ?? '')], 'Plan no longer active');
            return;
        }

        Subscription::extendPeriod($id, $nextBilling);
    }

    /**
     * Stop every PayPal agreement that would keep charging this artist. Used
     * before an account is deleted — afterwards there would be no row left to
     * tie the charges to.
     *
     * @return bool false if any agreement could not be cancelled, in which case
     *              the account must not be deleted
     */
    public static function cancelAllBilling(int $userId, string $reason): bool
    {
        $ids = Subscription::billingPaypalIdsFor($userId);
        if ($ids === []) {
            return true;
        }

        if (!PayPalClient::isConfigured()) {
            Logger::error('Cannot cancel PayPal billing: PayPal is not configured', ['user_id' => $userId]);
            return false;
        }

        return self::cancelAtPaypal($ids, $reason);
    }

    /**
     * @param array<int, string> $paypalIds
     * @return bool true when every agreement was cancelled
     */
    private static function cancelAtPaypal(array $paypalIds, string $reason): bool
    {
        $allCancelled = true;

        foreach ($paypalIds as $paypalId) {
            if ($paypalId === '') {
                continue;
            }

            try {
                if (!PayPalClient::cancelSubscription($paypalId, $reason)) {
                    throw new \RuntimeException('PayPal refused the cancellation');
                }
            } catch (\Throwable $e) {
                $allCancelled = false;
                // The artist may now be paying twice. Loud, so someone acts on it.
                Logger::error('Could not cancel a PayPal subscription — cancel it in the PayPal dashboard', [
                    'paypal_id' => $paypalId,
                    'reason'    => $reason,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $allCancelled;
    }
}
