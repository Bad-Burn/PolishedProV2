<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Artist subscriptions.
 *
 * This class is the single source of truth for what a plan permits. Controllers
 * ask `canAddService()` / `canAddPortfolioItem()` rather than reading limits
 * themselves, so there is one place to audit when the rules change.
 */
final class Subscription
{
    /** @return array<string, mixed>|null the artist's current active subscription */
    public static function activeFor(int $userId): ?array
    {
        return Database::selectOne(
            'SELECT s.*, p.code AS plan_code, p.name AS plan_name, p.badge,
                    p.max_services, p.max_portfolio_items, p.featured_weight,
                    p.has_analytics, p.has_instant_book,
                    p.price_monthly, p.price_yearly
               FROM subscriptions s
               INNER JOIN plans p ON p.id = s.plan_id
              WHERE s.user_id = :u
                AND s.status IN ("active", "past_due")
                AND (s.current_period_end IS NULL OR s.current_period_end > NOW())
              ORDER BY p.featured_weight DESC, s.id DESC
              LIMIT 1',
            ['u' => $userId]
        );
    }

    /**
     * The plan governing this artist right now — always returns something, so
     * callers never have to null-check before reading a limit.
     *
     * @return array<string, mixed>
     */
    public static function planFor(int $userId): array
    {
        $subscription = self::activeFor($userId);

        if ($subscription === null) {
            $plan = Plan::free();
            $plan['subscription_status'] = 'none';
            return $plan;
        }

        $plan = Plan::find((int) $subscription['plan_id']) ?? Plan::free();
        $plan['subscription_id']     = (int) $subscription['id'];
        $plan['subscription_status'] = (string) $subscription['status'];
        $plan['billing_cycle']       = (string) $subscription['billing_cycle'];
        $plan['current_period_end']  = $subscription['current_period_end'];
        $plan['cancel_at_period_end']= (int) $subscription['cancel_at_period_end'];
        $plan['provider']            = (string) $subscription['provider'];

        return $plan;
    }

    // -- Entitlement checks -------------------------------------------------

    /**
     * @return array{allowed:bool, used:int, limit:?int, message:?string}
     */
    public static function serviceAllowance(int $artistId): array
    {
        $plan  = self::planFor($artistId);
        $limit = $plan['max_services'] === null ? null : (int) $plan['max_services'];
        $used  = Service::countActive($artistId);

        if ($limit === null) {
            return ['allowed' => true, 'used' => $used, 'limit' => null, 'message' => null];
        }

        $allowed = $used < $limit;

        return [
            'allowed' => $allowed,
            'used'    => $used,
            'limit'   => $limit,
            'message' => $allowed
                ? null
                : sprintf(
                    'Your %s plan covers %d active service%s. Deactivate one, or upgrade for more.',
                    $plan['name'],
                    $limit,
                    $limit === 1 ? '' : 's'
                ),
        ];
    }

    /** @return array{allowed:bool, used:int, limit:?int, message:?string} */
    public static function portfolioAllowance(int $artistId): array
    {
        $plan  = self::planFor($artistId);
        $limit = $plan['max_portfolio_items'] === null ? null : (int) $plan['max_portfolio_items'];
        $used  = Portfolio::count($artistId);

        if ($limit === null) {
            return ['allowed' => true, 'used' => $used, 'limit' => null, 'message' => null];
        }

        $allowed = $used < $limit;

        return [
            'allowed' => $allowed,
            'used'    => $used,
            'limit'   => $limit,
            'message' => $allowed
                ? null
                : sprintf('Your %s plan includes %d portfolio photos. Remove one, or upgrade for more.', $plan['name'], $limit),
        ];
    }

    public static function hasAnalytics(int $artistId): bool
    {
        return (int) (self::planFor($artistId)['has_analytics'] ?? 0) === 1;
    }

    public static function hasInstantBook(int $artistId): bool
    {
        return (int) (self::planFor($artistId)['has_instant_book'] ?? 0) === 1;
    }

