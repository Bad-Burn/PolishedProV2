<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Route table with `{param}` placeholders and a small middleware pipeline.
 *
 * Handlers are declared as [ControllerClass::class, 'method'] and only
 * instantiated once a route actually matches.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array<int,string>, handler:mixed, middleware:array<int,string>, name:?string}> */
    private array $routes = [];

    /** @var array<int, string> middleware applied to everything declared inside the current group */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    /** @var array<string, string> route name => path pattern */
    private array $names = [];

    public function get(string $path, mixed $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, mixed $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Group a set of routes behind a shared prefix and middleware stack.
     *
     * @param array<int, string> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix     = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(string $method, string $path, mixed $handler): self
    {
        $full = rtrim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($full === '') {
            $full = '/';
        }

        // Turn "/artists/{id}" into a regex, capturing the placeholder names.
        $keys  = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $full
        );

        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'keys'       => $keys,
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
            'name'       => null,
            'path'       => $full,
        ];

        return $this;
    }

    /** Name the most recently registered route so it can be looked up later. */
    public function name(string $name): self
    {
        $index = array_key_last($this->routes);
        if ($index !== null) {
            $this->routes[$index]['name'] = $name;
            $this->names[$name] = $this->routes[$index]['path'];
        }
        return $this;
    }

    public function pathFor(string $name, array $params = []): string
    {
        $path = $this->names[$name] ?? '/';
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }
        return $path;
    }

    public function dispatch(Request $request): void
    {
        $path   = $request->path();
        $method = $request->method();

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['keys'] as $i => $key) {
                $params[$key] = $matches[$i] ?? null;
            }
            $request->setParams($params);

            foreach ($route['middleware'] as $middleware) {
                Middleware::run($middleware, $request);
            }

            $this->invoke($route['handler'], $request);
            return;
        }

        // Path exists but not for this verb — 405 is more useful than a blank 404.
        throw new HttpException($pathMatched ? 405 : 404);
    }

    private function invoke(mixed $handler, Request $request): void
    {
        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request);
            return;
        }

        throw new \RuntimeException('Route handler is not callable.');
    }
}
