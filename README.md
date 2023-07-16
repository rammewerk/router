# Rammewerk Router

The **Rammewerk Router** is a PHP routing library. It lets you manage routes in your web app and includes features like
class-based routing and custom parameter management. It's compatible with custom dependency injection systems too. It
helps in creating dynamic and secure routing systems, making it easy to use for both new and experienced developers.

## Installation

Install Rammewerk Router via composer:

```
composer require rammewerk/router
```

Basic usage
----

In its simple form you can define a route with a closure, like many other routers for PHP:

```php
$router = new Rammewerk\Component\Router\Router();

$router->add('/', function() {
    echo 'Hello index';
});

$router->add('/blog/hello', function() {
    echo 'Welcome to my blog!';
});

$router->find( $path );
```

Or through a class-based route:

```php
$router = new Rammewerk\Component\Router\Router();

$router->add('/', RouteActions::class, null, false);

$router->find( $path );
```

```php
class RouteActions {

    public function index(): void {
        echo 'Hello index';
    }
    
    public function blog_hello(): void {
        echo 'Welcome to my blog!';
    }
    
}
```

## Understanding the Rammewerk Router Path Handling

The Rammewerk Router has a distinctive approach to path handling that sets it apart from traditional PHP routers. Its
unique logic allows for more dynamic and flexible routing configurations.

### Dynamic Path Matching and Parameter Extraction

When a request comes in, the router initially attempts to match the entire request URL to a defined route. For instance,
given a request URL like `/product/item/123`, the router will first look for a route matching this exact pattern.

If such a route does not exist, the router doesn't simply throw a "route not found" error. Instead, it employs a
strategy of "sequential trimming and matching". It starts removing segments from the end of the URL and tries to match
the trimmed URL to a defined route.

In our example, if `/product/item/123` does not match a defined route, the router will trim the last segment (`123`) and
try to match `/product/item`. If this doesn't match either, it will trim `item` and try to match `/product`. Finally, if
no match is found, it will trim `product` and try to match the root route `/`.

### Intelligent Parameter Management

The segments that get trimmed during this process aren't simply discarded. Instead, they're preserved and treated as
parameters for the matched route, in the order they were in the original request URL.

Let's go back to our example. If a match is found for `/product/item`, the segment `123` that was trimmed earlier is not
lost. Instead, it becomes the first parameter for the matched route.

These parameters can then be accessed in the handler for that route, allowing you to create dynamic routes where
segments of the URL path can be used as variables. Here's an example:

```php
$router->add('/product/item', function(string $id) {
    echo "Displaying product item with ID: $id";
});
```

In this scenario, if the user navigates to `/product/item/123`, the router will trigger this route, passing `123` as
the `$id` parameter.

### Fallback Handling for Missing Parameters

What if a parameter is required by the route but the request URL doesn't include it? The Rammewerk Router has a solution
for this too. If a parameter is missing in the request, you can simply define it as optional by providing a default
value. This way, even without the parameter, the route is still considered a match.

This combination of dynamic path matching and intelligent parameter management makes the Rammewerk Router a versatile
and powerful tool for handling complex routing needs in your PHP applications.

## How the router works

#### Handling Different Types of Requests

By default, the router responds to all incoming requests regardless of the HTTP method (such as `GET`, `POST`, etc.)
they employ. This means that the router does not differentiate between request types out of the box.

However, if you need to differentiate and respond differently based on the HTTP method, a simple wrapper can be created
to manage this. The wrapper can define routes at runtime, taking into account the type of the incoming request.

This way, you can customize your application's behavior to suit specific needs based on different types of HTTP methods,
while still leveraging the simplicity and versatility of the router.

Please note that implementing this feature requires a good understanding of HTTP request methods and how to design your
application to respond appropriately to each type.

### Parameter Access: Key Feature

One key feature that sets our router apart from others is its unique method of parameter handling. Understanding this
involves grasping how our router maps a route.

#### Route Mapping Mechanism

Consider a request made to the URL `/product/item/123`. The router initiates its search by attempting to match the
entire route. If a defined route doesn't exist for `/product/item/123`, it modifies its search strategy.

#### Sequential Trimming and Matching

The router starts trimming the path from the end and tries matching again. It first attempts to find a match
for `/product/item`, then `/product`, and finally `/` if previous attempts were unsuccessful.

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

#### Handling dependencies

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
$router->add('/product', ProductRouteActions::class, null, false);
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

## Custom class loader

There is also an option to add a custom dependency handler for route classes. This is only triggered
when a Route class is initialized. The Closure are given the ReflectionClass and should respond
with a fully constructed class of the same type as the ReflectionClass.

```php
    $router->registerClassDependencyLoader( function( \ReflectionClass $class) use ($container) {
        $container->create($class->name);
    });
```

## Authentication of class based routes

If you want to authenticate access to a route before loading the route, you can add a method to the class
called `hasRouteAccess`. This method used to grant access to the route. If method returns false, the access is blocked,
or granted if true. To authorize access to a route class, implement the method `hasRouteAccess`. The router will only
allow requests when `hasRouteAccess` returns `true`.

```php
class BlogRoutes {

    # Access to any routes in this class is only accepted if this is true.
    public function hasRouteAccess(): bool {
        return ! empty($_SESSION['user_id']);
    }
    
    # Will only load if hasRouteAccess is true.
    public function index(): void {
        echo 'Welcome to my secure blog!';
    }
    
}
```

## Adding Routes

```php
$router->add( $path, $handler, $class_method_name, $authenticate)
```

Accepts 4 parameters:

- `path`: The path to the route.
- `handler`: The closure or class-string to handle the route.
- `class_method`: (Optional) Specific method inside a class based router.
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

## Support

If you are having any issues, please let us know. Email at [support@rammewerk.com](mailto:support@rammewerk.com) or open
an issue.





