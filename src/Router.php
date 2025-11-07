<?php

declare(strict_types=1);

namespace Rammewerk\Router;

use BackedEnum;
use Closure;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Rammewerk\Router\Definition\GroupDefinition;
use Rammewerk\Router\Definition\GroupInterface;
use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Definition\RouteHandler;
use Rammewerk\Router\Definition\RouteInterface;
use Rammewerk\Router\Definition\RouteParameter;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Foundation\Node;
use Rammewerk\Router\Foundation\NodeInterface;
use Rammewerk\Router\Foundation\Route;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use function array_filter;
use function array_map;
use function array_shift;
use function array_unshift;
use function enum_exists;
use function filter_var;
use function is_null;
use function is_numeric;
use function is_string;
use function parse_url;
use function strcasecmp;
use function trim;
use const PHP_URL_PATH;

/**
 * RAMMEWERK ROUTER
 *
 * Lightweight and fast PHP router for modern apps
 * Please notice: Code is written for performance and not for elegance.
 * It uses many "old" ways to doing things because it is simply faster
 *
 * @link   https://github.com/rammewerk/router
 * @author Kristoffer Follestad <kristoffer@bonsy.no>
 *
 */
class Router {


    /** @var GroupDefinition|null Current group in a group closure or null outside */
    private ?GroupDefinition $active_group = null;

    /** @var array<string,RouteDefinition> A static list of routes */
    public array $routes = [];

    /** @var array<string,RouteDefinition> Track wildcard routes for duplicate detection */
    private array $wildcardRoutes = [];

    /** @var Node Radix tree of routes */
    private NodeInterface $node;

    /** @var string The current path being matched */
    private string $path = '';

    /**
     * @var Closure(class-string):object|null to resolve class instances
     */
    protected ?Closure $container = null;



    /**
     * Create a new router instance
     *
     * Initializes the router with a dependency handler and sets up the routing tree.
     *
     * @param ?Closure(class-string):object $container
     * The closure is used to generate instances of objects when needed. The closure is given a class-string and
     * should return a fully instantiated instance of the same class.
     *
     */
    public function __construct(?Closure $container = null) {
        $this->container = $container ?? static fn(string $class): object => new $class();
        $this->node = new Node();
    }



    /**
     * Adds a new route to the application.
     *
     * Registers a route with a URI pattern and a handler.
     *
     * @param string $pattern     URI pattern to match against
     * @param class-string $class The handler to manage requests
     * @param bool $overwrite     Allow overwriting the existing route (default: false)
     *
     * @return RouteInterface
     * @throws RouterConfigurationException If the route already exists and $overwrite is false
     */
    public function entryPoint(string $pattern, string $class, bool $overwrite = false): RouteInterface {
        $pattern = trim($pattern, '/');

        // Normalize named parameters to wildcards: {anything} → *
        $pattern = Foundation\RouteUtility::normalizePattern($pattern);

        // Check for duplicate routes in static routes (non-wildcard)
        if (!$overwrite && isset($this->routes[$pattern]) && !str_contains($pattern, '*')) {
            throw new RouterConfigurationException(
                "Route with pattern '$pattern' is already registered. Use \$overwrite = true to replace it.",
            );
        }

        // Check for duplicate routes in wildcard routes
        if (!$overwrite && isset($this->wildcardRoutes[$pattern]) && str_contains($pattern, '*')) {
            throw new RouterConfigurationException(
                "Route with pattern '$pattern' is already registered. Use \$overwrite = true to replace it.",
            );
        }


        $route = new RouteDefinition($pattern, $class);

        // Store route based on type
        if (!str_contains($pattern, '*')) {
            $this->routes[$pattern] = $route;
        } else {
            $this->wildcardRoutes[$pattern] = $route;
        }

        $this->node->insert($pattern, $route);
        $this->active_group?->registerEntryPoint($route);
        return $route;
    }



    /**
     * Handles a group of routes
     *
     * Groups multiple routes together for easier management.
     *
     * @param Closure(Router):void $callback
     *
     * @return GroupInterface
     */
    public function group(Closure $callback): GroupInterface {
        $group = new GroupDefinition();
        $this->active_group = $group;
        $callback($this);
        $this->active_group = null;
        return $group;
    }



