<?php

/**
 * This is a demo file for the router.
 * It is not meant to be used in production.
 */

use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Router;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();

$router = new Router(fn($class) => $container->create($class));

$router->add('/', function (...$args) {
    echo 'Hello world!';
});

try {
    $router->dispatch('/');
} catch (\Rammewerk\Router\Error\InvalidRoute $e) {
    echo 'Invalid route: ' . $e->getMessage();
} catch (\Throwable $e) {
    echo 'Application error: ' . $e->getMessage();
}

die;




function dd(mixed $value): never {
    echo '<pre>';
    print_r($value);
    echo '</pre>';
    die;
}

$dependencyInjection = static function ($className, array $args = []) {
    if ($className === ProfileRoute::class) {
        return new $className(new \Rammewerk\Router\Tests\RouteClass\RouteDependency());
    }
    return new $className();
};

$router = new Router($dependencyInjection);

$router->add('/', function () {
    var_dump($test);
    dd($args);
    echo 'Hello world!';
})->middleware([
    MiddlewareBefore::class,
]);

$router->add('/hello', function (string $test) {
    echo $test;
    echo 'Hello world!';
})->middleware([
    MiddlewareBefore::class,
    MiddlewareBefore::class,
]);


$router->group(function () use ($router) {
    $router->add('/profile', ProfileRoute::class)->middleware([MiddlewareBefore::class])->defaultMethod('show');
    $router->add('/profile/name', ProfileNameRoute::class);
    $router->add('/profile/*/images', ProfileNameRoute::class)->method('last');
})->middleware([
    MiddlewareAfter::class,
    MiddlewareBefore::class,
]);

$req = new Request();

try {
    $response = $router->dispatch();
    if ($response) echo $response;
} catch (\Rammewerk\Router\Error\InvalidRoute $e) {
    echo '<h2>Invalid route</h2>';
    echo $e->getMessage();
    die;
} catch (\Throwable $e) {
    echo '<h2>A EXCEPTION OCCURED - NOT INVALID ROUTE</h2>';
    echo '<pre>';
    print_r($e);
    echo '</pre>';
}


#phpinfo();