<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;

/*
|--------------------------------------------------------------------------
| Define a class
|--------------------------------------------------------------------------
| This is just an example of how to define a class route.
| Remember this won't work because ProfileRoutes is not PSR autoloaded.
*/


class ProfileRoutes {

    public function index(): string {
        return "You have visited /profile";
    }



    public function user(string $name): string {
        return "You have visited /profile/user/$name";
    }



    public function user_settings(?string $type = null): string {
        if ($type) {
            return "You have visited /profile/user/settings/$type";
        }
        return "You have visited /profile/user/settings";
    }



    public function customRoute(string ...$args): string {
        // See route add() setup to understand.
        return 'You visited /custom with args:  ' . implode(', ', $args);
    }


}



/*
|--------------------------------------------------------------------------
| Define a dependency injection container
|--------------------------------------------------------------------------
| To handle class dependencies, or if methods requires a class as a parameter,
| you should have a solid dependency injection container.
*/

$container = new Container();

// Define a closure that will be used to resolve class instances from the router.
$route_dependency_handler = static fn(string $class) => $container->create($class);



/*
|--------------------------------------------------------------------------
| Router setup
|--------------------------------------------------------------------------
*/

$router = new Router($route_dependency_handler);

$router->add('/profile', ProfileRoutes::class);
$router->add('/custom', ProfileRoutes::class)->classMethod('customRoute');


/*
|--------------------------------------------------------------------------
| Dispatching the request
|--------------------------------------------------------------------------
*/

try {

    // Prints: You have visited /profile/user/john
    echo $router->dispatch('/profile/user/john');

    // Prints: "You have visited /profile/user/settings";
    echo $router->dispatch('/profile/user/settings');

    // Prints: "You have visited You visited /custom with args:  foo, bar";
    echo $router->dispatch('/custom/foo/bar');


} catch (InvalidRoute $e) {
    http_response_code(404);
    echo 'Not Found';
} catch (RouterConfigurationException $e) {
    // Handle errors from router configuration
    // Might be issues with how routes are defined, classes are set up, etc.
} catch (Throwable $e) {
    // Handle errors from application
}