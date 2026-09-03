<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Auth;

/**
 * Immutable record of administrative and sensitive actions.
 *
 * Every admin mutation writes here. It is append-only by convention — there is
 * no update or delete method — so the trail cannot be quietly edited from
 * inside the application.
 */
final class AuditLog
{
    /**
     * The only actions the admin console surfaces.
     *
     * Everything else — bookings, reviews, subscriptions, profile edits — is
     * still recorded, because a trail with holes in it is not worth having.
     * It is simply not on screen: an admin needs to see who got into the
     * platform and when, not what people did once they were inside.
     *
     * @var array<int, string>
     */
    public const SESSION_ACTIONS = ['user.signed_in', 'user.signed_out'];

    public static function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = [],
        ?int $actorId = null
    ): void {
        try {
            Database::insert(
                'INSERT INTO audit_logs (actor_id, action, target_type, target_id, meta, ip_address)
                 VALUES (:actor, :action, :type, :target, :meta, :ip)',
                [
                    'actor'  => $actorId ?? Auth::id(),
                    'action' => substr($action, 0, 80),
                    'type'   => $targetType,
                    'target' => $targetId,
                    'meta'   => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES),
                    'ip'     => self::clientIp(),
                ]
            );
        } catch (\Throwable) {
            // Logging must never take down the action it is recording.
        }
    }

    private static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total:int, pages:int, page:int}
     */
    public static function paginate(array $filters = []): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $bindings['action'] = (string) $filters['action'];
        }

        // Whitelist of actions to show. Applied as a hard filter so a crafted
        // ?action= cannot pull anything outside the allowed set into view.
        if (!empty($filters['actions'])) {
            $names = [];
            foreach (array_values((array) $filters['actions']) as $i => $action) {
                $key = 'act' . $i;
                $names[] = ':' . $key;
                $bindings[$key] = (string) $action;
            }
            $where[] = 'a.action IN (' . implode(', ', $names) . ')';
        }

        if (!empty($filters['actor_id'])) {
            $where[] = 'a.actor_id = :actor';
            $bindings['actor'] = (int) $filters['actor_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $where[] = '(a.action LIKE :search1 OR u.full_name LIKE :search2 OR a.target_type LIKE :search3)';

            foreach (['search1', 'search2', 'search3'] as $name) {
                $bindings[$name] = $term;
            }
        }

        $clause  = implode(' AND ', $where);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 30)));
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id WHERE {$clause}",
            $bindings
        );

        $rows = Database::select(
            "SELECT a.*, u.full_name AS actor_name, u.role AS actor_role
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.actor_id
              WHERE {$clause}
              ORDER BY a.created_at DESC, a.id DESC
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

    /**
     * @param array<int, string> $actions restrict to these actions; empty means all
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 10, array $actions = []): array
    {
        $clause   = '';
        $bindings = [];

        if ($actions !== []) {
            $names = [];
            foreach (array_values($actions) as $i => $action) {
                $key = 'act' . $i;
                $names[] = ':' . $key;
                $bindings[$key] = (string) $action;
            }
            $clause = ' WHERE a.action IN (' . implode(', ', $names) . ')';
        }

        return Database::select(
            'SELECT a.*, u.full_name AS actor_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.actor_id' . $clause . '
              ORDER BY a.created_at DESC, a.id DESC
              LIMIT ' . max(1, min(50, $limit)),
            $bindings
        );
    }

    /**
     * Distinct actions, for the filter dropdown.
     *
     * @param array<int, string> $only restrict to these actions; empty means all
     * @return array<int, string>
     */
    public static function actions(array $only = []): array
    {
        $rows = array_column(
            Database::select('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC'),
            'action'
        );

        return $only === [] ? $rows : array_values(array_intersect($rows, $only));
    }
}
