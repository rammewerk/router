# Rammewerk Router - Quick Reference

Lightweight, high-performance PHP router with attribute-only routing for PHP 8.4+.

## Initial Setup

### Installation
```bash
composer require rammewerk/router
```

### Basic Configuration
```php
use Rammewerk\Router\Router;

// Simple container (or use a DI container)
$container = static fn(string $class) => new $class();

// Create router
$router = new Router($container);

// Define entry points (maps URL patterns to controller classes)
$router->entryPoint('/api/users', UserController::class);
$router->entryPoint('/admin/dashboard', AdminController::class);

// Dispatch
try {
    $response = $router->dispatch();
} catch (InvalidRoute $e) {
    // Handle 404 or method not allowed
}
```

### Entry Points
Entry points map URL patterns to controller classes. Router scans for `#[Route]` attributes:

```php
// Static entry point
$router->entryPoint('/api/users', UserController::class);

// With wildcards for dynamic segments
$router->entryPoint('/blog/*/comments', CommentController::class);

// With overwrite protection (default throws exception on duplicate)
$router->entryPoint('/api/users', UserController::class);
$router->entryPoint('/api/users', AdminController::class); // Throws RouterConfigurationException!

// Explicitly overwrite existing route
$router->entryPoint('/api/users', AdminController::class, overwrite: true); // OK
```

## Usage with FrankenPHP (Worker Mode)

### Setup for Long-Running Workers
```php
// Initialize once during worker startup
$router = new Router($initialContainer);
$router->entryPoint('/api/users', UserController::class);
$router->entryPoint('/admin/', AdminController::class);

// Before EACH request - inject fresh container
$freshContainer = createFreshContainer();
$router->setContainer(fn($class) => $freshContainer->get($class));

// Dispatch with fresh dependencies
$response = $router->dispatch();
```

**Key Benefits:**
- Route factories cached automatically after first access
- Reflection performed only once per route
- Container reinjected per request to prevent singleton leakage
- Performance improves over time as caches build up

## Adding Routes

### Route Attribute
Define routes using `#[Route]` attribute on controller methods:

```php
use Rammewerk\Router\Foundation\Route;

class UserController {

    // Basic route
    #[Route('/api/users')]
    public function list(): array {
        return ['users' => []];
    }

    // Route with wildcards (*)
    #[Route('/api/users/*/profile')]
    public function profile(int $userId): string {
        return "User: $userId";
    }

    // Named parameters (cosmetic - becomes * internally)
    #[Route('/api/users/{id}/posts/{postId}')]
    public function userPost(int $userId, int $postId): mixed {
        return "User: $userId, Post: $postId";
    }

    // Multiple wildcards
    #[Route('/blog/*/comments/*')]
    public function comment(string $slug, int $commentId): mixed {
        return "Blog: $slug, Comment: $commentId";
    }
}
```

**Named Parameters**: `{name}` syntax is purely cosmetic and gets converted to `*` internally. Use it for readability.
```php
#[Route('/user/{id}')]      // Becomes /user/*
#[Route('/user/{userId}')]   // Also becomes /user/* - DUPLICATE!
```

### HTTP Methods
```php
class ApiController {

    // All methods (default)
    #[Route('/api/status')]
    public function status(): array {}

    // Specific method
    #[Route('/api/users', methods: ['GET'])]
    public function getUsers(): array {}

    #[Route('/api/users', methods: ['POST'])]
    public function createUser(): array {}

    // Multiple methods
    #[Route('/api/users/*', methods: ['PUT', 'PATCH'])]
    public function updateUser(int $id): array {}
}
```

### Multiple Routes per Method
```php
#[Route('/products')]
#[Route('/items')]
#[Route('/catalog')]
public function list(): array {
    return ['products' => []];
}
```

### Type-Safe Parameters
Automatic type conversion from URL segments:

```php
class Controller {

    // int, float, string, bool
    #[Route('/users/*/posts/*')]
    public function show(int $userId, int $postId): mixed {}

    // DateTime
    #[Route('/events/*/date/*')]
    public function event(int $id, DateTimeImmutable $date): mixed {}

    // Backed enums
    #[Route('/users/*/status/*')]
    public function status(int $id, StatusEnum $status): mixed {}

    // Non-backed enums (matches case name)
    #[Route('/users/*/type/*')]
    public function type(int $id, TypeEnum $type): mixed {}
}
```

