Rammewerk Router
====

Rammewerk Router is a **lightweight**, **high-performance PHP router** designed for modern applications. It prioritizes **fast route resolution, minimal overhead**, and a straightforward setup. Built for **PHP 8.4**, it uses an **attribute-only routing approach** for clean, declarative route definitions.

With **minimal configuration** required, Rammewerk Router is **easy to set up** while offering powerful features like type-safe parameters, dependency injection, and middleware. It provides **just the right amount of structure** without unnecessary complexity - delivering performance and flexibility in a simple, intuitive package.

#### Key Features:

- **Attribute-Only Routing**: Define routes declaratively using PHP attributes on methods
- **Flexible Entry Points**: Map entry points to classes without requiring class-level route definitions
- **Type-Safe Parameters**: Smart detection of route dependencies and parameters with full type safety
- **Middleware Support**: Add route-specific or group middleware for authentication, logging, and more
- **Dependency Injection**: Full container support with late binding for worker mode compatibility
- **Multiple Routes per Method**: Support multiple route attributes on the same method
- **Minimal Yet Powerful**: Focused on simplicity while offering the flexibility you need

## Table of Contents

- [Project Goals](#-project-goals)
- [Getting Started](#-getting-started)
  - [Install](#install)
  - [Requirements](#requirements)
  - [Basic Usage](#basic-usage)
- [Attribute-Based Routing](#-attribute-based-routing)
  - [Entry Points](#entry-points)
  - [Method Routes](#method-routes)
  - [HTTP Methods](#http-methods)
  - [Multiple Routes](#multiple-routes)
  - [Route-Specific Middleware](#route-specific-middleware)
- [Dynamic Parameters](#-dynamic-parameters)
  - [Type-Safe Parameters](#type-safe-parameters)
  - [Wildcard Parameters](#wildcard-parameters)
  - [Parameter Types](#parameter-types)
  - [Enums](#enums)
- [Dependency Injection](#-dependency-injection)
  - [Container Setup](#container-setup)
  - [Worker Mode Support](#worker-mode-support)
- [Middleware](#-middleware)
  - [Route Middleware](#route-middleware)
  - [Group Middleware](#group-middleware)
  - [Middleware Implementation](#middleware-implementation)
- [Dispatching](#-dispatching)
- [Performance](#-performance)

## 🎯 Project Goals

These goals reflect what Rammewerk strives to achieve across all its components:

- **Lightweight & Fast**: Small, focused and compact library with zero bloat, built for speed
- **Plug-and-Play**: Works out of the box with minimal configuration
- **Minimal & Understandable**: Simple code that's easy to read, adapt, and even rewrite for your own projects
- **Flexible by Design**: Add your own implementations and customize it to suit your needs
- **Open for Collaboration**: Fork it, explore it, and contribute back with pull requests!

By using Rammewerk, you get a minimal yet powerful foundation that's easy to build on and improve. Let's dive in! 🔧✨

## 🚀 Getting Started

### Install

Install Rammewerk Router via composer:

```bash
composer require rammewerk/router
```

### Requirements

- Requires PHP 8.4+
- Server must route all requests to a single PHP file (e.g., index.php) using Caddy, Nginx, or Apache
- Use a Dependency Injection container like [Rammewerk Container](https://github.com/rammewerk/container) for managing class instances

### Basic Usage

```php
use Rammewerk\Router\Router;

// Define your dependency resolver
// This closure receives a class string and must return an instance of that class
$container = static fn(string $class) => new $class();

// Create Router
$router = new Router($container);

// Define entry points to your classes
$router->entryPoint('/api/users', UserController::class);
$router->entryPoint('/dashboard', DashboardController::class);

// Dispatch the request
$response = $router->dispatch();

// Handle response...
```

## 🏷️ Attribute-Based Routing

Rammewerk Router uses **attribute-only routing**. Routes are defined using PHP attributes on methods, providing a clean and declarative approach.

### Entry Points

Entry points map URL patterns to classes. The router will scan the class for `#[Route]` attributes on methods:

```php
// Map /api/users/* to UserController class
$router->entryPoint('/api/users', UserController::class);

// With wildcards for dynamic segments
$router->entryPoint('/profile/*/settings', ProfileSettingsController::class);
```

### Method Routes

Define routes using the `#[Route]` attribute on methods. The path must be the complete route path:

```php
use Rammewerk\Router\Foundation\Route;

class UserController {

    #[Route('/api/users')]
    public function list(): array {
        return ['users' => []];
    }

    #[Route('/api/users/create')]
    public function create(): string {
        return 'User creation form';
    }

    #[Route('/api/users/*/edit')]
    public function edit(int $userId): string {
        return "Editing user: $userId";
    }
}
```

### HTTP Methods

Routes can be restricted to specific HTTP methods using the `methods` parameter in the `#[Route]` attribute. If no methods are specified, the route accepts all HTTP methods.

```php
use Rammewerk\Router\Foundation\Route;

class ApiController {

    // Accepts all HTTP methods (default behavior)
    #[Route('/api/status')]
    public function status(): array {
        return ['status' => 'ok'];
    }

    // Only accepts GET requests
    #[Route('/api/users', methods: ['GET'])]
    public function getUsers(): array {
        return ['users' => []];
    }

    // Only accepts POST requests
    #[Route('/api/users', methods: ['POST'])]
    public function createUser(): array {
        return ['message' => 'User created'];
    }

    // Accepts multiple specific methods
    #[Route('/api/users/*', methods: ['PUT', 'PATCH'])]
    public function updateUser(int $userId): array {
        return ['message' => "User $userId updated"];
    }

    // Only accepts DELETE requests
    #[Route('/api/users/*', methods: ['DELETE'])]
    public function deleteUser(int $userId): array {
        return ['message' => "User $userId deleted"];
    }
}
```

#### Same Path, Different Methods

You can define multiple methods that handle the same path but different HTTP methods:

```php
class ResourceController {

    #[Route('/api/resource', methods: ['GET'])]
    public function get(): array {
        return ['action' => 'get'];
    }

    #[Route('/api/resource', methods: ['POST'])]
    public function create(): array {
        return ['action' => 'create'];
    }

    #[Route('/api/resource', methods: ['PUT'])]
    public function update(): array {
        return ['action' => 'update'];
    }

    #[Route('/api/resource', methods: ['DELETE'])]
    public function delete(): array {
        return ['action' => 'delete'];
    }
}
```

#### Method Validation

If a route specifies allowed methods and a request comes with an unallowed method, the router will throw an `InvalidRoute` exception with details about which methods are allowed.

### Multiple Routes

A single method can handle multiple route paths by using multiple `#[Route]` attributes:

```php
class ProductController {

    #[Route('/products')]
    #[Route('/items')]
    #[Route('/catalog')]
    public function list(): array {
        return ['products' => []];
    }
}
```

### Route-Specific Middleware

Add middleware directly to routes using the `#[Route]` attribute:

```php
use Rammewerk\Router\Foundation\Route;

class AdminController {

    #[Route('/admin/dashboard', [AuthMiddleware::class, AdminMiddleware::class])]
    public function dashboard(): string {
        return 'Admin Dashboard';
    }

    #[Route('/admin/users', [AuthMiddleware::class])]
    public function users(): array {
        return ['users' => []];
    }
}
```

## 🎯 Dynamic Parameters

### Type-Safe Parameters

Parameters are automatically extracted from URL segments and converted to the specified types:

```php
class UserController {

    #[Route('/users/*/profile')]
    public function profile(int $userId): string {
        return "User profile for ID: $userId";
    }

    #[Route('/users/*/posts/*')]
    public function userPost(int $userId, int $postId): string {
        return "User $userId, Post $postId";
    }
}
```

### Wildcard Parameters

Use `*` in your entry point or route path to capture dynamic segments:

```php
// Entry point with wildcards
$router->entryPoint('/blog/*/comments/*', CommentController::class);

class CommentController {

    #[Route('/blog/*/comments/*')]
    public function show(string $slug, int $commentId): string {
        return "Blog: $slug, Comment: $commentId";
    }
}
```

### Parameter Types

The router supports automatic type conversion for:

- `string` - Default type
- `int` - Validates and converts to integer
- `float` - Validates and converts to float
- `bool` - Validates and converts to boolean
- `DateTime` - Parses date strings
- `DateTimeImmutable` - Parses date strings to immutable objects
- Custom classes - Resolved via dependency injection
- Enums - Both backed and non-backed enums

### Enums

Both backed and non-backed enums are supported:

```php
// Backed enum
enum StatusEnum: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

// Non-backed enum
enum TypeEnum {
    case USER;
    case ADMIN;
}

class UserController {

    #[Route('/users/*/status/*')]
    public function updateStatus(int $userId, StatusEnum $status): string {
        return "User $userId status: {$status->value}";
    }

    #[Route('/users/*/type/*')]
    public function setType(int $userId, TypeEnum $type): string {
        return "User $userId type: {$type->name}";
    }
}
```

## 📦 Dependency Injection

### Container Setup

The router requires a dependency resolver to manage class instances:

```php
use Rammewerk\Router\Router;

// Simple container
$container = static fn(string $class) => new $class();

// Or use a full DI container
$container = static fn(string $class) => $diContainer->get($class);

$router = new Router($container);
```

Classes can be injected into route methods:

```php
class UserController {

    #[Route('/users/*/profile')]
    public function profile(UserService $userService, int $userId): Response {
        $user = $userService->find($userId);
        return new JsonResponse($user);
    }
}
```

### Worker Mode Support

For long-running processes like **FrankenPHP workers**, inject fresh containers to prevent singleton leakage:

```php
// Setup once during worker initialization
$router = new Router($initialContainer);
$router->entryPoint('/users/*', UserController::class);

// Before each request in worker mode
$freshContainer = createFreshContainer();
$router->setContainer(fn($class) => $freshContainer->get($class));

// Dispatch with fresh dependencies
$response = $router->dispatch();
```

#### Automatic Caching in Worker Mode

The router automatically builds up performance caches in worker mode:

- **Route Factory Caching**: Once a route is accessed, its handler factory is cached for subsequent requests
- **Reflection Caching**: Method reflection is performed only once per route, then the reflection data is discarded to save memory
- **Parameter Closure Caching**: Type conversion logic is cached per route method

This means your application gets faster over time as route caches build up automatically. The first request to each route performs reflection and builds the factory, while subsequent requests use the cached factories for maximum performance.

## 🛡️ Middleware

### Route Middleware

Add middleware directly to routes via the `#[Route]` attribute:

```php
class UserController {

    #[Route('/api/users', [AuthMiddleware::class, RateLimitMiddleware::class])]
    public function list(): array {
        return ['users' => []];
    }
}
```

Or add middleware directly to entry points:

```php
$router->entryPoint('/admin/users', AdminUserController::class)->middleware([
    AuthMiddleware::class,
    AdminMiddleware::class
]);
```

### Group Middleware

Apply middleware to multiple routes using groups:

```php
$router->group(function(Router $r) {
    $r->entryPoint('/admin/users', AdminUserController::class);
    $r->entryPoint('/admin/settings', AdminSettingsController::class);
})->middleware([
    AuthMiddleware::class,
    AdminMiddleware::class
]);
```

You can also add middleware to individual entry points within a group. Entry point middleware runs **after** the group middleware:

```php
$router->group(function(Router $r) {
    $r->entryPoint('/admin/users', AdminUserController::class)->middleware([
        UserValidationMiddleware::class
    ]);
    $r->entryPoint('/admin/settings', AdminSettingsController::class);
})->middleware([
    AuthMiddleware::class,     // Runs first
    AdminMiddleware::class     // Runs second
]);
// UserValidationMiddleware runs third for /admin/users
```

### Middleware Implementation

Middleware must implement a `handle` method:

```php
class AuthMiddleware {

    public function handle(object|null $request, \Closure $next): mixed {
        // Perform authentication
        if (!$this->isAuthenticated($request)) {
            throw new UnauthorizedException();
        }

        return $next($request);
    }
}
```

## 🚀 Dispatching

Dispatch requests with optional path, request object, and HTTP method:

```php
try {
    // Dispatch current request (uses $_SERVER['REQUEST_METHOD'])
    $response = $router->dispatch();

    // Dispatch specific path
    $response = $router->dispatch('/api/users/123');

    // Dispatch with specific HTTP method
    $response = $router->dispatch('/api/users', null, 'POST');

    // Dispatch with request object for middleware
    $response = $router->dispatch('/api/users', $serverRequest);

    // Dispatch with path, request object, and method
    $response = $router->dispatch('/api/users/123', $serverRequest, 'PUT');

} catch (InvalidRoute $e) {
    // Handle 404 errors and method not allowed errors
    return new NotFoundResponse();
} catch (Throwable $e) {
    // Handle other errors
    return new ErrorResponse($e);
}
```

## ⚡ Performance

Rammewerk Router is designed for speed:

1. **Radix Tree Structure**: Efficient route matching using a radix tree for fast lookups
2. **Lazy Reflection**: Route methods are only reflected when first accessed
3. **Factory Caching**: Route handlers are cached after first creation
4. **Minimal Overhead**: Single-file core with no external dependencies
5. **Late Binding**: Container resolution happens at dispatch time for maximum flexibility

### Benchmarks

In tests with 150 routes and 75,000 route resolutions:

- **Rammewerk Router**: 79.959ms (baseline)
- **FastRoute**: 175.513ms (220% slower)
- **PHRoute**: 192.192ms (240% slower)
- **Symfony Router**: 491.643ms (615% slower)

## Example Application

Here's a complete example showing the new attribute-only approach:

```php
use Rammewerk\Router\Router;
use Rammewerk\Router\Foundation\Route;

// Controllers with attribute routes
class ApiController {

    #[Route('/api/health')]
    public function health(): array {
        return ['status' => 'ok'];
    }
}

class UserController {

    #[Route('/api/users', middleware: [AuthMiddleware::class], methods: ['GET'])]
    public function list(UserService $userService): array {
        return $userService->getAllUsers();
    }

    #[Route('/api/users', middleware: [AuthMiddleware::class], methods: ['POST'])]
    public function create(UserService $userService): array {
        return $userService->createUser();
    }

    #[Route('/api/users/*', middleware: [AuthMiddleware::class], methods: ['GET'])]
    public function show(UserService $userService, int $userId): array {
        return $userService->getUser($userId);
    }

    #[Route('/api/users/*', middleware: [AuthMiddleware::class], methods: ['PUT'])]
    public function update(UserService $userService, int $userId): array {
        return $userService->updateUser($userId);
    }

    #[Route('/api/users/*', middleware: [AuthMiddleware::class], methods: ['DELETE'])]
    public function delete(UserService $userService, int $userId): array {
        return $userService->deleteUser($userId);
    }

    #[Route('/api/users/*/profile', middleware: [AuthMiddleware::class, OwnerMiddleware::class], methods: ['GET'])]
    public function profile(UserService $userService, int $userId): array {
        return $userService->getUserProfile($userId);
    }
}

// Setup router
$container = static fn(string $class) => new $class();
$router = new Router($container);

// Register entry points
$router->entryPoint('/api/health', ApiController::class);
$router->entryPoint('/api/users', UserController::class);
$router->entryPoint('/api/users/*', UserController::class);

// Group with shared middleware
$router->group(function(Router $r) {
    $r->entryPoint('/admin/users', AdminUserController::class);
    $r->entryPoint('/admin/settings', AdminSettingsController::class);
})->middleware([AuthMiddleware::class, AdminMiddleware::class]);

// Dispatch
$response = $router->dispatch();
```

This new attribute-only approach provides:

- **Clean Declaration**: Routes are defined where they're used
- **Complete Paths**: No ambiguity about route patterns
- **Flexible Mapping**: One class can handle multiple entry points
- **Route-Specific Middleware**: Fine-grained control over middleware application
- **Multiple Routes**: Methods can handle multiple route patterns

The router maintains all the powerful features like type-safe parameters, dependency injection, and high performance while simplifying the API to focus purely on attribute-based route definitions.