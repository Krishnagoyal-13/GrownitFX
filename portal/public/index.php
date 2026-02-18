<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

$router = new Router('/portal');

$router->get('/', 'AuthController@showLogin');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');

$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');

$router->post('/logout', 'AuthController@logout');

$router->get('/dashboard', 'DashboardController@index');

$routeFromQuery = $_GET['route'] ?? null;
if (is_string($routeFromQuery) && str_starts_with($routeFromQuery, '/')) {
    $routePath = $routeFromQuery;
} else {
    $routePath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $routePath);
