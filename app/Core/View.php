<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templating. Templates receive their data as extracted variables and
 * declare their layout by calling $this->layout() — no template engine needed.
 */
final class View
{
    /** @var array<string, mixed> data shared with every view (current user, counts, …) */
    private static array $shared = [];

    private static ?string $layout = null;

    /** @var array<string, string> named content blocks captured with section()/endSection() */
    private static array $sections = [];

    private static array $sectionStack = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    /**
     * Render a view and wrap it in whatever layout it selected.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        self::$layout   = null;
        self::$sections = [];

        $content = self::capture($template, $data);

        if (self::$layout !== null) {
            $layout = self::$layout;
            self::$layout = null;
            $content = self::capture('layouts/' . $layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /**
     * Render a template fragment without layout handling — used for partials.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $file = PP_VIEWS . DIRECTORY_SEPARATOR . str_replace(['.', '\\'], ['/', '/'], $template) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View [{$template}] not found at {$file}");
        }

        $scope = array_merge(self::$shared, $data);
        extract($scope, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public static function useLayout(string $layout): void
    {
        self::$layout = $layout;
    }

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$sectionStack);
        if ($name === null) {
            return;
        }
        self::$sections[$name] = (string) ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }
}
