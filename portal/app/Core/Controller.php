<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__, 2) . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        extract($data, EXTR_SKIP);
        $contentView = $viewFile;
        require dirname(__DIR__, 2) . '/views/layout.php';
    }

    protected function redirect(string $path): void
    {
        $target = $path;
        if (str_starts_with($path, '/portal/') && !str_contains($path, '.php')) {
            $route = '/' . ltrim(substr($path, strlen('/portal')), '/');
            $target = '/portal/public/index.php?route=' . rawurlencode($route);
        }

        header('Location: ' . $target);
        exit;
    }
}
