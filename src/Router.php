<?php

declare(strict_types=1);

namespace Rammewerk\Router;

use BackedEnum;
use Closure;
use Rammewerk\Router\Definition\GroupDefinition;
use Rammewerk\Router\Definition\GroupInterface;
use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Definition\RouteInterface;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Foundation\Node;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Foundation\RouteUtility;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use const PHP_URL_PATH;

class Router {


    /** @var GroupDefinition|null Current group in a group closure or null outside */
    private ?GroupDefinition $active_group = null;
    private Node $radixRoot;



    /**
     * Create a new router instance
     *
     * @template T of object
     *
     * @param Closure(class-string<T>):T $dependencyHandler
     * The closure is used to generate instances of objects when needed. The closure are given a class-string and
     * should return a fully instantiated instance of the same class.
     *
     * @param string $default_method
     * The default method for a route class. Defaults to "index".
     *
     */
    public function __construct(
        protected Closure $dependencyHandler,
        private readonly string $default_method = 'index',
    ) {
        $this->radixRoot = new Node();
    }



    /**
     * Adds a new route to the application.
     *
     * @param string $pattern
     * @param Closure|class-string $handler The route handler to manage requests to this path.
     *
     * @return RouteInterface
     */
    public function add(string $pattern, Closure|string $handler): RouteInterface {
        $pattern = trim($pattern, '/ ');
        $route = new RouteDefinition($pattern, $handler);
        $this->radixRoot->insert($pattern, $route);
        $this->active_group?->registerRoute($route);
        return $route;
    }



    /**
     * Handles a group of routes
     *
     * @param Closure(Router):void $callback
     *
     * @return GroupInterface
     */
    public function group(Closure $callback): GroupInterface {
        $group = new GroupDefinition();
        $this->active_group = $group;                  // keep reference
        $callback($this);                              // user calls $router->add() inside
        $this->active_group = null;                    // free reference
        return $group;
    }



    /**
     * Finds and handles a route based on a provided path.
     *
     * The method locates a route using the input path and initiates its handler.
     *
     * @param string|null $path          The route path to locate. If not provided, the request_uri is used.
     * @param object|null $serverRequest Pass server request object to use for middleware and route handler.
     *
     * @return mixed The called route method or closure response
     * @throws InvalidRoute
     */
    public function dispatch(?string $path = null, object|null $serverRequest = null): mixed {

        $path = trim($path ?? $this->getUriPath(), '/ ');

        $node_route = $this->radixRoot->match($path);

        if (!$node_route) {
            throw new InvalidRoute("No route found for path: $path");
        }

        // Clone the route and reset the state
        $route = clone $node_route;
        $node_route->context = '';

        # Get lazy request handler
        $handler = $this->requestHandlerFactory($route, $serverRequest);

        // Return early if there are no middleware
        if (empty($route->middleware)) {
            return $handler($serverRequest);
        }

        $middlewareFactories = $this->createMiddlewareFactories($route->middleware);

        return $this->runPipeline($middlewareFactories, $handler, $serverRequest);

    }



    /**
     * @param RouteDefinition $route
     * @param object|null $serverRequest
     *
     * @return Closure
     * @throws InvalidRoute
     */
    private function requestHandlerFactory(RouteDefinition $route, object|null $serverRequest): Closure {

        $reflection = $this->reflectHandler($route);

        $argumentFactory = $this->resolveParameters($reflection, $route->getArguments(), $serverRequest);
        $className = (is_string($route->handler)) ? $route->handler : null;


        // Generate request handler factory by Closure-based route
        return function (object|null $request) use ($reflection, $argumentFactory, $className) {
            // Build arguments for handler
            $args = array_map(static fn($closure) => $closure($request), $argumentFactory);
            // Call request handler
            return $className
                ? $reflection->invokeArgs(($this->dependencyHandler)($className), $args)
                : $reflection->invokeArgs($args);
        };

    }



