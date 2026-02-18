<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function __construct(private readonly string $basePath = '')
    {
    }

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uriPath): void
    {
        $path = $this->normalizePath($uriPath);
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }

        [$controllerName, $action] = explode('@', $handler, 2);
        $class = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($class)) {
            http_response_code(500);
            echo 'Controller not found';
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            echo 'Action not found';
            return;
        }

        $controller->{$action}();
    }

    private function normalizePath(string $uriPath): string
    {
        $path = $uriPath;
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }

        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
