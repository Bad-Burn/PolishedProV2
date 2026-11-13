<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
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
    /**
     * PayPal charges a renewal at `next_billing_time`, but the sale (and its
     * webhook) can land hours later. A subscription that is still set to renew
     * keeps working this long past its period end, so a paying artist does not
     * drop out of search while PayPal catches up. A cancelled one gets nothing.
     */
    public const RENEWAL_GRACE_DAYS = 3;

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
                AND p.price_monthly > 0
                AND ' . self::periodLiveSql('s') . '
              ORDER BY p.featured_weight DESC, s.id DESC
              LIMIT 1',
            ['u' => $userId]
        );
    }

    /**
     * SQL for "this row's paid period has not run out", grace included.
     * Artist::BASE_SELECT and Artist::HAS_ACCESS spell the same test out
     * inline, because class constants cannot call a method — keep them in step.
     */
    public static function periodLiveSql(string $alias): string
    {
        return "({$alias}.current_period_end IS NULL
                 OR {$alias}.current_period_end > NOW()
                 OR ({$alias}.provider = 'paypal'
                     AND {$alias}.cancel_at_period_end = 0
                     AND {$alias}.current_period_end > NOW() - INTERVAL " . self::RENEWAL_GRACE_DAYS . ' DAY))';
    }

    public static function trialDays(): int
    {
        return max(0, (int) Config::get('app.subscription.trial_days', 14));
    }

    /**
     * Where this artist stands: inside their trial, paying, or locked out.
     *
     * This is the one question the rest of the app should ask. `planFor()`
     * answers "what may they do"; this answers "may they trade at all".
     *
     * An artist still waiting for approval counts as trialing, with the full
     * trial ahead of them: they need the trial plan's limits to set up their
     * services, and the clock only starts once an admin approves them
     * (AdminController::approveArtist). They are not live, and the public
     * queries keep them hidden regardless.
     *
     * @return array{state:string, trial_ends_at:?string, days_left:int, is_live:bool, awaiting_approval:bool}
     */
    public static function accessFor(int $userId): array
    {
        if (self::activeFor($userId) !== null) {
            return ['state' => 'subscribed', 'trial_ends_at' => null, 'days_left' => 0, 'is_live' => true, 'awaiting_approval' => false];
        }

        $artist = Database::selectOne(
            'SELECT trial_ends_at, approved_at FROM users WHERE id = :u AND role = "artist" LIMIT 1',
            ['u' => $userId]
        );

        if ($artist !== null && empty($artist['approved_at'])) {
            return [
                'state'             => 'trialing',
                'trial_ends_at'     => date('Y-m-d H:i:s', strtotime('+' . self::trialDays() . ' days')),
                'days_left'         => self::trialDays(),
                'is_live'           => false,
                'awaiting_approval' => true,
            ];
        }

        $trialEnds = $artist['trial_ends_at'] ?? null;

        if ($trialEnds === null || $trialEnds === '') {
            return ['state' => 'locked', 'trial_ends_at' => null, 'days_left' => 0, 'is_live' => false, 'awaiting_approval' => false];
        }

        $endsAt = strtotime((string) $trialEnds);
        if ($endsAt === false || $endsAt <= time()) {
            return ['state' => 'locked', 'trial_ends_at' => (string) $trialEnds, 'days_left' => 0, 'is_live' => false, 'awaiting_approval' => false];
        }

        return [
            'state'             => 'trialing',
            'trial_ends_at'     => (string) $trialEnds,
            // Part of a day still counts as a day to the person reading it.
            'days_left'         => (int) max(1, ceil(($endsAt - time()) / 86400)),
            'is_live'           => true,
            'awaiting_approval' => false,
        ];
    }

    /** Can this artist appear publicly and take new bookings? */
    public static function isLive(int $userId): bool
    {
        return self::accessFor($userId)['is_live'];
    }

    /**
     * The plan governing this artist right now — always returns something, so
     * callers never have to null-check before reading a limit.
     *
     * A trial grants a real plan's entitlements; a lapsed artist gets
     * Plan::locked(), whose limits are all zero.
     *
     * @return array<string, mixed>
     */
    public static function planFor(int $userId): array
    {
        $subscription = self::activeFor($userId);

        if ($subscription === null) {
            $access = self::accessFor($userId);

            $plan = $access['state'] === 'trialing' ? Plan::trial() : Plan::locked();
            $plan['subscription_status'] = $access['state'] === 'trialing' ? 'trialing' : 'none';
            $plan['trial_ends_at']       = $access['trial_ends_at'];
            $plan['trial_days_left']     = $access['days_left'];

            return $plan;
        }

        $plan = Plan::find((int) $subscription['plan_id']) ?? Plan::locked();
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
            'message' => $allowed ? null : ($limit === 0
                ? self::lockedMessage()
                : sprintf(
                    'Your %s plan covers %d active service%s. Deactivate one, or upgrade for more.',
                    $plan['name'],
                    $limit,
                    $limit === 1 ? '' : 's'
                )),
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
            'message' => $allowed ? null : ($limit === 0
                ? self::lockedMessage()
                : sprintf('Your %s plan includes %d portfolio photos. Remove one, or upgrade for more.', $plan['name'], $limit)),
        ];
    }

    /** One wording for every "your trial ended" dead end. */
    private static function lockedMessage(): string
    {
        return 'Your trial has ended, so your profile is hidden and you cannot take new bookings. '
             . 'Choose a plan to go live again — nothing you have added has been deleted.';
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
     *
     * Retiring a row here only changes our records. The caller must also stop
     * the PayPal agreements returned, or the artist keeps paying for the plan
     * they just replaced — use SubscriptionService::activate(), not this.
     *
     * @return array<int, string> PayPal ids of replaced subscriptions that were still set to renew
     */
    public static function activate(int $id, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        return Database::transaction(static function () use ($id, $periodStart, $periodEnd): array {
            $row = Database::selectOne('SELECT user_id FROM subscriptions WHERE id = :id', ['id' => $id]);
            if ($row === null) {
                return [];
            }

            $stillBilling = Database::select(
                'SELECT paypal_subscription_id
                   FROM subscriptions
                  WHERE user_id = :u AND id <> :id
                    AND provider = "paypal"
                    AND paypal_subscription_id IS NOT NULL
                    AND status IN ("active", "past_due")
                    AND cancel_at_period_end = 0',
                ['u' => (int) $row['user_id'], 'id' => $id]
            );

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

            return array_map(static fn (array $r): string => (string) $r['paypal_subscription_id'], $stillBilling);
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

    /**
     * PayPal has stopped billing this agreement (the artist cancelled here or
     * on PayPal's side). What they already paid for still runs to the end of
     * the period; expireLapsed() closes it after that. A row with no paid time
     * left — or one already retired by an upgrade — is simply cancelled.
     */
    public static function markCancelled(int $id): void
    {
        Database::affectingStatement(
            'UPDATE subscriptions
                SET status = CASE
                        WHEN status IN ("active", "past_due") AND current_period_end > NOW() THEN status
                        ELSE "cancelled"
                    END,
                    cancel_at_period_end = 1,
                    cancelled_at = COALESCE(cancelled_at, NOW())
              WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Undo a pending cancellation. Only meaningful for manual grants — a cancelled PayPal agreement cannot be revived. */
    public static function resume(int $id): void
    {
        Database::affectingStatement(
            'UPDATE subscriptions SET cancel_at_period_end = 0, cancelled_at = NULL WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * A renewal payment cleared: move the period forward to `$newPeriodEnd`.
     *
     * Idempotent — PayPal reports the first payment and the activation
     * separately, and both may carry the same next billing date, so the period
     * only ever moves forwards. A retired row (replaced by an upgrade) has its
     * dates updated but is never switched back on.
     */
    public static function extendPeriod(int $id, string $newPeriodEnd): void
    {
        // Named placeholders cannot repeat with native prepares, hence end1..3.
        Database::affectingStatement(
            'UPDATE subscriptions
                SET current_period_start = CASE
                        WHEN current_period_end IS NOT NULL AND current_period_end < :end1 THEN current_period_end
                        ELSE current_period_start
                    END,
                    current_period_end = CASE
                        WHEN current_period_end IS NULL OR current_period_end < :end2 THEN :end3
                        ELSE current_period_end
                    END,
                    status = CASE WHEN status IN ("active", "past_due", "expired") THEN "active" ELSE status END
              WHERE id = :id',
            ['end1' => $newPeriodEnd, 'end2' => $newPeriodEnd, 'end3' => $newPeriodEnd, 'id' => $id]
        );
    }

    /** Admin override: grant a plan without going through PayPal. */
    /**
     * @return array{id:int, replaced:array<int, string>} the new row, and the
     *         PayPal agreements it replaced that the caller must cancel
     */
    public static function grantManual(int $userId, int $planId, int $months = 1): array
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

        $replaced = self::activate($id, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime("+{$months} months")));

        return ['id' => $id, 'replaced' => $replaced];
    }

    /**
     * PayPal agreements for this artist that would keep charging them if their
     * account disappeared.
     *
     * @return array<int, string>
     */
    public static function billingPaypalIdsFor(int $userId): array
    {
        $rows = Database::select(
            'SELECT paypal_subscription_id
               FROM subscriptions
              WHERE user_id = :u
                AND provider = "paypal"
                AND paypal_subscription_id IS NOT NULL
                AND status IN ("active", "past_due")
                AND cancel_at_period_end = 0',
            ['u' => $userId]
        );

        return array_map(static fn (array $r): string => (string) $r['paypal_subscription_id'], $rows);
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
            'UPDATE subscriptions s
                SET s.status = "expired"
              WHERE s.status IN ("active", "past_due")
                AND s.current_period_end IS NOT NULL
                AND NOT ' . self::periodLiveSql('s')
        );
    }

    /**
     * Live artists whose trial ends within `$days` and who have neither
     * subscribed nor been warned yet.
     *
     * @return array<int, array{id:int, trial_ends_at:string}>
     */
    public static function trialsEndingWithin(int $days): array
    {
        return Database::select(
            'SELECT u.id, u.trial_ends_at
               FROM users u
              WHERE u.role = "artist"
                AND u.status = "active"
                AND u.approved_at IS NOT NULL
                AND u.trial_warned_at IS NULL
                AND u.trial_ends_at > NOW()
                AND u.trial_ends_at <= NOW() + INTERVAL ' . max(1, $days) . ' DAY
                AND NOT EXISTS (
                    SELECT 1 FROM subscriptions s
                     WHERE s.user_id = u.id AND s.status IN ("active", "past_due", "pending")
                )'
        );
    }

    /** Claim the warning for this artist. False if another run already sent it. */
    public static function markTrialWarned(int $userId): bool
    {
        return Database::affectingStatement(
            'UPDATE users SET trial_warned_at = NOW() WHERE id = :u AND trial_warned_at IS NULL',
            ['u' => $userId]
        ) === 1;
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
            'SELECT 1 FROM subscription_events
              WHERE provider_event_id = :id AND processing_status = "processed" LIMIT 1',
            ['id' => $providerEventId]
        ) !== null;
    }

    /**
     * Atomically claim a PayPal event. Failed or abandoned claims can be
     * retried; a recent claim belongs to a handler that is still working.
     */
    public static function claimEvent(?int $subscriptionId, string $eventType, string $providerEventId, array $payload): bool
    {
        $bindings = [
            's' => $subscriptionId,
            'e' => $providerEventId,
            't' => $eventType,
            'p' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ];

        if (Database::affectingStatement(
            'INSERT IGNORE INTO subscription_events
                (subscription_id, provider_event_id, event_type, payload, processing_status, processing_started_at)
             VALUES (:s, :e, :t, :p, "processing", NOW())',
            $bindings
        ) === 1) {
            return true;
        }

        return Database::affectingStatement(
            'UPDATE subscription_events
                SET subscription_id = :s, event_type = :t, payload = :p,
                    processing_status = "processing", processing_started_at = NOW(), processed_at = NULL
              WHERE provider_event_id = :e
                AND processing_status <> "processed"
                AND (processing_status = "failed"
                     OR processing_started_at IS NULL
                     OR processing_started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))',
            $bindings
        ) === 1;
    }

    public static function markEventProcessed(string $providerEventId): void
    {
        Database::affectingStatement(
            'UPDATE subscription_events
                SET processing_status = "processed", processing_started_at = NULL, processed_at = NOW()
              WHERE provider_event_id = :e AND processing_status = "processing"',
            ['e' => $providerEventId]
        );
    }

    public static function markEventFailed(string $providerEventId): void
    {
        Database::affectingStatement(
            'UPDATE subscription_events
                SET processing_status = "failed", processing_started_at = NULL
              WHERE provider_event_id = :e AND processing_status = "processing"',
            ['e' => $providerEventId]
        );
    }
}
