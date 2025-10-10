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

    // Route with parameters (wildcards = *)
    #[Route('/api/users/*/profile')]
    public function profile(int $userId): string {
        return "User: $userId";
    }

    // Multiple wildcards
    #[Route('/blog/*/comments/*')]
    public function comment(string $slug, int $commentId): mixed {
        return "Blog: $slug, Comment: $commentId";
    }
}
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
4. **Type conversion** happens automatically based on parameter types
5. **Dependencies** are resolved before route parameters in method signatures
6. **Worker mode** requires fresh container injection per request via `setContainer()`

## Performance Notes

- Uses radix tree for O(log n) route lookups
- Lazy reflection - routes reflected only on first access
- Factory caching - handlers cached after creation
- Late binding - container resolution at dispatch time
- Ideal for FrankenPHP workers with automatic optimization
