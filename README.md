Rammewerk Router
======================
The **Rammewerk Router** is a PHP routing library which provides a simple 
and elegant way to handle routing in your web application. It includes the 
flexibility of using either class-based or Closure-based route handling, and 
the ability to easily customize route access control.

## Installation
```
composer require rammewerk/router
```

## Features
- A clean and clear route definition.
- Closure-based routes for quick and easy routing.
- Support for class-based routes with builtin authorization handler.
- A flexible route loading mechanism, so you can incorporate a dependency injection container to load routes.
- Prevents duplicate routes

Basic usage
----

In its simple form you can define a route with a closure, like many other routers for PHP:

```php
$router->add('/blog', function() {
    echo 'Welcome to my blog!';
});
```

What makes this router unique, is its ability to define one or multiple classes that handle routes based on its public
methods.

Or use class-based routes where each public method corresponds to the given route.
The class must implement the `RouteInterface` or extend the included `Route` class.  

```php
namespace Module\Blog;

class Routes extends \Rammewerk\Component\Route {

    public function index(): void {
        echo 'Welcome to my blog!';
    }
    
    public function article($id=''): void {
        echo "Implement loading of article with id $id";
    }
    
}
```
```php
$router->add('/blog', \Module\Blog\Routes::class);
$router->find( $_SERVER['REQUEST_URI'] );
```
In the above example, every route that starts with /blog/article/ will be handled 
by the article method. While all other /blog/ paths will resolve back to index. 
Read more details about this later 👇🏻

Now you can easily define new routes or logic by adding or changing the given methods.
Let's add a new method to the `Module\Blog` class that handles the path `/blog/about/team/`.

```php
public function about_team(): void {
    echo 'This is the about page for our team';
}
```

The constructor requires a default route for handling empty routes and work as a 
fallback route if no route is matched. Add your closure or class-based router to 
the first parameter when initiating the `Router`.

```php
    use \Rammewerk\Component\Route\Router;
    
    $router = new Router( function() {
        echo 'Welcome to default / fallback route.' 
    });
```
## Custom class loader
If you want to use a custom class loader you can define a closure for the second parameter.
The given parameter to the closure is a copy of the reflected class that matches the given
route (or fallback).
```php
    $router = new Router( \MyDefaultRoute:class, function( \ReflectionClass $class ) use ( $container ) {
        $container->create( $class->name );
    });
```
## Authentication
Class based routes must implement the RouteInterface class which consist of a method called `hasRouteAccess`.
This method used to grant access to the route. If method returns false, the access is blocked, or granted if true.
To authorize access to a route class, implement the method `hasRouteAccess`. The router will only allow 
requests when `hasRouteAccess` returns `true`.
```php
class BlogRoutes extends \Rammewerk\Component\Route {

    public function hasRouteAccess(): bool {
        return ! empty($_SESSION['user_id']);
    }
    
    public function index(): void {
        echo 'Welcome to my secure blog!';
    }
    
}
```

## Adding Routes
```php
$router->add()
```
Accepts 4 parameters:
- `path`: The path to the route.
- `handler`: The closure or class-string to handle the route.
- `method`: (Optional) Specific method inside a class based router.
- `authorize`: (Optional) A boolean that determines if the route requires authorization (default: true).

In most cases. The only thing needed to define is the path (parameter #1) and Closure/class-string (parameter #2).
But there might be cases where you want to have a non-authorized path inside a class-based router where the
router has implemented a route access authorization. In this case, we can define which method inside the 
class based router to target and to specify that this route should not authorize.

```php
$router->add('/accounts', AccountRoutes::class, 'info_privacy', false);
```
The above will only target `/accounts/info/privacy/` and its sub-pages, and will pass these routes through
the `info_privacy()` method in the `AccountRoutes` class. This route will skip the `hasRouteAccess` check. 

## API

### Router

#### `__construct(string|Closure $default_route, Closure $closure = null)`

The constructor accepts a default route handler, and an optional closure for custom loading of route classes.

#### `add(string $path, Closure|string $handler, ?string $method = null, bool $authorize = true)`

Adds a route closure.

#### `find(string $path, string $noAccessRelocatePath = null)`

Resolves and returns the requested path.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Please note that this project is provided "as is" without warranty of any kind, either expressed or implied, including,
but not limited to, the implied warranties of merchantability and fitness for a particular purpose. The entire risk as
to the quality and performance of the project is with you.

## Error Handling

You can catch `RouteAccessDenied` exceptions and handle them according to your application's needs. For example, you may
want to redirect the user to a login page or show an error message.

## Advanced Usage

Advanced features include a custom loading mechanism for your routes by providing a callback function to the Router's
constructor. This allows you to, for example, integrate the Router with your dependency injection system.

Please see the `Router` class for more details.

## Contributing

Contributions are welcome! Please submit a pull request or create an issue to contribute to the project.


# Class based routes

Basically, all `public` methods in the Route class will translate to a path. So in the example above, where we have
defined a class for the `/blog` path. Any method name will translate to whatever comes after the `/blog/` path.

For instance, the method `article` will translate to `/blog/article`. Any methods with underscore will translate to a
slash. So the method `article_detail` will translate to `/blog/article/detail`.

The router will also try to resolve any paths that are not defined in the class. So if a request is made
to `/blog/author`, but the class does not have a method called `author`, the router will call the `index` method, and
pass `author` as the first argument. A request to `/blog/article/archive/{$id}`, where the method `article_archive`
doesn't exist, but the `article` method does, will resolve to the `article` method, and pass `archive` as first argument
and `$id` as the second.

The class `Modules\Blogs\Routes` must extend the `Route` class. And each given **public** methods are treated as a
route. The method name is the route name and the method body is the route handler. Any underscores are treated as a '/'
in the given path. Here's an example:

```php
namespace Modules\Blogs;

use Core\Router\Route;

class Routes extends Route {

    /** Will be called on '/blog' */
    public function index(): void {
        echo 'Blog index';
    }

    /** Will be called on '/blog/article' paths */
    public function article( string $id = ''): void {
        echo 'Will show blog article with id: ' . $id;
    }
    
    /** Will be called on '/blog/article/edit/123' paths */
    public function article_edit( string $id = ''): void {
        echo 'Will edit blog article for ' . $id;
    }
    
}
```

```php
    $router->add('/hello', 'App\Controllers\HelloController::index');
```