**Parameter Validation:**
- Route patterns **must** have a wildcard (`*` or `{name}`) for each route parameter
- **Route parameters**: scalar types, DateTime, DateTimeImmutable, enums
- **DI parameters**: classes, interfaces (don't require wildcards)
- Optional parameters still need wildcards if they're route parameters
- Throws `RouterConfigurationException` at registration if wildcards are missing

```php
// ✅ Valid: 1 wildcard for 1 route parameter
#[Route('/user/*')]
public function show(int $id): mixed {}

// ❌ Invalid: Missing wildcard
#[Route('/user')]
public function invalid(int $id): mixed {}
// Throws: Route pattern '/user' has 0 wildcard(s) but handler expects 1 route parameter(s)

// ✅ Valid: DI parameter doesn't need wildcard
#[Route('/service')]
public function service(LoggerInterface $logger): mixed {}

// ✅ Valid: Only route params need wildcards
#[Route('/mixed/*')]
public function mixed(LoggerInterface $logger, int $id): mixed {}
```

### Dependency Injection
Inject services into route methods:

```php
class UserController {

    #[Route('/api/users/*')]
    public function show(UserService $service, int $userId): Response {
        return $service->find($userId);
    }

    // Dependencies resolved before route parameters
    #[Route('/api/users/*/posts/*')]
    public function userPost(
        UserService $userService,
        PostService $postService,
        int $userId,
        int $postId
    ): Response {}
}
```

### Middleware

**Route-level middleware:**
```php
#[Route('/admin/dashboard', [AuthMiddleware::class, AdminMiddleware::class])]
public function dashboard(): string {}
```

**Entry point middleware:**
```php
$router->entryPoint('/admin/users', AdminController::class)
    ->middleware([AuthMiddleware::class]);
```

**Group middleware:**
```php
$router->group(function(Router $r) {
    $r->entryPoint('/admin/users', AdminUserController::class);
    $r->entryPoint('/admin/settings', SettingsController::class);
})->middleware([AuthMiddleware::class, AdminMiddleware::class]);
```

**Middleware implementation:**
```php
class AuthMiddleware {
    public function handle(object|null $request, \Closure $next): mixed {
        if (!$this->isAuthenticated($request)) {
            throw new UnauthorizedException();
        }
        return $next($request);
    }
}
```

**Execution order:** Group middleware → Entry point middleware → Route attribute middleware

### Dispatching

```php
// Current request (uses $_SERVER['REQUEST_URI'] and $_SERVER['REQUEST_METHOD'])
$response = $router->dispatch();

// Specific path
$response = $router->dispatch('/api/users/123');

// With HTTP method
$response = $router->dispatch('/api/users', null, 'POST');

// With request object (PSR-7 compatible)
$response = $router->dispatch('/api/users', $serverRequest);

// Full control
$response = $router->dispatch('/api/users/123', $serverRequest, 'PUT');
```

## Key Concepts

1. **Entry points** map URL patterns to controller classes
2. **#[Route]** attributes define actual routes on methods with full paths
3. **Wildcards (*)** in paths capture dynamic segments as method parameters
4. **Named parameters** `{name}` are cosmetic - they become `*` internally
5. **Duplicate detection** - same pattern throws exception (use `overwrite: true` to allow)
6. **Type conversion** happens automatically based on parameter types
7. **Dependencies** are resolved before route parameters in method signatures
8. **Worker mode** requires fresh container injection per request via `setContainer()`

### Route Coexistence Rules

- `/user` and `/user/*` are **different routes** (static vs wildcard)
- `/user/id` and `/user/*` are **different routes** (static segment vs wildcard)
- `/user/{id}` and `/user/{userId}` are **duplicates** (both become `/user/*`)
- `/test/*/*` and `/test/*/id` are **different routes** (different patterns)
- Static routes have **priority** over wildcard routes during matching

## Performance Notes

- Uses radix tree for O(log n) route lookups
- Lazy reflection - routes reflected only on first access
- Factory caching - handlers cached after creation
- Late binding - container resolution at dispatch time
- Ideal for FrankenPHP workers with automatic optimization
