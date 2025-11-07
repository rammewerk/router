CHANGELOG
=========

1.1.0
---

### Breaking Changes

- **Duplicate Route Protection** – Routes with identical patterns now throw `RouterConfigurationException` by default. This includes both static routes (e.g., `/api/users`) and wildcard routes (e.g., `/api/users/*`). Use the `$overwrite` parameter in `entryPoint()` to explicitly allow route replacement: `$router->entryPoint($pattern, $class, overwrite: true)`.

- **Named Parameter Normalization** – Named parameters in route patterns (e.g., `{id}`, `{userId}`) are now normalized to wildcards (`*`) during registration. This means `/user/{id}` and `/user/{userId}` are treated as duplicate patterns and will throw an exception. The parameter names are purely cosmetic and ignored by the router.

- **Wildcard Route Tracking** – Wildcard routes are now tracked separately and checked for duplicates. Previously, wildcard routes could be registered multiple times without error.

### New Features

- **Route Parameter Validation** – Added automatic validation to ensure route patterns have sufficient wildcards for all route parameters. When registering routes, the router now validates that each non-DI parameter has a corresponding wildcard in the pattern. Throws `RouterConfigurationException` with a helpful error message if wildcards are missing. Example: `#[Route('/user')]` with `function user(int $id)` will now throw an exception suggesting to use `/user/*`.

- **Named Parameter Syntax** – Added support for `{name}` syntax in route patterns as a cosmetic alternative to `*`. Example: `#[Route('/user/{id}/posts/{postId}')]` is equivalent to `#[Route('/user/*/posts/*')]`. Parameter names are for documentation only and do not affect routing behavior.

- **Enhanced Middleware Propagation** – Fixed middleware propagation from parent entry points to sub-routes discovered via attributes. Routes now properly inherit middleware from their parent entry points when patterns match.

- **RouteUtility::normalizePattern()** – Added utility method to centralize pattern normalization logic. Converts `{anything}` syntax to `*` for consistent internal route matching.

### Improvements

- **Type Safety** – Improved type safety in pattern normalization with explicit `LogicException` throws on regex failures.
- **Better Documentation** – Updated README.md and AGENTS.md with comprehensive examples of named parameters, duplicate detection, route coexistence rules, and parameter validation.
- **Test Coverage** – Added 12 new tests in `RoutePatternTest.php` covering named parameters, duplicate detection, and route coexistence scenarios. Added 10 new tests in `RouteParameterValidationTest.php` covering parameter validation with wildcards, DI parameters, optional parameters, and variadic parameters.

### Migration Guide

If you're upgrading from 1.0.x:

1. **Check for duplicate routes**: Review your route registrations. If you intentionally register the same pattern twice, add `overwrite: true`:
   ```php
   // Before (would silently overwrite):
   $router->entryPoint('/api/users', UserController::class);
   $router->entryPoint('/api/users', AdminController::class);

   // After (explicit):
   $router->entryPoint('/api/users', UserController::class);
   $router->entryPoint('/api/users', AdminController::class, overwrite: true);
   ```

2. **Named parameters are normalized**: If you relied on different parameter names to create different routes, this will now fail:
   ```php
   // Before (might have worked):
   #[Route('/user/{id}')]
   #[Route('/user/{userId}')]  // Different name

   // After (throws exception - they're duplicates):
   #[Route('/user/*')]         // Use different patterns instead
   #[Route('/user/profile/*')]
   ```

3. **Wildcard routes checked**: Duplicate wildcard patterns now throw exceptions:
   ```php
   // Before (would silently accept):
   $router->entryPoint('/api/*', Controller1::class);
   $router->entryPoint('/api/*', Controller2::class);

   // After (throws exception or use overwrite):
   $router->entryPoint('/api/*', Controller1::class);
   $router->entryPoint('/api/*', Controller2::class, overwrite: true);
   ```

4. **Route parameters must have wildcards**: Routes with parameters now require explicit wildcards:
   ```php
   // Before (might have worked):
   #[Route('/user')]
   public function user(int $id) { }

   // After (throws exception - add wildcard):
   #[Route('/user/*')]
   public function user(int $id) { }

   // Or use named parameter syntax:
   #[Route('/user/{id}')]
   public function user(int $id) { }
   ```

   Note: DI parameters (classes, interfaces) don't require wildcards, only route parameters (scalar types, DateTime, enums).

1.0.1
---