    /**
     * Sets or replaces the container for dependency injection
     *
     * This method allows injecting a fresh container before dispatch to prevent
     * singleton leakage in FrankenPHP worker mode. Route factories remain cached
     * for performance but will use the new container through late binding.
     *
     * @param Closure(class-string):object $container
     *
     * @return void
     */
    public function setContainer(Closure $container): void {
        $this->container = $container;
    }



    /**
     * Gets the current container closure
     *
     * @return Closure(class-string):object
     * @throws RouterConfigurationException
     */
    public function getContainer(): Closure {
        return $this->container ?? throw new RouterConfigurationException('Container not set');
    }



    /**
     * Finds and handles a route based on a provided path.
     *
     * Resolves a route, executes its handler, and returns the response.
     *
     * @param string|null $path          The route path to locate. If not provided, the request_uri is used.
     * @param object|null $serverRequest Pass server request object to use for middleware and route handler.
     * @param string|null $requestMethod The HTTP method to use for the route. If not provided, the request_method is used.
     *
     * @return mixed The called route method or closure response
     * @throws InvalidRoute
     */
    public function dispatch(?string $path = null, object|null $serverRequest = null, ?string $requestMethod = null): mixed {

        $path = $this->path = trim($path ?? $this->getUriPath(), '/ ');

        $route = $this->routes[$path]
            ?? $this->node->match($path)
            ?? throw new InvalidRoute("No route found for path: $path");

        // Reset nodeContext
        $route->context = $route->nodeContext;
        $route->nodeContext = '';

        /** @var string $requestMethod Find handler for current HTTP method */
        $requestMethod = $requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestMethod = strtoupper($requestMethod);

        // If no handlers exist, the route needs reflection first
        if (empty($route->handlers)) {
            $route = $this->reflectHandler($route);
        }

        $handler = $route->getHandlerForMethod($requestMethod);

        // Show error if a handler is not found for a given request method
        if (!$handler) {
            // If no handlers exist at all, this is a configuration issue
            if (empty($route->handlers)) {
                throw new InvalidRoute("No route handlers found for path: $path");
            }

            // Handler exists but not for this HTTP method
            $allowedMethods = [];
            foreach ($route->handlers as $h) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $allowedMethods = array_merge($allowedMethods, $h->methods);
            }
            $allowedMethods = array_unique($allowedMethods);
            $methodList = empty($allowedMethods) ? 'all HTTP methods' : implode(', ', $allowedMethods);
            throw new InvalidRoute("Method $requestMethod not allowed for path: $path. Allowed methods: $methodList");
        }

        if (!$handler->factory) {
            $handler = $this->createHandlerFactory($route, $handler);
        }

        $handlerFactory = $handler->factory ?? throw new InvalidRoute("Unable to handle route for: '$path'");
        $arguments = $route->getArguments();

        // Combine route-level and handler-level middleware
        $allMiddleware = array_merge($route->middleware, $handler->middleware);

