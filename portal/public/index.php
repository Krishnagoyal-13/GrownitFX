<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

function detectBasePath(): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $phpSelf = (string)($_SERVER['PHP_SELF'] ?? '');

    $entryScript = $scriptName !== '' ? $scriptName : $phpSelf;
    if ($entryScript === '') {
        return '/portal';
    }

    $entryDir = rtrim(str_replace('\\', '/', dirname($entryScript)), '/');

    // Front controller lives in /portal/public, basePath should stop at /portal.
    if (str_ends_with($entryDir, '/public')) {
        $entryDir = substr($entryDir, 0, -strlen('/public'));
    }

    $entryDir = '/' . ltrim($entryDir, '/');
    return $entryDir === '/' ? '' : rtrim($entryDir, '/');
}


$basePath = detectBasePath();
$_ENV['APP_BASE_PATH'] = $basePath;
$router = new Router($basePath);

$router->get('/', 'AuthController@showLogin');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');

$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');

$router->post('/logout', 'AuthController@logout');

$router->get('/dashboard', 'DashboardController@index');

$routeFromQuery = $_GET['route'] ?? null;
if (is_string($routeFromQuery) && str_starts_with($routeFromQuery, '/')) {
    $rawPath = $routeFromQuery;
} else {
    $rawPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
}

$resolvedRoutePath = $router->resolvePath($rawPath);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $resolvedRoutePath === '/_debug') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'REQUEST_URI' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'SCRIPT_NAME' => (string)($_SERVER['SCRIPT_NAME'] ?? ''),
        'detectedBasePath' => $basePath,
        'resolvedRoutePath' => $resolvedRoutePath,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $rawPath);