    // -- Lifecycle ----------------------------------------------------------

    /**
     * Record an intent to subscribe before sending the artist to PayPal. The
     * row stays `pending` until the webhook (or the return handler) confirms it.
     */
    public static function createPending(int $userId, int $planId, string $cycle, ?string $paypalId = null): int
    {
        return Database::insert(
            'INSERT INTO subscriptions (user_id, plan_id, status, billing_cycle, provider, paypal_subscription_id)
             VALUES (:u, :p, "pending", :c, "paypal", :pid)',
            [
                'u'   => $userId,
                'p'   => $planId,
                'c'   => $cycle === 'yearly' ? 'yearly' : 'monthly',
                'pid' => $paypalId,
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByPaypalId(string $paypalSubscriptionId): ?array
    {
        return Database::selectOne(
            'SELECT * FROM subscriptions WHERE paypal_subscription_id = :id LIMIT 1',
            ['id' => $paypalSubscriptionId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT s.*, p.name AS plan_name, p.code AS plan_code, u.full_name, u.email
               FROM subscriptions s
               INNER JOIN plans p ON p.id = s.plan_id
               INNER JOIN users u ON u.id = s.user_id
              WHERE s.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Mark a subscription live and retire any earlier one for the same artist,
     * so an upgrade never leaves two active rows fighting over entitlements.
     */
    public static function activate(int $id, ?string $periodStart = null, ?string $periodEnd = null): void
    {
        Database::transaction(static function () use ($id, $periodStart, $periodEnd): void {
            $row = Database::selectOne('SELECT user_id FROM subscriptions WHERE id = :id', ['id' => $id]);
            if ($row === null) {
                return;
            }

            Database::affectingStatement(
                'UPDATE subscriptions
                    SET status = "cancelled", cancelled_at = NOW()
                  WHERE user_id = :u AND id <> :id AND status IN ("active", "past_due", "pending")',
                ['u' => (int) $row['user_id'], 'id' => $id]
            );

            Database::affectingStatement(
                'UPDATE subscriptions
                    SET status = "active",
                        started_at = COALESCE(started_at, NOW()),
                        current_period_start = :start,
                        current_period_end   = :end,
                        cancel_at_period_end = 0,
                        cancelled_at = NULL
                  WHERE id = :id',
                [
                    'start' => $periodStart ?? date('Y-m-d H:i:s'),
                    'end'   => $periodEnd,
                    'id'    => $id,
                ]
            );
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        $valid = ['pending', 'active', 'past_due', 'cancelled', 'expired'];
        if (!in_array($status, $valid, true)) {
            return;
        }

        $extra = in_array($status, ['cancelled', 'expired'], true) ? ', cancelled_at = NOW()' : '';

        Database::affectingStatement(
            'UPDATE subscriptions SET status = :s' . $extra . ' WHERE id = :id',
            ['s' => $status, 'id' => $id]
        );
    }

    /**
     * Cancel at the end of the paid period. The artist keeps their benefits
     * until then — cutting them off mid-cycle after they have paid would be
     * wrong, and would silently unpublish services they are still selling.
     */
    public static function cancelAtPeriodEnd(int $id): void
    {
        Database::affectingStatement(
            'UPDATE subscriptions SET cancel_at_period_end = 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public static function resume(int $id): void
    {
        Database::affectingStatement(
            'UPDATE subscriptions SET cancel_at_period_end = 0, status = "active" WHERE id = :id',
            ['id' => $id]
        );
    }

    public static function extendPeriod(int $id, string $newPeriodEnd): void
    {
        Database::affectingStatement(
            'UPDATE subscriptions
                SET current_period_start = current_period_end,
                    current_period_end = :end,
                    status = "active"
              WHERE id = :id',
            ['end' => $newPeriodEnd, 'id' => $id]
        );
    }

    /** Admin override: grant a plan without going through PayPal. */
    public static function grantManual(int $userId, int $planId, int $months = 1): int
    {
        $id = Database::insert(
            'INSERT INTO subscriptions
                (user_id, plan_id, status, billing_cycle, provider,
                 current_period_start, current_period_end, started_at)
             VALUES (:u, :p, "active", :cycle, "manual", NOW(), :end, NOW())',
            [
                'u'     => $userId,
                'p'     => $planId,
                'cycle' => $months >= 12 ? 'yearly' : 'monthly',
                'end'   => date('Y-m-d H:i:s', strtotime("+{$months} months")),
            ]
        );

        self::activate($id, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime("+{$months} months")));

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    public static function historyFor(int $userId): array
    {
        return Database::select(
            'SELECT s.*, p.name AS plan_name, p.code AS plan_code
               FROM subscriptions s
               INNER JOIN plans p ON p.id = s.plan_id
              WHERE s.user_id = :u
              ORDER BY s.created_at DESC',
            ['u' => $userId]
        );
    }

    /**
     * Expire subscriptions whose paid period has run out. Called
     * opportunistically so a missed webhook cannot leave someone on a paid
     * tier indefinitely.
     */
    public static function expireLapsed(): int
    {
        return Database::affectingStatement(
            'UPDATE subscriptions
                SET status = "expired"
              WHERE status IN ("active", "past_due")
                AND current_period_end IS NOT NULL
                AND current_period_end < NOW()'
        );
    }

    // -- Admin reporting ----------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public static function paginate(array $filters = []): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $bindings['status'] = (string) $filters['status'];
        }

        if (!empty($filters['plan_id'])) {
            $where[] = 's.plan_id = :plan';
            $bindings['plan'] = (int) $filters['plan_id'];
        }

        $clause  = implode(' AND ', $where);
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::scalar("SELECT COUNT(*) FROM subscriptions s WHERE {$clause}", $bindings);

        $rows = Database::select(
            "SELECT s.*, p.name AS plan_name, p.code AS plan_code, p.price_monthly, p.price_yearly,
                    u.full_name, u.email, ap.display_name
               FROM subscriptions s
               INNER JOIN plans p ON p.id = s.plan_id
               INNER JOIN users u ON u.id = s.user_id
               LEFT JOIN artist_profiles ap ON ap.user_id = u.id
              WHERE {$clause}
              ORDER BY s.created_at DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'pages' => (int) max(1, ceil($total / $perPage)),
            'page'  => $page,
        ];
    }

    /** Monthly recurring revenue, normalising yearly plans to a monthly figure. */
    public static function mrr(): float
    {
        return (float) Database::scalar(
            'SELECT COALESCE(SUM(
                        CASE WHEN s.billing_cycle = "yearly"
                             THEN p.price_yearly / 12
                             ELSE p.price_monthly END
                    ), 0)
               FROM subscriptions s
               INNER JOIN plans p ON p.id = s.plan_id
              WHERE s.status = "active" AND s.provider = "paypal"'
        );
    }

    /** @return array<int, array<string, mixed>> plan code => active subscriber count */
    public static function planBreakdown(): array
    {
        return Database::select(
            'SELECT p.name, p.code, p.price_monthly,
                    COUNT(s.id) AS subscribers
               FROM plans p
               LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status = "active"
              GROUP BY p.id
              ORDER BY p.sort_order ASC'
        );
    }

    /** Idempotency guard for webhooks: have we already processed this event? */
    public static function eventAlreadyHandled(string $providerEventId): bool
    {
        return Database::scalar(
            'SELECT 1 FROM subscription_events WHERE provider_event_id = :id LIMIT 1',
            ['id' => $providerEventId]
        ) !== null;
    }

    public static function recordEvent(?int $subscriptionId, string $eventType, ?string $providerEventId, array $payload): void
    {
        Database::insert(
            'INSERT INTO subscription_events (subscription_id, provider_event_id, event_type, payload)
             VALUES (:s, :e, :t, :p)',
            [
                's' => $subscriptionId,
                'e' => $providerEventId,
                't' => $eventType,
                'p' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ]
        );
    }
}
