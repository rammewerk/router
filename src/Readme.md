# Router

A simple, flexible and powerful router for PHP.

In it simple form you can define a route with a closure, like many other routers for PHP:

```php
$router->add('/blog', function() {
    echo 'Welcome to my blog!';
});
```

What makes this router unique, is its ability to define one or multiple classes that handle routes based on its public
methods.

```php
namespace Module\Blog;

class Routes extends \Rammewerk\Component\Route {

    public function index(): void {
        echo 'Welcome to my blog!';
    }
    
}
```

```php
$router->add('/hello', \Module\Hello\Routes::class);
```

Now you can easily define new routes or logic by adding or changing the given methods.

Let's add a new method to the `Module\Blog` class that handles the path `/blog/article/{id}`.

```php
public function article( string $id = '' ): void {
    echo 'This is article ' . $id;
}
```

Basically, all `public` methods in the Route class will translate to a path. So in the example above, where we have
defined a class for the `/blog` path. Any method name will translate to whatever comes after the `/blog/` path.

For instance, the method `article` will translate to `/blog/article`. Any methods with underscore will translate to a
slash. So the method `article_detail` will translate to `/blog/article/detail`.

The router will also try to resolve any paths that are not defined in the class. So if a request is made
to `/blog/author`, but the class does not have a method called `author`, the router will call the `index` method, and
pass `author` as the first argument. A request to `/blog/article/archive/{$id}`, where the method `article_archive`
doesn't exist, but the `article` method does, will resolve to the `article` method, and pass `archive` as first argument
and `$id` as the second.

```php

Any parameters will be passed to the
method as arguments. So a request for `/blog/article/{category}/{id}/` will translate to the method `article` with
the arguments `category` and `id`: `public function article( string $category = '', string $id = '' ): void`.

What makes this router unique is its ability to define one or multiple simple route class that handles multiple routes.

But you can also define a class to handle routes for a given path. Which makes the router very flexible for modular
applications. Let's say you have a module called `Blog` and you want to handle all routes for the blog in a class

```php
    $router->add('/blog', \Modules\Blogs\Routes:class);
```

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
        echo 'Will show blog profile for ' . $id;
    }
    
    /** Will be called on '/blog/article/edit/123' paths */
    public function article_edit( string $id = ''): void {
        echo 'Will edit blog profile for ' . $id;
    }
    
}
```

```php
    $router->add('/hello', 'App\Controllers\HelloController::index');
```

Initialize the router

```
    $router = new \Core\Router\Router();
```

Parameters:

1. Optional route class
2. Optional closure to handle constructing route class

http://app.bonsy.dk/home/John/McKane

```
$router->add( '/home', static function ($firstName='', $lastName='') {
    echo "Hello $firstName $lastName";
} );
```

Will output: «Hello John McKane»

