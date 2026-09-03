<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Hash;
use App\Models\Subscription;

final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::selectOne(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    public static function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql      = 'SELECT 1 FROM users WHERE email = :email';
        $bindings = ['email' => strtolower(trim($email))];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $ignoreId;
        }

        return Database::scalar($sql . ' LIMIT 1', $bindings) !== null;
    }

    /**
     * @param array{role:string, full_name:string, email:string, password:string, phone?:?string} $data
     */
    public static function create(array $data): int
    {
        $role = in_array($data['role'], ['customer', 'artist', 'admin'], true) ? $data['role'] : 'customer';

        return Database::insert(
            'INSERT INTO users (role, full_name, email, phone, password_hash, status, approved_at, trial_ends_at)
             VALUES (:role, :name, :email, :phone, :hash, :status, :approved, :trial)',
            [
                'role'   => $role,
                'name'   => $data['full_name'],
                'email'  => strtolower(trim($data['email'])),
                'phone'  => $data['phone'] ?? null,
                'hash'   => Hash::make($data['password']),
                'status' => 'active',
                // Customers are live immediately; artists wait for admin approval.
                'approved' => $role === 'artist' ? null : date('Y-m-d H:i:s'),
                // Artists start a trial the moment they sign up; clients never
                // have one, because nothing on the client side is paid for.
                'trial' => $role === 'artist'
                    ? date('Y-m-d H:i:s', strtotime('+' . Subscription::trialDays() . ' days'))
                    : null,
            ]
        );
    }

    /** @param array<string, mixed> $fields */
    public static function update(int $id, array $fields): bool
    {
        $allowed = ['full_name', 'email', 'phone', 'avatar_path', 'status', 'role', 'approved_at', 'email_verified_at', 'trial_ends_at'];
        $set     = [];
        $bind    = ['id' => $id];

        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue; // never build SQL from unvetted keys
            }
            $set[] = "{$column} = :{$column}";
            $bind[$column] = $value;
        }

        if ($set === []) {
            return false;
        }

        return Database::affectingStatement(
            'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id',
            $bind
        ) > 0;
    }

    public static function updatePassword(int $id, string $plain): void
    {
        Database::affectingStatement(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => Hash::make($plain), 'id' => $id]
        );

        // Every other session and remember-token for this account dies with it.
        Database::affectingStatement('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $id]);
    }

    /** Rehash transparently when the algorithm or cost has moved on. */
    public static function rehashIfNeeded(int $id, string $currentHash, string $plain): void
    {
        if (Hash::needsRehash($currentHash)) {
            Database::affectingStatement(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => Hash::make($plain), 'id' => $id]
            );
        }
    }

    public static function touchLogin(int $id): void
    {
        Database::affectingStatement(
            'UPDATE users SET last_login_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public static function delete(int $id): bool
    {
        return Database::affectingStatement('DELETE FROM users WHERE id = :id', ['id' => $id]) > 0;
    }

    // -- Two-factor ---------------------------------------------------------

    public static function setTwoFactorSecret(int $id, string $secret): void
    {
        Database::affectingStatement(
            'UPDATE users SET two_factor_secret = :s, two_factor_confirmed_at = NULL WHERE id = :id',
            ['s' => $secret, 'id' => $id]
        );
    }

    /** @param array<int, string> $recoveryCodes stored hashed, shown once */
    public static function confirmTwoFactor(int $id, array $recoveryCodes): void
    {
        $hashed = array_map(static fn (string $code): string => Hash::hmac($code), $recoveryCodes);

        Database::affectingStatement(
            'UPDATE users SET two_factor_confirmed_at = :now, two_factor_recovery = :codes WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'codes' => json_encode($hashed), 'id' => $id]
        );
    }

    public static function disableTwoFactor(int $id): void
    {
        Database::affectingStatement(
            'UPDATE users
                SET two_factor_secret = NULL, two_factor_confirmed_at = NULL, two_factor_recovery = NULL
              WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Burn a recovery code; returns false if it was not valid. */
    public static function consumeRecoveryCode(int $id, string $code): bool
    {
        $user = self::find($id);
        if ($user === null || empty($user['two_factor_recovery'])) {
            return false;
        }

        $stored = json_decode((string) $user['two_factor_recovery'], true);
        if (!is_array($stored)) {
            return false;
        }

        $target = Hash::hmac(strtoupper(trim($code)));
        $index  = null;

        foreach ($stored as $i => $hash) {
            if (is_string($hash) && hash_equals($hash, $target)) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return false;
        }

        unset($stored[$index]);

        Database::affectingStatement(
            'UPDATE users SET two_factor_recovery = :codes WHERE id = :id',
            ['codes' => json_encode(array_values($stored)), 'id' => $id]
        );

        return true;
    }

    public static function hasTwoFactor(array $user): bool
    {
        return !empty($user['two_factor_secret']) && !empty($user['two_factor_confirmed_at']);
    }

    // -- Admin listings -----------------------------------------------------

    /**
     * @param array{role?:string, status?:string, search?:string, page?:int, per_page?:int} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int, page: int}
     */
    public static function paginate(array $filters = []): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['role']) && in_array($filters['role'], ['customer', 'artist', 'admin'], true)) {
            $where[] = 'u.role = :role';
            $bindings['role'] = $filters['role'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'suspended', 'pending'], true)) {
            $where[] = 'u.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (!empty($filters['awaiting_approval'])) {
            $where[] = 'u.role = "artist" AND u.approved_at IS NULL';
        }

        if (!empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $where[] = '(u.full_name LIKE :search1 OR u.email LIKE :search2)';
            $bindings['search1'] = $term;
            $bindings['search2'] = $term;
        }

        $clause  = implode(' AND ', $where);
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::scalar("SELECT COUNT(*) FROM users u WHERE {$clause}", $bindings);

        $rows = Database::select(
            "SELECT u.*, ap.display_name, ap.rating_avg, ap.rating_count, ap.is_verified
               FROM users u
               LEFT JOIN artist_profiles ap ON ap.user_id = u.id
              WHERE {$clause}
              ORDER BY u.created_at DESC
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

    public static function countByRole(string $role): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM users WHERE role = :r', ['r' => $role]);
    }

    public static function countPendingArtists(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM users WHERE role = "artist" AND approved_at IS NULL AND status = "active"'
        );
    }
}
