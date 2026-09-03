<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query in the app goes through here with bound
 * parameters — there is no code path that concatenates user input into SQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** @var array<int, array{sql:string, ms:float}> collected only when APP_DEBUG is on */
    private static array $log = [];

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = (array) Config::get('database.mysql', []);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? 'polishedpro_v2',
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO(
                $dsn,
                (string) ($config['username'] ?? 'root'),
                (string) ($config['password'] ?? ''),
                self::options()
            );

            // Strict mode surfaces truncation/invalid-date bugs instead of silently coercing.
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Unable to connect to the database. Check your .env settings.', 0, $e);
        }

        return self::$pdo = $pdo;
    }

    /** @return array<int, mixed> */
    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements: the driver never interpolates values itself.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_PERSISTENT         => false,
        ];
    }

    /** @param array<string|int, mixed> $bindings */
    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $started = microtime(true);

        $statement = self::connection()->prepare($sql);
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $statement->bindValue($param, $value, self::typeOf($value));
        }
        $statement->execute();

        if (Config::get('app.debug')) {
            self::$log[] = ['sql' => $sql, 'ms' => round((microtime(true) - $started) * 1000, 2)];
        }

        return $statement;
    }

    private static function typeOf(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    /** @return array<int, array<string, mixed>> */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::run($sql, $bindings)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::run($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $value = self::run($sql, $bindings)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $sql, array $bindings = []): int
    {
        self::run($sql, $bindings);
        return (int) self::connection()->lastInsertId();
    }

    public static function affectingStatement(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    /**
     * Run a closure inside a transaction, rolling back on any exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        // Nested calls just join the outer transaction.
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function isMysql(): bool
    {
        return Config::get('database.driver', 'mysql') === 'mysql';
    }

    /** @return array<int, array{sql:string, ms:float}> */
    public static function queryLog(): array
    {
        return self::$log;
    }
}
