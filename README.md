Rammewerk Router
====

Rammewerk Router is a **lightweight**, **high-performance PHP router** designed for modern applications. It prioritizes
**fast
route resolution, minimal overhead**, and a straightforward setup. Built for **PHP 8.4**, it takes a modern, class-based
approach while remaining flexible and powerful in its minimalism.

With **minimal configuration** required, Rammewerk Router is **easy to set up** while still offering a wide range of
features
like type-safe parameters, dependency injection, and middleware. It provides **just the right amount of structure**
without
unnecessary complexity - delivering performance and flexibility in a simple, intuitive package.

#### Key Features:

- **Class-Based Routing**: Organize routes cleanly and intuitively.
- **Attribute-Based Routing**: Define routes directly in classes and methods for a clean, declarative approach.
- **Type-Safe Parameters**: Smart detection of route dependencies and parameters.
- **Middleware Support**: Add functionality like authentication or logging.
- **Minimal Yet Powerful**: Focused on simplicity while offering the flexibility you need.

## Table of Contents

- [Project Goals](#-project-goals)
- [Getting started](#-getting-started)
    - [Install](#install)
    - [Requirements](#requirements)
    - [Usage](#usage)
- [Basic Routing](#-basic-routing)
- [Class-based Routing](#-class-based-routing)
    - [Define Subpaths with Class Methods](#define-subpaths-with-class-methods)
    - [Dynamic Path Segments](#dynamic-path-segments)
    - [Wildcard parameters](#wildcard-parameters)
    - [Parameter class dependencies](#parameter-class-dependencies)
    - [Binding a Path to a Specific Method](#binding-a-path-to-a-specific-method)
    - [Wrapping Up Class-Based Routing](#wrapping-up-class-based-routing)
- [Dependency Injection](#-dependency-injection)
- [Middleware](#-middleware)
    - [Request Handling](#request-handling)
    - [Group Middleware](#group-middleware)
    - [Wrapping Up Middleware](#wrapping-up-middleware)
- [Dispatching and Response](#-dispatching-and-response)
- [Method-Specific Request Handling](#-method-specific-request-handling)
- [Powerful Parameter Handling](#-powerful-parameter-handling)
- [Performance and Speed](#-performance-and-speed)
- [Closure-Based Routes](#-closure-based-routes)
- [PSR-7 & PSR-15 Support](#-psr-7--psr-15-support)
- [Attribute-Based Routing](#-attribute-based-routing)
- [Benchmark](#-benchmark)

## 🎯 Project Goals

These goals reflect what Rammewerk strives to achieve across all its components:

- **Lightweight & Fast**: Small, focused and compact library with zero bloat, built for speed.
- **Plug-and-Play**: Works out of the box with minimal configuration.
- **Minimal & Understandable**: Simple code that’s easy to read, adapt, and even rewrite for your own projects.
- **Flexible by Design**: Add your own implementations and customize it to suit your needs.
- **Open for Collaboration**: Fork it, explore it, and contribute back with pull requests!

By using Rammewerk, you get a minimal yet powerful foundation that’s easy to build on and improve. Let’s dive in! 🔧✨

## 🚀 Getting Started

### Install

Install Rammewerk Router via composer:

```bash
composer require rammewerk/router
```

### Requirements

- Requires PHP 8.4+.
- Server must route all requests to a single PHP file (e.g., index.php) using Caddy, Nginx, or Apache.
- Use a Dependency Injection container like [Rammewerk Container](https://github.com/rammewerk/container) for managing
  class instances.

### Usage

```php
use Rammewerk\Router\Router;

// Define your dependency resolver.
// This is a closure that receives a class string
// and  must return an instance of that class.
$di_container = static fn( string $class) => new $class() );

// Create Router
$router = new Router($di_container);

// Define routes
// ...

// Go!
$response = $router->dispatch();

// Handle response....

```

## 🧭 Basic Routing

While the Rammewerk Router is designed for class-based routing, it also supports closures.

Here’s a simple example:

```php
$router->add('/hello', function() {
    return 'Hello World!';
});
```

This matches `/hello`, triggers the closure, and returns “Hello World!”

## 🏗️ Class-based Routing

Class-based routing is the core feature of Rammewerk Router. It maps paths and their nested routes directly to a class,
making it both powerful and flexible.

Here’s how it works:

```php
$router->add('/profile', ProfileRoute::class);
```

With this setup, the `ProfileRoute` class will handle all requests to `/profile` (and its sub-paths, unless overridden
by other routes).

Here’s an example of a simple class for the `/profile` path:

```php
namespace Routes;

class ProfileRoute {

    public function index(): string {
        return 'You visited /profile';
    }

}
```

The `index()` method is the default handler for the base path of a class-based route. In this case, accessing `/profile`
triggers the `index()` method.

You can also define class routes with a single `__invoke()` method. This will be called if no other method matches or is
defined. The `__invoke()` method is best used when the class doesn’t have additional route methods, keeping it simple
and focused.

### Define Subpaths with Class Methods

To handle a path like `/profile/settings/notifications`, simply add a method to your class matching the subpath
structure:

```php
class ProfileRoute {

    // Previous methods

    public function settings_notifications(): string {
        return 'You visited /profile/settings/notifications';
    }

}
```

### Route attributes as alternative

You can also use the `#[Route]` if you prefer a more declarative approach:

```php
use Rammewerk\Router\Foundation\Route;

#[Route('/profile')] // <-- must match based path given in add()
class ProfileRoute {

    // Will match /profile/settings/notifications
    #[Route('/settings/notifications')] // <-- Attribute
    #[Route('/profile/settings/notifications')] // <-- This also works
    public function notifications(): string {
        return 'You visited /profile/settings/notifications';
    }
    
}
```

Each segment after the base path (`/profile`) maps to a method, with subpath segments replaced by underscores (`_`).

### Dynamic Path Segments

You can define dynamic subpaths by adding parameters to your method:

```php
class ProfileRoute {

    public function edit( int $id ): string {
        return "You visited /profile/edit/$id";
    }
    
}
```

Accessing `/profile/edit/123` triggers the `edit()` method with the parameter `$id` = `123`.

### Wildcard parameters

Additionally, you can use wildcard parameters (`*`) to map subpaths to parameters. For example, handling
`/profile/123/edit` can be done like this:

```php
$router->add('/profile/*/edit', ProfileEditRoute::class);
```

Wildcard parameters are mapped in order, alongside subpaths. For example, `/profile/123/edit/notification` results in
parameters `123` and `notification`.

## Parameter types

- Parameter names don’t matter, but their order does.
- If a parameter isn’t in the path, it must be **optional** or **nullable** to match.
- Type hints are supported, and path segments are converted to match (`int`, `float`, `bool`, `string`). Undefined or
  mixed defaults to string.
- Parameters that can't convert to defined type are rejected, and route won't match.
- Paths must match exactly. For example, `/profile/edit/123` won’t match `/profile/edit/123/something`.
- Use a variadic parameter (`...$args`) to allow extra subpaths to match.

#### Enums

You can use both PHP backed enumerations and regular enums as route parameters because Rammewerk will convert them
automatically based on the given path argument.

```php
# Backed enum
enum OrderStatusEnum: string {
    case OPEN = 'open'; // Path Argument /open/ will match this
    case CLOSED = 'closed'; // Path Argument /closed/ will match this
}

# Regular enum
enum OrderShipmentEnum {
    case SHIPPED;   // Path Argument /shipped/ will match this
    case NOT_SHIPPED; // Path Argument /not_shipped/ will match this
}

# Example:

#[Route('/orders')]
class Orders {

    #[Route('/item/*/status')] // Will support paths like: /item/123/status/open
    public function itemStatus( int $item_id, OrderStatusEnum $status ): string {
        return "The status for item $item_id is $status->value";
    }
     
}
```

The router will automatically convert the parameter to the type specified in the method signature.

### Parameter class dependencies

You can use classes as parameters, and the router will resolve them via the dependency handler set during
initialization:

```php
class ProfileRoute {
    public function edit( Profile $profile, Template $template, int $id ): Response {
        $user = $profile->find($id);
        return $template->render('profile/edit', [$user]);
    }
}
```

The order of class dependencies doesn’t matter, but parameters extracted from the path must be in the correct order.

### Binding a Path to a Specific Method

You can bind a route to a specific class method in its class by defining the method in the route definition:

```php
$router->add(...)->classMethod('edit');
```

This ensures the `edit()` method of the `ProfileRoute` class is always called when `/profile/settings` is accessed.

### Wrapping Up Class-Based Routing

Less configuration? Checked! 🎉 Class-based routing keeps things straightforward. Adding new routes is as easy as
defining methods in your handler
classes. With type safety, support for required and optional parameters, and the flexibility of wildcards, you can build
routes that adapt to your needs without unnecessary complexity.

---

## 📦 Dependency Injection

To manage dependencies for class-based and closure-based routes, as well as middleware, the router requires a dependency
resolver. You *must* set this up during initialization by passing a closure to the constructor. This closure receives a
class name and returns an instance of that class.

> For a simple and efficient solution, check out [Rammewerk Container](https://github.com/rammewerk/container).

```php
$router = new Router( static fn( string $class_string ) => $container->create($class_string) );
```

This approach keeps your routes clean and ensures seamless dependency handling.

## 🛡️ Middleware

Middleware adds functionality to your routes, like authentication, logging, or caching, without cluttering your route
classes. It acts as a layer that processes requests before they reach your route handler or modifies responses
afterward.

Here’s how to add middleware to a route:

```php
$router->add('/', HomeRoutes::class)->middleware([
    AuthMiddleware::class,
    LoggerMiddleware::class,
]);
```

Middleware runs in the order it’s defined. Each middleware must have a handle method that processes the request and
calls the next closure to continue:

```php
class AuthMiddleware {
    public function handle(Request $request, \Closure $next) {
        // Do auth stuff
        return $next($request);
    }
}
```

**Note**: Even though the request object is optional (`object|null`), your middleware must define it as the first
parameter of the `handle()` method. The `handle()` method **must always** receive two arguments: the given request
object (or
null) and the next closure to call.

> Rammewerk Router also supports PSR-15 MiddlewareInterface. See [PSR-15 Support](#-psr-7--psr-15-support) for more
> information.

### Request Handling - Dispatching

The router passes a request object to each middleware and the route handler. The request type is flexible; you can
pass any object during dispatch:

```php
$router->dispatch('/profile', new ServerRequest());
```

While optional, it’s good practice to type-hint the request in your handle method and ensure it matches the request
class passed during dispatch.

> Rammewerk Router also supports PSR-7 ServerRequestInterface. See [PSR-7 Support](#-psr-7--psr-15-support) for more

### Group Middleware

Use the `group()` method to apply middleware to multiple routes at once. This keeps your code clean and avoids
repetitive middleware declarations.

Here’s an example:

```php
$router->group(function(Router $r) {
    $r->add('/products', ProductRoutes::class);
    $r->add('/users', fn() => 'Users listing');
})->middleware([
    AuthMiddleware::class,
    LoggerMiddleware::class
]);
```

In this example, both `/products` and `/users` routes share the same middleware (`AuthMiddleware` and
`LoggerMiddleware`), applied in the defined order.

If you define middleware on a route inside the group, it will run **before** the group’s middleware:

```php
$r->group(function (Router $r) {
    $r->add('/products', ProductRoutes::class)->middleware([AuthMiddleware::class]);
    // More routes
})->middleware([LoggerMiddleware::class]);
```

Here, `AuthMiddleware` runs first for `/products`, followed by `LoggerMiddleware` from the group. This lets you control
the middleware order for each route.

### Wrapping Up Middleware

Little configuration? Checked! 🎉 Middleware in the router is flexible and straightforward, letting you add layers to
your routes without overcomplicating things. You’re free to implement middleware however you like — no restrictions on
which request class to use or how to handle it.

---

## 🚀 Dispatching and Response

Dispatching routes is simple: just call the dispatch method on your router instance.

```php
try {
    $response = $router->dispatch( path: '/', serverRequest: $request );
    // Handle response
} catch (InvalidRoute $e) {
    // Handle 404 errors or log unmatched paths
} catch (Throwable $e) {
    // Handle other application errors, or let it bubble up
}
```

- The path parameter is matched against your routes. If no match is found, an InvalidRoute exception is thrown, letting
  you handle 404s or similar responses.
- The request parameter is optional and passes a request object to middleware and route handlers for processing.
- The response is returned from the dispatch method, which can be any type. It’s up to you to handle it in your
  application.

## 🚦 Method-Specific Request Handling

This router doesn’t predefine request types like `GET` or `POST`. It simply passes any request to the route. If you want
to implement a `get()`/`post()`/`delete()` style structure, you can achieve it by adding a wrapper or using middleware
to handle specific request methods. This gives you full flexibility to define request handling as you see fit.

## 🧩 Powerful Parameter Handling

This router shines with its robust and flexible parameter system:

- **Type-safe & Intuitive**: Supports type hints, union types, and automatic conversion for seamless parameter handling.
- **Dependency-Friendly**: Reflects parameters in methods and closures, allowing seamless integration with your own DI
  container for maximum flexibility and adaptability.
- **Wildcard Simplicity**: Use * to capture dynamic segments - no regex needed, and parameter types ensure effortless
  refactoring and clarity.
- **No Dictated Names**: Parameters don’t rely on specific names, giving you freedom and flexibility.
- **Support for Enums**: Support for both backed enums and regular enums. Backed enums are automatically converted to
  it's type, where the given parameter argument is called with ::tryFrom(), while regular enums are matched with their
  case.

This level of type safety, combined with flexible wildcards, is rare in other routers. It’s designed to make routing
both powerful and effortless! 🚀

## ⚡ Performance and Speed

Rammewerk Router is designed to stay lean and move fast.

1. It uses simple arrays to store routes and quickly narrows down
   matching paths by comparing only relevant segments.
2. Regex patterns are sorted by length so more specific routes are
   tested first.
3. Reflection is only performed once a route is confirmed, so there’s no overhead for routes that don’t match
4. With minimal internal complexity, no bulky dependencies, and a single-file core, the router focuses on doing one job
   well without slowing you
   down in production.

> Integrating [Rammewerk Container](https://github.com/rammewerk/container) can boost speed even more, thanks to its
> lazy-loading approach. It’s also one of the fastest DI containers out there, as shown in benchmarks.

## 🪶 Closure-Based Routes

While class-based routing is the core feature of Rammewerk Router, closure-based routes can be useful for simple,
standalone handlers or quick prototypes.

These routes still benefit from the same powerful parameter handling as
class-based routes, including dependency injection of classes and type-hinted subpath parameters. Middleware can also be
applied seamlessly to closure-based routes, ensuring consistent behavior across your application.

Here’s an example:

```php
// Define a closure-based route with parameter handling and middleware
$router->add('/greet', function (string $name, Logger $logger): string {
  $logger->info("Greeting user: $name");
  return "Hello, $name!";
})->middleware([
    AuthMiddleware::class,
]);

// Dispatch the router
$router->dispatch('/greet/John', new Request());
```

Key Points:

- **Parameter Handling**: Subpath parameters like {name} are automatically resolved and type-checked.
- **Dependency Injection**: Classes like Logger are injected via the resolver.
- **Middleware**: Layers such as AuthMiddleware can be applied, ensuring functionality like authentication or logging is
  handled consistently.

Closure-based routes provide a lightweight yet flexible alternative when you don’t need a dedicated class handler.

## 🌐 PSR-7 & PSR-15 Support

The **Rammewerk Router** includes an extended class, `PsrRouter`, designed specifically for applications requiring
**PSR-7** (HTTP Message Interface) and **PSR-15** (Middleware and Request Handlers) compliance. Use PsrRouter as a
drop-in replacement for the default Router when working with PSR-compliant middleware and request handlers.

#### Highlights

- **PSR-7**: Pass compliant ServerRequestInterface objects to handlers and middleware.
- **PSR-15**: Add reusable, standards-based MiddlewareInterface layers.
- **Pipeline**: Middleware is executed sequentially, ensuring proper request and response processing.

Here's an example of PSR-7 & PSR-15 Usage:

```php
use Rammewerk\Router\Extension\PsrRouter;

$router = new PsrRouter(static fn(string $class) => $container->get($class));

// Add PSR middleware and routes
// HomeRoute handle method must return a PSR-7 ResponseInterface
$router->add('/home', HomeRoute::class)->middleware([
    AuthMiddleware::class, // Implements PSR-15 MiddlewareInterface
]);

// $serverRequest is a PSR-7 ServerRequestInterface
$response = $router->dispatch('/', $serverRequest);

header('Content-Type: ' . $response->getHeaderLine('Content-Type'));
echo $response->getBody();
```

The PsrRouter not only provides PSR-7 and PSR-15 support but also serves as an example of how to extend the Rammewerk
Router to implement custom solutions tailored to specific application needs. This showcases the flexibility of the
Rammewerk Router’s architecture, enabling developers to adapt it to various standards or unique requirements.

> **NOTE:** If your project requires `getAttribute()` or similar functionality to handle parameters directly, the
> Rammewerk Router might not be the ideal solution for your needs. This router is designed for flexibility and handles
> parameters differently, with logic tailored to its specific architecture. If you’re looking for a router that supports
> named parameters or simpler routing logic, you may want to consider alternatives that are more closely aligned with
> your
> project’s requirements.


Here’s an updated section for your README documentation to reflect the usage and rules for Route attributes:

## 🛠️ Route Attributes

The `#[Route]` attribute allows you to define routes directly on classes and methods for a clean and declarative
approach to routing.

**Class-Level Route Attribute:**

- The `#[Route]` attribute **must** be defined on the class level.
- The route path in the class attribute **must** match the base segment provided in the `add()` method. If not, the
  class
  will not be reflected for route attributes. This ensures faster reflection and avoids ambiguity.

```php
use Rammewerk\Router\Foundation\Route;

#[Route('/dashboard')]
class DashboardRoute {
    // ...
}

// Base segment ('/dashboard') matches class-level #Route('/dashboard')
$router->add('/dashboard', DashboardRoute::class); 
```

**Method-Level Route Attribute:**

- Use `#[Route]` attributes on methods to define subroutes. These routes follow the same wildcard (`*`) and trailing
  parameters logic as manually defined routes.
- The parameters for wildcard and trailing segments are passed to the method for validation and handling, just like we
  do on closures and class-based routing, described above.

```php
#[Route('/dashboard')]
class DashboardRoute {

    #[Route('/stats/*/details')]
    public function stats(string $param1, string ...$wildcards): Response {
        // Example: `/dashboard/stats/123/details/flag1/flag2`
    }

    #[Route('/profile')]
    public function profile( int $id ): string {
        return "Profile page for user ID $id";
    }
    
    public function unknown(): string {
        return 'Will never be called, no matching route found';
    }
    
}
```

#### Why This Approach?

- **Performance**: The router only reflects classes if the base segment matches the class-level route attribute. This
  ensures faster processing and avoids unnecessary computation.
- **Clarity**: Explicitly linking class-level routes to base segments keeps the logic predictable and easy to debug.
- **Flexibility**: You can use wildcards and trailing parameters to build dynamic and flexible route patterns.

## 🏃‍♀️ Benchmark

Rammewerk Router is designed for speed and minimal overhead, ensuring fast route resolution even in complex scenarios.

This benchmark test was conducted on **PHP 8.4 CLI**, calling a **PHP-FPM server with opcache enabled** via curl. Each
router was **warmed up** before testing to ensure a fair comparison.

#### Benchmark Setup:

- **150 different routes** with a mix of **simple and complex** paths, including a **dynamic parameter**.
- Each route was called **500 times**, totaling **75,000 route resolutions per test**.
- The **median time** was recorded after **30 test runs** for each package.

| Rank | Container            | Time (ms) | Time (%) | Peak Memory (MB) | Peak Memory (%) |
|------|----------------------|-----------|----------|------------------|-----------------|
| 1    | **Rammewerk Router** | 79.959    | 100%     | 1.287            | 100%            |
| 2    | **FastRoute**        | 175.513   | 220%     | 0.788            | 61%             |
| 3    | **PHRoute**          | 192.192   | 240%     | 0.937            | 73%             |
| 4    | **Symfony Router**   | 491.643   | 615%     | 0.817            | 64%             |

#### Key Takeaways:

- **Rammewerk Router outperformed all tested routers** in this scenario, offering **more than twice the speed of
  FastRoute** and
  significantly faster execution than Symfony Router.
- **Memory usage was slightly higher** compared to some alternatives, but the trade-off resulted in substantial
  performance
  gains.
- FastRoute showed competitive results when handling a small number of different routes, performing slightly better in
  such cases.
- Rammewerk Router particularly excels in complex routing scenarios, maintaining high efficiency even with numerous
  route variations.

More extensive benchmarks and detailed performance tests will be available in a dedicated GitHub repository soon. 🚀