    /**
     * Reflect the route request handler
     *
     * @param RouteDefinition $route
     *
     * @return ReflectionFunction|ReflectionMethod
     * @throws InvalidRoute
     */
    public function reflectHandler(RouteDefinition $route): ReflectionFunction|ReflectionMethod {
        try {

            if ($route->handler instanceof Closure) {
                return new ReflectionFunction($route->handler);
            }

            return $this->resolveClassRoute(new ReflectionClass($route->handler), $route);

        } catch (ReflectionException $e) {
            throw new RouterConfigurationException(
                "Unable to reflect route handler for route: '$route->pattern' - {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }
    }



    /**
     * Resolve and call route method
     *
     * Will default to index if unable to resolve path to public method.
     * @template T of object
     *
     * @param ReflectionClass<T> $reflection
     * @param RouteDefinition $route
     *
     * @return ReflectionMethod
     * @throws InvalidRoute
     */
    private function resolveClassRoute(ReflectionClass $reflection, RouteDefinition $route): ReflectionMethod {

        // 1. If route->method explicitly set, always go straight to method
        if ($route->classMethod) {
            return $this->tryMethod($reflection, $route->classMethod)
                ?? throw new RouterConfigurationException("Route method '$route->classMethod' is explicitly defined, but not found");
        }

        // 2. If class supports Route attribute, we only resolve attributes
        if ($classRouteAttr = $reflection->getAttributes(Route::class)[0] ?? null) {
            return $this->resolveClassAttributes($reflection, $classRouteAttr, $route);
        }


        $method_name_context = str_replace('/', '_', $route->context);

        while ($method_name_context) {
            if ($method = $this->tryMethod($reflection, $method_name_context)) {
                $route->context = ltrim(substr($route->context, strlen($method_name_context)), '/');
                return $method;
            }
            RouteUtility::removeLastSegmentFromMethodName($method_name_context);
        }


        // 3. Fallback to default_method or __invoke
        if ($method = $this->tryMethod($reflection, $route->default_method ?? $this->default_method)) {
            return $method;
        }
        if ($method = $this->tryMethod($reflection, '__invoke')) {
            return $method;
        }

        throw new InvalidRoute("No valid method found in {$reflection->getName()}. Missing default method or __invoke()?");

    }



    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param string $method_name
     *
     * @return ?ReflectionMethod
     */
    private function tryMethod(ReflectionClass $reflection, string $method_name): ?ReflectionMethod {
        $method = $reflection->hasMethod($method_name) ? $reflection->getMethod($method_name) : null;
        return $method?->isPublic() ? $method : null;
    }



    /**
     * An array of middleware factories to handle creating instances of middleware
     *
     * @param array<class-string|object> $middlewareQueue
     *
     * @return array<Closure():object> The called method response.
     */
    protected function createMiddlewareFactories(array $middlewareQueue): array {
        return array_reverse(array_map(
            fn($middleware): Closure => function () use ($middleware): object {
                if (is_string($middleware)) {
                    return ($this->dependencyHandler)($middleware);
                }
                return $middleware;
            },
            $middlewareQueue,
        ));

    }



    /**
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
     * Validate parameters and create closures for resolving them
     *
     * @param ReflectionMethod|ReflectionFunction $handler
     * @param string[] $args
     * @param object|null $serverRequest
     *
     * @return array<int, Closure(object|null):mixed>
     * @throws InvalidRoute
     */
    private function resolveParameters(ReflectionMethod|ReflectionFunction $handler, array $args, object|null $serverRequest): array {

        $argumentClosures = array_map(function ($parameter) use (&$args, $serverRequest): Closure {

            if ($parameter->isVariadic()) {
                $variadic_arguments = $args;
                $args = [];
                return static fn() => $variadic_arguments;
            }

            $type = $parameter->getType();

            /** Handle class parameters */
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string $className */
                $className = $type->getName();

                // Use the given instance of request handler, if parameter is of same type
                if ($serverRequest && $serverRequest instanceof $className) {
                    return static fn(object|null $req) => $req;
                }

                // Then backed enum check:
                if (is_subclass_of($className, BackedEnum::class)) {
                    $arg = array_shift($args);
                    return static function () use ($arg, $className) {
                        return $arg === null
                            ? null
                            : $className::from($arg);
                    };
                }

                # Check if parameter is a backed enum
                return fn() => ($this->dependencyHandler)($className);


            }

            $arg = array_shift($args);

            if (is_null($arg) && $parameter->isOptional()) {
                return static fn() => $parameter->getDefaultValue();
            }

            if (is_null($arg) && $parameter->allowsNull()) {
                return static fn() => null;
            }

            # Handle built-in type
            if (!is_null($arg) && $type instanceof ReflectionNamedType) {
                return static fn() => $arg;
            }

            # Handle union type of built-in types
            if (
                !is_null($arg) && $type instanceof ReflectionUnionType && array_any(
                    $type->getTypes(),
                    static fn($unionType) => $unionType instanceof ReflectionNamedType && $unionType->isBuiltIn(),
                )
            ) {
                return static fn() => $arg;
            }

            if (!is_null($arg)) {
                throw new InvalidRoute('Unable to resolve parameter: $' . $parameter->getName() . ' with value: ' . $arg);
            }

            throw new InvalidRoute('No arguments left to resolve parameter: ' . $parameter->getName());

        }, $handler->getParameters());

        if (!empty($args)) {
            throw new InvalidRoute('Too many arguments passed to route handler');
        }

        return $argumentClosures;

    }



    /**
     * Get URI path
     *
     * @return string
     */
    protected function getUriPath(): string {
        /** @var string $request_uri */
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return trim(parse_url($request_uri, PHP_URL_PATH) ?: '', '/');
    }



    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param ReflectionAttribute<Route> $classRouteAttr
     * @param RouteDefinition $route
     *
     * @return ReflectionMethod
     * @throws InvalidRoute
     */
    private function resolveClassAttributes(ReflectionClass $reflection, ReflectionAttribute $classRouteAttr, RouteDefinition $route): ReflectionMethod {

        $classRoute = $classRouteAttr->newInstance()->path;

        $pattern = $route->pattern;
        $base_segment = RouteUtility::extractFirstSegment($pattern);

        // Validate that the base segment matches the class-level route
        if (trim($classRoute, '/ ') !== $base_segment) {
            throw new RouterConfigurationException("Route mismatch: Class-level route attribute '$classRoute' does not match added base segment '/$base_segment'");
        }

        // Iterate over methods to find one with a matching Route attribute
        foreach ($reflection->getMethods() as $method) {
            if ($attribute = $method->getAttributes(Route::class)[0] ?? null) {
                $methodRoute = $attribute->newInstance()->path;
                // Check if the remaining context matches the method-level route
                if ($this->matchAttributeRoute($methodRoute, $route)) {
                    return $method;
                }
            }
        }

        throw new InvalidRoute("No matching Route attribute found in class {$reflection->getName()} for route '$route->pattern'");


    }



    /**
     * Match a route pattern to the given context and extract parameters.
     *
     * @return bool True if the pattern matches; false otherwise.
     */
    private function matchAttributeRoute(string $attributeRoute, RouteDefinition $route): bool {
        $attributeRoute = trim($attributeRoute, '/ ');# Count the number of / in the route pattern

        // Quick optimization: If the pattern is longer than the context, it can't match
        if (substr_count($attributeRoute, '/') > substr_count($route->context, '/')) {
            return false;
        }



        $context = $route->context;
        $args = '';


        while ($attributeRoute) {
            if (!$context) return false;
            $contextSegment = RouteUtility::extractFirstSegment($context);
            $attributeSegment = RouteUtility::extractFirstSegment($attributeRoute);
            if ($attributeSegment === '*') {
                RouteUtility::appendSegment($args, $contextSegment);
            } else if ($attributeSegment !== $contextSegment) {
                return false;
            }
        }


        RouteUtility::appendSegment($args, $context);
        $route->context = $args;
        return true;
    }



}