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

    /** The plan every artist falls back to when they have no active subscription. */
    public static function free(): array
    {
        return self::findByCode('starter') ?? [
            'id'                  => 0,
            'code'                => 'starter',
            'name'                => 'Starter',
            'tagline'             => '',
            'price_monthly'       => 0.0,
            'price_yearly'        => 0.0,
            'max_services'        => 3,
            'max_portfolio_items' => 6,
            'featured_weight'     => 0,
            'badge'               => null,
            'has_analytics'       => 0,
            'has_instant_book'    => 0,
            'highlights'          => [],
        ];
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
