<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Router;

/*
|--------------------------------------------------------------------------
| Define a class
|--------------------------------------------------------------------------
| This is just an example of how to define a class route.
| Remember this won't work because ProfileRoutes is not PSR autoloaded.
*/


#[Route('/profile')] // <- The entry point for the class, must be set!
class ProfileRoutes {

    #[Route('/')]
    public function mainProfilePage(): string {
        return "You have visited /profile";
    }



    #[Route('/profile/user')] // <- Supports a prepended /profile to avoid ambiguity
    public function user(string $name): string {
        return "You have visited /profile/user/$name";
    }



    #[Route('/user/*/settings')] // <- With wildcards
    public function userSettings(string $name, ?string $type = null): string {
        if ($type) {
            return "You have visited /profile/$name/settings/$type";
        }
        return "You have visited /profile/$name/settings/";
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


/*
|--------------------------------------------------------------------------
| Dispatching the request
|--------------------------------------------------------------------------
*/

try {

    // Prints: You have visited /profile/user/john
    echo $router->dispatch('/profile/user/john');

    // Prints: "You have visited /profile/john/settings/newsletter";
    echo $router->dispatch('/profile/john/settings/newsletter');


} catch (InvalidRoute $e) {
    http_response_code(404);
    echo 'Not Found';
} catch (RouterConfigurationException $e) {
    // Handle errors from router configuration
    // Might be issues with how routes are defined, classes are set up, etc.
} catch (Throwable $e) {
    // Handle errors from application
}