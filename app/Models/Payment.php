<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Minimal ledger for verified PayPal subscription payment events. */
final class Payment
{
    /** Record one verified successful sale. Provider transaction IDs are idempotent. */
    public static function recordCompleted(
        int $userId,
        int $subscriptionId,
        string $transactionId,
        float $amount,
        string $currency,
        ?string $paidAt,
        ?string $eventId = null
    ): void {
        if ($userId < 1 || $subscriptionId < 1 || $transactionId === '' || $amount <= 0
            || preg_match('/^[A-Z]{3}$/', $currency) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid verified PayPal payment details.');
        }

        Database::affectingStatement(
            'INSERT INTO payments
                (user_id, purpose, subscription_id, provider, provider_txn_id, provider_event_id,
                 amount, currency, status, paid_at)
             VALUES (:u, "subscription", :s, "paypal", :txn, :event, :amount, :currency, "completed", :paid)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id), subscription_id = VALUES(subscription_id),
                amount = VALUES(amount), currency = VALUES(currency),
                status = CASE WHEN status IN ("refunded", "reversed") THEN status ELSE "completed" END,
                paid_at = COALESCE(paid_at, VALUES(paid_at))',
            [
                'u' => $userId,
                's' => $subscriptionId,
                'txn' => $transactionId,
                'event' => null,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'paid' => self::sqlTime($paidAt),
            ]
        );

        if ($eventId !== null && $eventId !== '') {
            Database::affectingStatement(
                'UPDATE payments SET provider_event_id = :event
                  WHERE provider = "paypal" AND provider_txn_id = :txn
                    AND (provider_event_id IS NULL OR provider_event_id = :event_match)',
                ['event' => $eventId, 'event_match' => $eventId, 'txn' => $transactionId]
            );
        }
    }

    /** Store a failed billing attempt without inventing a transaction ID. */
    public static function recordFailedAttempt(
        int $userId,
        int $subscriptionId,
        string $eventId,
        float $amount,
        string $currency,
        ?string $attemptedAt
    ): void {
        if ($eventId === '') {
            return;
        }

        Database::affectingStatement(
            'INSERT INTO payments
                (user_id, purpose, subscription_id, provider, provider_event_id,
                 amount, currency, status, paid_at)
             VALUES (:u, "subscription", :s, "paypal", :event, :amount, :currency, "failed", :paid)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id), subscription_id = VALUES(subscription_id),
                amount = VALUES(amount), currency = VALUES(currency),
                status = CASE WHEN status IN ("completed", "refunded", "reversed") THEN status ELSE "failed" END',
            [
                'u' => $userId,
                's' => $subscriptionId,
                'event' => $eventId,
                'amount' => number_format(max(0, $amount), 2, '.', ''),
                'currency' => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'PHP',
                'paid' => self::sqlTime($attemptedAt),
            ]
        );
    }

    /** Apply a signed refund/reversal event; can arrive before the sale event. */
    public static function markSaleStatus(
        int $userId,
        int $subscriptionId,
        string $transactionId,
        string $eventId,
        string $status,
        float $amount,
        string $currency,
        ?string $occurredAt
    ): void {
        if (!in_array($status, ['refunded', 'reversed'], true) || $transactionId === '' || $eventId === '') {
            throw new \InvalidArgumentException('Invalid PayPal refund or reversal details.');
        }

        Database::affectingStatement(
            'INSERT INTO payments
                (user_id, purpose, subscription_id, provider, provider_txn_id, provider_event_id,
                 amount, currency, status, paid_at)
             VALUES (:u, "subscription", :s, "paypal", :txn, :event, :amount, :currency, :status, :paid)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id), subscription_id = VALUES(subscription_id),
                amount = CASE WHEN amount = 0 THEN VALUES(amount) ELSE amount END,
                currency = VALUES(currency),
                status = CASE WHEN status = "reversed" THEN status ELSE VALUES(status) END',
            [
                'u' => $userId,
                's' => $subscriptionId,
                'txn' => $transactionId,
                'event' => $eventId,
                'amount' => number_format(max(0, $amount), 2, '.', ''),
                'currency' => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'PHP',
                'status' => $status,
                'paid' => self::sqlTime($occurredAt),
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByPaypalTransaction(string $transactionId): ?array
    {
        if ($transactionId === '') {
            return null;
        }

        return Database::selectOne(
            'SELECT * FROM payments WHERE provider = "paypal" AND provider_txn_id = :txn LIMIT 1',
            ['txn' => $transactionId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function historyFor(int $userId): array
    {
        return Database::select(
            'SELECT id, subscription_id, provider_txn_id, amount, currency, status, paid_at, created_at
               FROM payments
              WHERE user_id = :u AND purpose = "subscription"
              ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
              LIMIT 100',
            ['u' => $userId]
        );
    }

    private static function sqlTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        $timestamp = strtotime($time);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