        return !empty($allMiddleware) ? $this->runPipeline(
            $this->createMiddlewareFactories($allMiddleware),
            static fn(object|null $serverRequest) => $handlerFactory($arguments, $serverRequest),
            $serverRequest,
        ) : $handlerFactory($arguments, $serverRequest);

    }



    /**
     * Creates a handler factory for the route handler
     *
     * Processes the route handler and prepares it for execution.
     *
     * @param RouteDefinition $route
     * @param RouteHandler $handler
     *
     * @return RouteHandler
     * @throws InvalidRoute
     */
    private function createHandlerFactory(RouteDefinition $route, RouteHandler $handler): RouteHandler {

        if (!$handler->reflection) {
            throw new InvalidRoute("Handler reflection is missing for route: '/$route->pattern'");
        }

        // Validate that pattern has enough wildcards for route parameters
        $wildcardCount = substr_count($route->pattern, '*');
        $routeParamCount = $this->countRouteParameters($handler->reflection);

        if ($routeParamCount > $wildcardCount) {
            throw new RouterConfigurationException(
                "Route pattern '/{$route->pattern}' has {$wildcardCount} wildcard(s) " .
                "but handler '{$handler->classMethod}()' expects {$routeParamCount} route parameter(s). " .
                "Add wildcards or use named parameters like '/{$route->pattern}" . str_repeat('/*', $routeParamCount - $wildcardCount) . "'."
            );
        }

        $argumentFactory = $this->getHandlerParameterClosure($handler);
        $handler->reflection = null; // Free up memory

        $method = $handler->classMethod;
        $class = $route->routeClass;
        $handler->factory = function (array $context, object|null $request) use ($class, $method, $argumentFactory) {
            return $this->getContainer()($class)->{$method}(...$argumentFactory($context, $request));
        };

        return $handler;

    }



    /**
     * Reflect the route request handler
     *
     * Uses reflection to analyze the handler method or class.
     *
     * @param RouteDefinition $route
     *
     * @return RouteDefinition
     * @throws InvalidRoute
     */
    public function reflectHandler(RouteDefinition $route): RouteDefinition {
        try {
            $reflection = new ReflectionClass($route->routeClass);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);
                foreach ($attributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();
                    $this->addRouteHandler($routeAttribute, $route->routeClass, $method);
                }
            }

            // Check if route attributes exist on non-public methods and provide a helpful error
            if (empty($route->handlers)) {
                foreach ($reflection->getMethods(ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE) as $method) {
                    $attributes = $method->getAttributes(Route::class);
                    if (!empty($attributes)) {
                        $visibility = $method->isProtected() ? 'protected' : 'private';
                        throw new RouterConfigurationException(
                            "Route attribute found on $visibility method '{$method->getName()}' in class {$reflection->getName()}. " .
                            "Route handlers must be public methods.",
                        );
                    }
                }
            }

            return $this->swapRoute($route)
                ?? throw new InvalidRoute("No matching Route attribute found in class {$reflection->getName()} for route '$route->pattern'");


        } catch (ReflectionException $e) {
            throw new RouterConfigurationException(
                "Unable to reflect route class for route: '/$route->pattern' - {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }
    }



    /**
     * Registers a route handler
     *
     * Adds a route and assigns its reflection method.
     *
     * @param Route $routeAttribute
     * @param class-string $handler
     * @param ReflectionMethod $reflection
     *
     * @return void
     */
    private function addRouteHandler(Route $routeAttribute, string $handler, ReflectionMethod $reflection): void {
        $pattern = trim($routeAttribute->path, '/ ');
        /** @var RouteDefinition $route */
        // Check both static and wildcard route storage
        $route = $this->routes[$pattern] ?? $this->wildcardRoutes[$pattern] ?? $this->entryPoint($pattern, $handler);

        $route->addHandler(new RouteHandler(
            methods: $routeAttribute->methods,
            middleware: $routeAttribute->middleware,
            classMethod: $reflection->getName(),
            reflection: $reflection,
        ));

        $route->nodeContext = '';
        $route->context = '';
    }



    /**
     * Swaps the current route with the matched route
     *
     * Updates route context and middleware settings.
     *
     * @param RouteDefinition $route
     *
     * @return RouteDefinition|null
     */
    private function swapRoute(RouteDefinition $route): ?RouteDefinition {
        $swap_route = $this->node->match($this->path);
        if (!$swap_route) return null;
        $route->context = '';
        // Only copy middleware if it's a different route and doesn't already have it
        if ($swap_route !== $route && empty($swap_route->middleware)) {
            $swap_route->middleware($route->middleware);
        }
        $swap_route->context = $swap_route->nodeContext;
        $swap_route->nodeContext = '';
        return $swap_route;
    }



    /**
     * Creates middleware factories
     *
     * Converts middleware definitions into callable factories.
     *
     * @param array<class-string|object> $middlewareQueue
     *
     * @return array<Closure():object> The called method response.
     */
    protected function createMiddlewareFactories(array $middlewareQueue): array {
        $router = $this;
        return array_reverse(array_map(
            static fn($middleware): Closure => static function () use ($middleware, $router): object {
                if (is_string($middleware)) {
                    return $router->getContainer()($middleware);
                }
                return $middleware;
            },
            $middlewareQueue,
        ));

    }



    /**
     * Runs a request through the middleware pipeline
     *
     * Applies middleware layers before executing the route handler.
     *
     * @param array<Closure():object> $middlewareQueue
     * @param Closure(object|null):mixed $requestHandler
     * @param object|null $serverRequest
     *
     * @return mixed
     */
    protected function runPipeline(array $middlewareQueue, Closure $requestHandler, object|null $serverRequest): mixed {

        $pipeline = array_reduce(
            $middlewareQueue,
            static function (Closure $nextHandler, Closure $middlewareFactory) {
                return static function ($request) use ($middlewareFactory, $nextHandler) {
                    $middleware = $middlewareFactory();
                    if (method_exists($middleware, 'handle')) {
                        return $middleware->handle($request, $nextHandler);
                    }
                    throw new RouterConfigurationException('Required middleware method handle() is missing');
                };
            },
            $requestHandler,
        );


        return $pipeline($serverRequest);
    }



    /**
     * Generates parameter closure for a route handler
     *
     * Resolves route parameters using reflection.
     *
     * @param RouteHandler $handler
     *
     * @return Closure
     * @throws InvalidRoute
     */
    public function getHandlerParameterClosure(RouteHandler $handler): Closure {

        if (!$handler->reflection) {
            throw new InvalidRoute("Unable to reflect route handler for handler method: '$handler->classMethod'");
        }

        $handlerName = $handler->reflection->getName();
        $parameterClosures = [];

        foreach ($handler->reflection->getParameters() as $parameter) {
            $set = new RouteParameter();
            $set->name = $parameter->getName();
            $set->variadic = $parameter->isVariadic();
            $set->optional = $parameter->isOptional();
            $set->nullable = $parameter->allowsNull();
            $set->builtIn = $parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->isBuiltin();
            $set->type = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : '';
            $set->defaultValue = $parameter->isOptional() && $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

            if (!$set->builtIn && $set->type && is_subclass_of($set->type, BackedEnum::class)) {
                try {
                    $set->enum = new ReflectionEnum($set->type)->getBackingType()?->getName();
                } catch (ReflectionException $e) {
                    throw new RouterConfigurationException("Unable to reflect enum type for route handler parameter '$set->name'", $e->getCode(), $e);
                }
            }

            if (!$set->type && $parameter->getType() instanceof ReflectionUnionType) {
                /** @phpstan-ignore-next-line */
                $set->unionTypes = array_map(static fn($t) => $t->getName(), $parameter->getType()->getTypes());
            }

            $parameterClosures[] = $set;
        }

        // Free up memory
        $handler->reflection = null;

        $convertToType = static function (string $type, mixed $value): mixed {

            /** @param class-string<DateTime|DateTimeImmutable> $class */
            $parseDate = static function (mixed $value, string $class) {
                /** @phpstan-ignore-next-line */
                return array_reduce(
                    ['Y-m-d', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'],
                    static fn($carry, $format) => $carry ?? $class::createFromFormat($format, $value) ?: null,
                );
            };

            return match ($type) {
                'int'               => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'float'             => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                'bool'              => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                'DateTimeImmutable' => $parseDate($value, DateTimeImmutable::class),
                'DateTime'          => $parseDate($value, DateTime::class),
                'array',
                'object',
                'callable',
                'iterable',
                'resource'          => throw new InvalidArgumentException($type),
                default             => $value,
            };

        };

        /**
         * @param string[] $args
         * @param object|null $request
         *
         * @return Closure(array<string,mixed>, object|null): mixed[]
         * @throws RouterConfigurationException|InvalidRoute
         */
        return function (array $args, object|null $request) use ($parameterClosures, $handlerName, $convertToType) {

            $arguments = [];

            foreach ($parameterClosures as $parameter) {

                // Variadic must be the last parameter
                if ($parameter->variadic) {
                    if ($parameter->type) {
                        try {
                            $args = array_filter(
                                array_map(static fn($value) => $convertToType($parameter->type, $value), $args),
                                static fn($arg) => $arg !== null,
                            );
                        } catch (InvalidArgumentException) {
                            throw new RouterConfigurationException("The parameter '$parameter->name' in route method '$handlerName' has an unsupported type: '$parameter->type'. Cannot be handled by router.");
                        }
                    }
                    $arguments = [...$arguments, ...$args];
                    $args = [];
                    break;
                }

                if (!$parameter->builtIn) {

                    // Use the given instance of request handler if the parameter is of the same type
                    if ($request && $request instanceof $parameter->type) {
                        $arguments[] = $request;
                        continue;
                    }

                    if ($parameter->type === 'DateTime' || $parameter->type === 'DateTimeImmutable') {
                        $arg = array_shift($args);
                        $arg = is_string($arg)
                            ? $convertToType($parameter->type, $arg) ?? $parameter->defaultValue
                            : null;
                        if ($arg !== null || $parameter->optional || $parameter->nullable) {
                            $arguments[] = $arg;
                            continue;
                        }
                        throw new InvalidRoute("Missing required parameter '$parameter->name' of type '$parameter->type' in '$handlerName'");
                    }

                    // Then backed enum check:
                    if ($parameter->enum) {
                        $arg = array_shift($args) ?? $parameter->defaultValue;
                        if ($arg instanceof $parameter->type) {
                            $arguments[] = $arg;
                            continue;
                        }
                        if ($parameter->enum === 'int' && is_numeric($arg)) {
                            $arg = filter_var($arg, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                            $arguments[] = $parameter->type::tryFrom($arg) ?? throw new InvalidRoute("Invalid enum value '$arg' for '$parameter->type'");
                            continue;
                        }
                        if ($parameter->enum === 'string' && is_string($arg) && $arg !== '') {
                            $arguments[] = $parameter->type::tryFrom($arg) ?? throw new InvalidRoute("Invalid enum value '$arg' for '$parameter->type'");
                            continue;
                        }
                        throw new InvalidRoute("Invalid enum value for '$parameter->type'");
                    }

                    // Then non-backed enum check based on the case name
                    if (enum_exists($parameter->type)) {
                        $arg = array_shift($args);
                        if (is_string($arg) && $arg !== '') {
                            $enumCase = array_filter($parameter->type::cases(), static fn($case) => strcasecmp($case->name, $arg) === 0);
                            $arguments[] = $enumCase
                                ? reset($enumCase)
                                : throw new InvalidRoute("Invalid enum value '$arg' for '$parameter->type'");
                            continue;
                        }
                        array_unshift($args, $arg);
                    }

                    /** @phpstan-ignore-next-line */
                    $arguments[] = $this->getContainer()($parameter->type);
                    continue;

                }

                $arg = array_shift($args);

                if (!is_null($arg)) {
                    try {
                        $arg = $convertToType($parameter->type, $arg);
                    } catch (InvalidArgumentException) {
                        throw new RouterConfigurationException("The parameter '$parameter->name' in route method '$handlerName' has an unsupported type: '$parameter->type'. Cannot be handled by router.");
                    }
                }

                if (!is_null($arg)) {
                    $arguments[] = $arg;
                    continue;
                }

                $arguments[] = $parameter->optional || $parameter->nullable
                    ? $parameter->defaultValue
                    : throw new InvalidRoute("Missing required parameter '$parameter->name' of type '$parameter->type' in '$handlerName'");

            }

            if (!empty($args)) {
                throw new InvalidRoute('Too many arguments passed to route handler method ' . $handlerName);
            }

            return $arguments;

        };


    }



    /**
     * Count parameters that come from the route (not DI)
     *
     * Counts parameters that are resolved from URL segments:
     * - Built-in types (string, int, float, bool)
     * - DateTime/DateTimeImmutable
     * - Enums (backed and non-backed)
     * - Variadic parameters
     *
     * Excludes class/interface types (resolved via DI).
     *
     * @param ReflectionMethod $method The method to analyze
     *
     * @return int Number of route parameters
     */
    private function countRouteParameters(ReflectionMethod $method): int {
        $count = 0;
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType) continue;

            $typeName = $type->getName();
            $isBuiltIn = $type->isBuiltin();

            // Count parameters that come from route (not DI)
            if ($isBuiltIn || $typeName === 'DateTime' || $typeName === 'DateTimeImmutable' || enum_exists($typeName)) {
                if ($parameter->isVariadic()) {
                    // Variadic counts as needing at least one wildcard
                    $count++;
                    break;
                }
                $count++;
            }
        }
        return $count;
    }



    /**
     * Get URI path
     *
     * Extracts the path from the request URI.
     *
     * @return string
     */
    protected function getUriPath(): string {
        /** @var string $request_uri */
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return trim(parse_url($request_uri, PHP_URL_PATH) ?: '', '/ ');
    }



}