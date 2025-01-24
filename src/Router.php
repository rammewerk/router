<?php

declare(strict_types=1);

namespace Rammewerk\Router;

use Closure;
use LogicException;
use Rammewerk\Router\Definition\GroupDefinition;
use Rammewerk\Router\Definition\GroupInterface;
use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Definition\RouteInterface;
use Rammewerk\Router\Error\InvalidRoute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use const PHP_URL_PATH;

class Router {

    /** @var array<string, RouteDefinition[]> List of routes */
    private array $routes = [];

    /** @var GroupDefinition|null Current group in a group closure or null outside */
    private ?GroupDefinition $active_group = null;



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
    ) {}



    /**
     * Adds a new route to the application.
     *
     * @param string $pattern
     * @param Closure|class-string $handler The route handler to manage requests to this path.
     *
     * @return RouteInterface
     */
    public function add(string $pattern, Closure|string $handler): RouteInterface {

        $pattern = trim($pattern, '/');
        $baseSegment = explode('/', $pattern, 2)[0];

        // Convert segments '*' => '([^/]+)' otherwise preg_quote()
        $segments = array_map(
            static fn(string $p) => $p === '*' ? '([^/]+)' : preg_quote($p, '#'),
            explode('/', $pattern),
        );

        // Join segments and then allow trailing leftover
        $regex = '#^' . implode('/', $segments) . '(?:/(.*))?$#';

        $route = new RouteDefinition($regex, $handler);

        // If grouping, also attach route here
        $this->active_group?->registerRoute($route);

        return $this->routes[$baseSegment][] = $route;

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
        $this->active_group = $group;   // keep reference
        $callback($this);               // user calls $router->add() inside
        $this->active_group = null;     // free reference
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

        $path = $path ?? $this->getUriPath();

        $pattern = trim($path, '/');
        $baseSegment = explode('/', $pattern, 2)[0];

        // Fallback to root if not found
        if (!isset($this->routes[$baseSegment]) && isset($this->routes[''])) {
            $baseSegment = '';
            $pattern = '/' . $pattern;
        }

        if (!isset($this->routes[$baseSegment])) {
            throw new InvalidRoute('No route found for path: ' . $path);
        }

        // Sort by regex length (desc) so longer patterns match first
        usort($this->routes[$baseSegment], static fn($a, $b) => strlen($b->regex) <=> strlen($a->regex));

        foreach ($this->routes[$baseSegment] as $route) {
            if (preg_match($route->regex, $pattern, $matches)) {

                $route->setMatchesFromPath($matches);

                # Get lazy request handler
                $handler = $this->requestHandlerFactory($route, $serverRequest);

                // Return early if there are no middleware
                if (empty($route->middleware)) {
                    return $handler($serverRequest);
                }

                $middlewareFactories = $this->createMiddlewareFactories($route->middleware);

                return $this->runPipeline($middlewareFactories, $handler, $serverRequest);

            }
        }

        throw new InvalidRoute('No route found for path: ' . $path);

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

        $argumentFactory = $this->resolveParameters($reflection, $route->args, $serverRequest);
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
     */
    public function reflectHandler(RouteDefinition $route): ReflectionFunction|ReflectionMethod {
        try {

            if ($route->handler instanceof Closure) {
                $route->args = $route->context;
                $route->context = [];
                return new ReflectionFunction($route->handler);
            }

            return $this->resolveClassMethod(new ReflectionClass($route->handler), $route);

        } catch (ReflectionException $e) {
            throw new LogicException('Unable to reflect route handler: ' . $e->getMessage(), $e->getCode(), $e);
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
     */
    private function resolveClassMethod(ReflectionClass $reflection, RouteDefinition $route): ReflectionMethod {

        // 1. If route->method explicitly set
        if ($route->method) {
            $route->args = $route->context;
            $route->context = [];
            return $this->tryMethod($reflection, $route->method)
                ?? throw new LogicException("Route method '$route->method' is explicitly defined, but not found");
        }

        // 2. Try "context_method" until exhausted
        while ($route->context) {
            if ($method = $this->tryMethod($reflection, implode('_', $route->context))) {
                return $method;
            }
            // Move the last context element to the beginning of argument list
            array_unshift($route->args, array_pop($route->context));
        }

        // 3. Fallback to default_method or __invoke
        if ($method = $this->tryMethod($reflection, $route->default_method ?? $this->default_method)) {
            return $method;
        }
        if ($method = $this->tryMethod($reflection, '__invoke')) {
            return $method;
        }

        throw new LogicException("No valid method found in {$reflection->getName()}. Missing default method or __invoke()?");

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
                    throw new LogicException('Required middleware method handle() is missing');
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
                $value = $this->convertParameterType($type->getName(), $arg);
                if (!is_null($value)) {
                    return static fn() => $value;
                }
            }

            # Handle union type of built-in types
            if (!is_null($arg) && $type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $unionType) {
                    if ($unionType instanceof ReflectionNamedType && $unionType->isBuiltIn()) {
                        $value = $this->convertParameterType($unionType->getName(), $arg);
                        if (!is_null($value)) {
                            return static fn() => $value;
                        }
                    }
                }
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
     * Convert argument to match parameter type
     *
     * @param string $type
     * @param string $arg
     *
     * @return bool|float|int|string|null
     */
    protected function convertParameterType(string $type, string $arg): bool|float|int|string|null {
        return match ($type) {
            'string', 'mixed' => $arg,
            'int'             => filter_var($arg, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float'           => filter_var($arg, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool'            => filter_var($arg, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            default           => null,
        };
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



}