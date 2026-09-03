<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Plan
{
    /** @return array<int, array<string, mixed>> */
    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM plans';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }

        return array_map(
            static fn (array $row): array => self::hydrate($row),
            Database::select($sql . ' ORDER BY sort_order ASC, price_monthly ASC')
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::selectOne('SELECT * FROM plans WHERE id = :id LIMIT 1', ['id' => $id]);
        return $row === null ? null : self::hydrate($row);
    }

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $row = Database::selectOne('SELECT * FROM plans WHERE code = :c LIMIT 1', ['c' => $code]);
        return $row === null ? null : self::hydrate($row);
    }

    /**
     * What an artist gets when their trial has run out and they have not
     * subscribed: nothing.
     *
     * This is a synthetic plan rather than null so the seventeen callers of
     * Subscription::planFor() can keep reading limits without null-checking —
     * the same contract as before, with the numbers set to zero instead of to
     * a free allowance. Nothing in the database matches it.
     */
    public static function locked(): array
    {
        return [
            'id'                  => 0,
            'code'                => 'locked',
            'name'                => 'No active plan',
            'tagline'             => 'Subscribe to put your profile back in front of clients.',
            'price_monthly'       => 0.0,
            'price_yearly'        => 0.0,
            'max_services'        => 0,
            'max_portfolio_items' => 0,
            'featured_weight'     => 0,
            'badge'               => null,
            'has_analytics'       => 0,
            'has_instant_book'    => 0,
            'highlights'          => [],
        ];
    }

    /** The plan a trial grants. Falls back to the cheapest live plan. */
    public static function trial(): array
    {
        $code = (string) \App\Core\Config::get('app.subscription.trial_plan_code', 'studio');
        $plan = self::findByCode($code);

        if ($plan === null || (int) ($plan['is_active'] ?? 0) !== 1) {
            $active = self::all(true);
            $plan   = $active[0] ?? self::locked();
        }

        return $plan;
    }

    public static function isPaid(array $plan): bool
    {
        return (float) ($plan['price_monthly'] ?? 0) > 0;
    }

    /** @param array<string, mixed> $fields */
    public static function update(int $id, array $fields): void
    {
        $allowed = [
            'name', 'tagline', 'price_monthly', 'price_yearly', 'max_services',
            'max_portfolio_items', 'featured_weight', 'badge', 'has_analytics',
            'has_instant_book', 'is_active', 'sort_order',
            'paypal_plan_id_monthly', 'paypal_plan_id_yearly',
        ];

        $set  = [];
        $bind = ['id' => $id];

        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $bind[$column] = $value;
        }

        if ($set === []) {
            return;
        }

        Database::affectingStatement('UPDATE plans SET ' . implode(', ', $set) . ' WHERE id = :id', $bind);
    }

    /** Store the PayPal plan ids created by bin/paypal-plans.php. */
    public static function setPaypalPlanIds(int $id, ?string $monthly, ?string $yearly): void
    {
        Database::affectingStatement(
            'UPDATE plans SET paypal_plan_id_monthly = :m, paypal_plan_id_yearly = :y WHERE id = :id',
            ['m' => $monthly, 'y' => $yearly, 'id' => $id]
        );
    }

    /** Decode the JSON highlights column into a plain array of strings. */
    private static function hydrate(array $row): array
    {
        $highlights = json_decode((string) ($row['highlights'] ?? '[]'), true);
        $row['highlights'] = is_array($highlights) ? $highlights : [];

        return $row;
    }

    public static function priceFor(array $plan, string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']);
    }

    /** Months free when paying yearly, used as the "save X" label. */
    public static function yearlySavingPercent(array $plan): int
    {
        $monthly = (float) ($plan['price_monthly'] ?? 0);
        $yearly  = (float) ($plan['price_yearly'] ?? 0);

        if ($monthly <= 0 || $yearly <= 0) {
            return 0;
        }

        $full = $monthly * 12;
        return (int) round((($full - $yearly) / $full) * 100);
    }
}
