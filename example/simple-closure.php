<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;

/*
|--------------------------------------------------------------------------
| Define a dependency injection container
|--------------------------------------------------------------------------
*/
$container = new Container();


/*
|--------------------------------------------------------------------------
| Router setup
|--------------------------------------------------------------------------
*/

$router = new Router(static fn(string $class) => $container->create($class));

$router->add('/hello/*/greet', function (string $name) {
    return "Hello $name! How are you?";
});



/*
|--------------------------------------------------------------------------
| Dispatching the request
|--------------------------------------------------------------------------
*/

try {

    $response = $router->dispatch('/hello/John/greet');
    echo $response; //@phpstan-ignore-line

} catch (InvalidRoute $e) {
    http_response_code(404);
    echo 'Not Found';
} catch (RouterConfigurationException $e) {
    // Handle errors from router configuration
    // Might be issues with how routes are defined, classes are set up, etc.
} catch (Throwable $e) {
    // Handle errors from application
}