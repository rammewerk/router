# Rammewerk Router

The **Rammewerk Router** is a PHP routing library. It lets you manage routes in your web app and includes features like
class-based routing and custom parameter management. It's compatible with custom dependency injection systems too. It
helps in creating dynamic and secure routing systems, making it easy to use for both new and experienced developers.

## Installation

Install Rammewerk Router via composer:

```
composer require rammewerk/router
```

Basic usage with class-based routing
----
```php
$router = new Rammewerk\Component\Router\Router();

$router->add('/', RouteActions::class);

$router->find();
```

```php
class RouteActions {

    # Handle empty path as well as other unresolved paths.
    public function index(): void {
        echo 'Hello index';
    }
    
    # Handle paths that starts with '/blog/hello/'
    public function blog_hello(): void {
        echo 'Welcome to my blog!';
    }
    
}
```

In this class any path called, which is not starting with `/blog/hello/` will be resolved to the `index` method.

Closures
----

In its simple form you can define a route with a closure, like many other routers for PHP:

```php
$router->add('/', function() {
    echo 'Hello index';
});

$router->add('/blog/hello', function() {
    echo 'Welcome to my blog!';
});
```

## Understanding the path handling

The Router has a distinctive approach to path handling that sets it apart from traditional PHP routers.

#### Route Mapping Mechanism

Consider a request made to the URL `/product/item/123`. The router initiates its search by attempting to match the
entire route. If a defined route doesn't exist for `/product/item/123`, it modifies its search strategy.

#### Sequential Trimming and Matching

The router starts trimming the path from the end and tries matching again. It first attempts to find a match
for `/product/item`, then `/product`, and finally `/` if previous attempts were unsuccessful.

This is why an empty route `/` is always required to be added before the router starts finding the path.

#### Parameter Capturing

Each trimmed segment from the path is added to the parameters list, in the sequence it appears in the requested path.
So, if the route `/product/item` is a valid one, then `123` is captured as the first parameter.

#### Accessing Parameters in Code

To tap into these parameters, define a `string` parameter within the callback or class method. Here is a PHP example for
better understanding:

```php
$router->add('/product/item', function(string $id) {
    echo "Showing product item with ID: $id";
});
```

In this scenario, `123` is passed to the `$id` parameter when the route `/product/item/123` is requested.

#### Handling Unmatched Routes

Remember, if a parameter like `$id` is required but the request doesn't include it, the router will consider the route
unmatched. To circumvent this, simply make the parameter optional by setting a default value, as
shown: `string $id = ''`. This way, even if the parameter is missing in the request, the route remains valid.

## Configuring Routes

To add new routes, all you need to do is define the route along with its corresponding function or class. Here's how:

```php
$router->add('/page', function( ...$params ) {
    ...
})
```

Note that you don't define any parameters in the route setup. Instead, the function or class method you provide decides
what parameters it needs. When '/page' is visited, the function or method runs with the parameters it has specified.

Here's an example for adding a class-based route:

```php
$router->add('/page', RouterActions::class )
```

## Handling Different Types of Requests

The router handles all web requests, no matter what type (like 'GET' or 'POST'). If you need it to react differently
based on the request type, you can make a wrapper to do that. This helps you to fine-tune your app's actions for
different web request types. Keep in mind that you need to know about web request types and how to design responses to
use this feature.

## Handling dependencies

You can define a register a custom way to handle dependencies by registering your dependency injection container:

```php
$router->registerDependencyLoader( function( string $class_name ) => use($container) {
    return $container->create($class_name);
});
```

Once registered it allows adding dependencies to other classes:

```php
$router->add("/product/item/", function( ProductController $product, string $id ) {
    $product->showItem( $id );
});
```

Note that the first given string parameter is the first extracted parameter from a given path.

## Class-based routing

What makes this router unique, is its ability to define one or multiple classes that handle routes, based on its public
methods.

It's offer a quick way to work with routing.

Let's define a simple Route class that handles all routes that starts with `/product/`:

```php
namespace Module\Product;

class ProductRouteActions {

    # Will handle /product/
    public function index(): void {
        echo 'Default product page when visiting base level of route: /';
    }
    
    # Will handle /product/item/{id}
    public function item( string $id ): void {
        echo "Implement loading of product item with id $id";
    }
    
    # Will handle /product/list/all/
    public function list_all(): void {
        echo "Implement list of all products"
    }
    
}
```

To register this class, we can define it so:

```php
$router->add('/product', ProductRouteActions::class);
```

If we later on wants to add a new product route to handle product update, we could simply add a new method to our class:

```php
class ProductRouteActions {

    ...
    
    # Will handle /product/update/{id}
    public function update( string $id ): void {
        # Handle product update for product of ID = $id
    }
    
}
```

## Setting up a Custom Class Loader (Optional)

It's possible to customize how route classes handle their dependencies. This is done by using a class dependency loader
which runs when a route class starts initializing.

This loader receives a `ReflectionClass` object, mirroring the class being loaded. The router already created
this `ReflectionClass`, saving you processing time. This feature is particularly useful in modular systems for
inspecting class namespaces and loading the right dependencies.

Here's how to register a custom loader:

```php
$router->registerClassDependencyLoader( function( \ReflectionClass $class) use ($container) {
    return $container->create($class->name);
});
```

In this example, `$container->create($class->name);` creates an instance of the class.

Note: This is an optional feature. If you don't set up a custom class loader, the router will use its default
loader (`$router->registerDependencyLoader`). So, your route classes will still load their dependencies, even without a
custom class loader.

## Setting Up Route Authentication

For adding authentication checks before a route is accessed, you can define a default method. This method will run for
every class-based route before it's loaded.

Here's how to register the default method:

```php
$router->classAuthenticationMethod('hasRouteAccess');
```

Now, the router will run the 'hasRouteAccess' method before it loads any class-based route (note: this doesn't apply to
closure routes). This method should return `true` or `false`, indicating whether the route should be accessible.

```php
class SecureBlogRoutes {

    // Access to any route in this class is granted if this returns true.
    public function hasRouteAccess(): bool {
        return ! empty($_SESSION['user_id']);
    }
    
    // This route will only load if 'hasRouteAccess' returns true.
    public function index(): void {
        echo 'Welcome to my secure blog!';
    }
    
}
```

Once you register the default authentication method, every class-based route must implement it. If a route class doesn't
need authentication, have its 'hasRouteAccess' method return `true`, this will allow all requests to pass.

You can catch `RouteAccessDenied` exceptions and handle them according to your application's needs. For example, you may
want to redirect the user to a login page or show an error message if access is approved.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Please note that this project is provided "as is" without warranty of any kind, either expressed or implied, including,
but not limited to, the implied warranties of merchantability and fitness for a particular purpose. The entire risk as
to the quality and performance of the project is with you.

## Support

If you are having any issues, please let us know. Email at [support@rammewerk.com](mailto:support@rammewerk.com) or open
an issue.