- **Improved Error Messages** – Enhanced error reporting when route handlers cannot be found. The router now
  distinguishes between missing routes and HTTP method mismatches, providing clearer feedback.
- **Non-Public Method Detection** – Added validation to detect Route attributes placed on protected or private methods.
  The router now throws a descriptive `RouterConfigurationException` explaining that route handlers must be public
  methods.
- **Better HTTP Method Errors** – When a route exists but doesn't support the requested HTTP method, error messages now
  clearly show which methods are allowed (e.g., "Allowed methods: POST, PUT" instead of the confusing "Allowed methods:
  all methods").
- **AI Agent Guide** - Added `AGENTS.md` to help developers and AI agents alike understand the router's behavior in an
  effective way.

1.0.0
---

- **🎉 Major Release - Attribute-Only Routing** – Router has been completely refactored to support ONLY attribute-based
  routing. This simplifies the API and provides a more declarative approach to route definitions.
- **HTTP Method Support** – Added full support for HTTP methods (GET, POST, PUT, DELETE) through the Route attribute.
  Methods can be specified using the `methods` parameter in `#[Route()]` attributes.
- **Multiple Routes per Method** – Single controller methods can now handle multiple route patterns by using multiple
  `#[Route]` attributes on the same method.
- **Enhanced Route Middleware** – Middleware can now be defined directly in Route attributes using the `middleware`
  parameter, providing fine-grained control over route-specific middleware.
- **Flexible Method Handling** – Routes without specified methods allow all HTTP methods. When methods are specified,
  only those methods are allowed for that route.
- **Breaking Changes** – This is a major version release with breaking changes. The previous closure-based and mixed
  routing approaches have been removed in favor of the cleaner attribute-only approach.

0.9.10
---

- **Late Container Binding** – Added `setContainer()` method to support container injection after router initialization.
  This prevents singleton leakage in FrankenPHP worker mode while preserving performance through cached route factories.
- **Worker Mode Support** – Enhanced compatibility with long-running processes like FrankenPHP workers by allowing fresh
  container instances per request.
- **Type Annotation Improvements** – Fixed PHPStan template issues for better static analysis support.

0.9.9
---

- Added support for prepended middleware on routes. This is now the default for grouped routes, ensuring group
  middleware runs before each child route's middleware.
- Minor code improvements and refactoring.

0.9.8
---

- New lookup/matching algorithm for faster route resolution

0.9.6
---

- Dependency resolver is no longer required, but we still recommend it!
- Added support to disable reflection for added routes, which can improve performance when reflection isn't needed.
  `$router->add(...)->disableReflection();`

0.9.5
---

- **Simplified API** – Removed the ability to override the default method in class-based routes. With attribute support,
  this is now the preferred approach for customizing default methods instead of relying on index.
- **Performance Enhancements** – Refactored core logic for better efficiency and faster route resolution.
- **General Improvements** – Minor optimizations and bug fixes for improved stability.

0.9.4
---

- Improved performance throughout the codebase
- New improved radix tree implementation
- Introduced cached parameter resolver
- Removed reflection from parameter resolver cache to allow for serialization
- Utilizing the radix tree to resolve class methods and attributes
- Caching up reflection instances
- Added support for non-backed enums, through matching on case name
- Added multiple new unit tests, ensuring the code is solid
- Added a BETA version of the adapter "MethodAwareRouter" which allows for validating route based on HTTP method. Please
  note that this is still in BETA and may change in the future. Any feedback is welcome!

0.9.3
---

- Added support for enums
- Increased performance by replacing preg_match() with radix tree
- Optimized the way context and paths are handled, string based instead of array based. Benchmarked everything changes
  to make sure there was improvement.
- Better folder structure
- Added tests and organized tests
- Added a simple benchmark tester to help compare different implementations
- Implemented a RouterConfigurationException class to make it more clear what went wrong

0.9.2
---

- Changed method() to classMethod() to avoid confusion with HTTP methods
- Support for Route attributes

0.9.1
---

- Deferred regex generation until needed

0.9.0
---

* Huge refactoring of the codebase, making it more maintainable and easier to extend.
* Please see the README for more information.

0.6.0
---

* Added a way to require that number of parameters must match the number of parameters in the route. Use
  `$route->autoResolve(false);`

0.5.0
---

* Fixed bug where parameter resolver didn't detect class interfaces as dependency.

0.5.0
---

* Added support for routes having parameters of type int. Router will convert path part (string) to int.
* Will now return the value of the method from the find() method

0.1.0
---
Initial beta release