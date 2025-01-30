<?php

declare(strict_types=1);

namespace Rammewerk\Router;

use BackedEnum;
use Closure;
use Rammewerk\Router\Definition\GroupDefinition;
use Rammewerk\Router\Definition\GroupInterface;
use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Definition\RouteInterface;
use Rammewerk\Router\Definition\RouteParameter;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Foundation\Node;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Foundation\RouteUtility;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use const PHP_URL_PATH;

/**
 * RAMMEWERK ROUTER
 *
 * Lightweight and fast PHP router for modern apps
 * Please notice: Code is written for performance and not for elegance.
 * It uses many "old" ways to doing things, because it is simply faster
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

    /** @var Node Radix tree of routes */
    private Node $node;

    /** @var string The current path being matched */
    private string $path = '';



    /**
     * Create a new router instance
     *
     * Initializes the router with a dependency handler and sets up the routing tree.
     *
     * @template T of object
     *
     * @param Closure(class-string<T>):T $dependencyHandler
     * The closure is used to generate instances of objects when needed. The closure are given a class-string and
     * should return a fully instantiated instance of the same class.
     *
     */
    public function __construct(
        protected Closure $dependencyHandler,
    ) {
        $this->node = new Node();
    }



    /**
     * Adds a new route to the application.
     *
     * Registers a route with a URI pattern and a handler.
     *
     * @param string $pattern               URI pattern to match against
     * @param Closure|class-string $handler The handler to manage requests
     *
     * @return RouteInterface
     */
    public function add(string $pattern, Closure|string $handler): RouteInterface {
        $pattern = trim($pattern, '/ ');
        $route = new RouteDefinition($pattern, $handler);
        $this->routes[$pattern] = $route;
        $this->node->insert($pattern, $route);
        $this->active_group?->registerRoute($route);
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
     * Finds and handles a route based on a provided path.
     *
     * Resolves a route, executes its handler, and returns the response.
     *
     * @param string|null $path          The route path to locate. If not provided, the request_uri is used.
     * @param object|null $serverRequest Pass server request object to use for middleware and route handler.
     *
     * @return mixed The called route method or closure response
     * @throws InvalidRoute
     */
    public function dispatch(?string $path = null, object|null $serverRequest = null): mixed {

        $path = $this->path = trim($path ?? $this->getUriPath(), '/ ');

        $route = $this->routes[$path]
            ?? $this->node->match($path)
            ?? throw new InvalidRoute("No route found for path: $path");

        // Reset nodeContext
        $route->context = $route->nodeContext;
        $route->nodeContext = '';

        if (!$route->factory) {
            $route = $this->requestHandlerFactory($route);
        }

        $handlerFactory = $route->factory ?? throw new InvalidRoute("Unable to handle route for: '$path'");
        $arguments = $route->getArguments();

        return $route->middleware ? $this->runPipeline(
            $this->createMiddlewareFactories($route->middleware),
            static fn(object|null $serverRequest) => $handlerFactory($arguments, $serverRequest),
            $serverRequest,
        ) : $handlerFactory($arguments, $serverRequest);

    }



    /**
     * Creates a request handler factory
     *
     * Processes the route handler and prepares it for execution.
     *
     * @param RouteDefinition $route
     *
     * @return RouteDefinition
     * @throws InvalidRoute
     */
    private function requestHandlerFactory(RouteDefinition $route): RouteDefinition {

        if (!$route->reflection) {
            $route = $this->reflectHandler($route);
        }

        if (!$route->reflection) {
            throw new InvalidRoute("Unable to reflect route handler for route: '/$route->pattern'");
        }

        try {
            $argumentFactory = $this->getParameterClosure($route->reflection);
        } catch (ReflectionException $e) {
            throw new InvalidRoute("Unable to reflect route handler for route: '/$route->pattern' - {$e->getMessage()}");
        }

        $route->reflection = null; // Free up memory
        $handler = $route->handler;

        if (is_string($handler)) {
            $method = $route->classMethod;
            $container = $this->dependencyHandler;
            $route->factory = static function (array $context, object|null $request) use ($container, $handler, $method, $argumentFactory) {
                return $container($handler)->{$method}(...$argumentFactory($context, $request));
            };
        } else {
            $route->factory = static function (array $context, object|null $request) use ($handler, $argumentFactory) {
                return $handler(...$argumentFactory($context, $request));
            };
        }

        return $route;

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

            if ($route->handler instanceof Closure) {
                $route->reflection = new ReflectionFunction($route->handler);
                return $route;
            }

            // 1. If route->method explicitly set, always go straight to method
            if ($route->classMethod) {
                $route->reflection = new ReflectionMethod($route->handler, $route->classMethod);
                return $route->reflection->isPublic()
                    ? $route
                    : throw new RouterConfigurationException("Route method '$route->classMethod' is not a public method");
            }

            $classReflection = new ReflectionClass($route->handler);

            // 2. If class supports Route attribute, we only resolve attributes
            if ($classRouteAttr = $classReflection->getAttributes(Route::class)[0] ?? null) {
                return $this->resolveClassAttributes($classReflection, $classRouteAttr, $route);
            }

            return $this->resolveClassRoute($classReflection, $route);

        } catch (ReflectionException $e) {
            throw new RouterConfigurationException(
                "Unable to reflect route handler for route: '/$route->pattern' - {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }
    }



    /**
     * Resolve and call route method
     *
     * Determines the correct method to call within a route class.
     *
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param RouteDefinition $route
     *
     * @return RouteDefinition
     * @throws InvalidRoute
     */
    private function resolveClassRoute(ReflectionClass $reflection, RouteDefinition $route): RouteDefinition {

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_path = RouteUtility::convertMethodNameToPath($method->getName());
            $pattern = $route->pattern;
            if ($method_path !== 'index' && $method_path !== '__invoke') {
                RouteUtility::appendSegment($pattern, $method_path);
            }
            $this->addInternal($pattern, $route->handler, $method);
        }

        return $this->swapRoute($route)
            ?? throw new InvalidRoute("No valid method found in {$reflection->getName()}. Missing default method or __invoke()?");
    }



    /**
     * Resolve class attributes for routing
     *
     * Parses and applies route attributes on a class.
     *
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param ReflectionAttribute<Route> $classRouteAttr
     * @param RouteDefinition $route
     *
     * @return RouteDefinition
     * @throws InvalidRoute
     */
    private function resolveClassAttributes(ReflectionClass $reflection, ReflectionAttribute $classRouteAttr, RouteDefinition $route): RouteDefinition {

        $classRoute = trim($classRouteAttr->newInstance()->path, '/ ');

        // Validate that the base segment matches the class-level route
        if (!str_starts_with($route->pattern, $classRoute)) {
            throw new RouterConfigurationException("Route mismatch. Class attribute with path '$classRoute' doesn't match added base segment '/$route->pattern'");
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($attribute = $method->getAttributes(Route::class)[0] ?? null) {
                $path = trim($attribute->newInstance()->path, '/ ');
                if (!str_starts_with($path, $classRoute)) {
                    RouteUtility::prependSegment($path, $classRoute);
                }
                $this->addInternal($path, $route->handler, $method);
            }
        }

        return $this->swapRoute($route)
            ?? throw new InvalidRoute("No matching Route attribute found in class {$reflection->getName()} for route '$route->pattern'");

    }



    /**
     * Registers an internal route
     *
     * Adds a route and assigns its reflection method.
     *
     * @param string $pattern
     * @param Closure|class-string $handler
     * @param ReflectionMethod $reflection
     *
     * @return void
     */
    private function addInternal(string $pattern, string|Closure $handler, ReflectionMethod $reflection): void {
        /** @var RouteDefinition $route */
        $route = $this->routes[$pattern] ?? $this->add($pattern, $handler);
        if (!$route->classMethod) {
            $route->classMethod($reflection->getName());
        }
        if (!$route->factory && !$route->reflection) {
            $route->reflection = $reflection;
        }
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
        $swap_route->middleware($route->middleware);
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
     * Generates parameter closure
     *
     * Resolves route parameters using reflection.
     *
     * @param ReflectionMethod|ReflectionFunction $handler
     *
     * @return Closure
     * @throws ReflectionException
     */
    public function getParameterClosure(ReflectionMethod|ReflectionFunction $handler): Closure {

        $handlerName = $handler->getName();
        $parameterClosures = [];

        foreach ($handler->getParameters() as $parameter) {
            $set = new RouteParameter();
            $set->name = $parameter->getName();
            $set->variadic = $parameter->isVariadic();
            $set->optional = $parameter->isOptional();
            $set->nullable = $parameter->allowsNull();
            $set->builtIn = $parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->isBuiltin();
            $set->type = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : '';
            $set->defaultValue = $parameter->isOptional() && $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

            if (!$set->builtIn && $set->type && is_subclass_of($set->type, BackedEnum::class)) {
                $set->enum = new ReflectionEnum($set->type)->getBackingType()?->getName();
            }

            $parameterClosures[] = $set;
        }

        /**
         * @param string[] $args
         * @param object|null $request
         *
         * @return Closure(array<string,mixed>, object|null): mixed[]
         * @throws RouterConfigurationException|InvalidRoute
         */
        return function (array $args, object|null $request) use ($parameterClosures, $handlerName) {

            $arguments = [];

            foreach ($parameterClosures as $parameter) {

                // Variadic must be the last parameter
                if ($parameter->variadic) {
                    $arguments = [...$arguments, ...$args];
                    $args = [];
                    break;
                }

                if (!$parameter->builtIn) {

                    // Use the given instance of request handler, if parameter is of same type
                    if ($request && $request instanceof $parameter->type) {
                        $arguments[] = $request;
                        continue;
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

                    // Then non-backed enum check based on case name
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

                    /** @phpstan-ignore-next-line Complains about not being a class-string */
                    $arguments[] = ($this->dependencyHandler)($parameter->type);
                    continue;

                }

                $arg = array_shift($args);

                if (!is_null($arg)) {
                    $arg = match ($parameter->type) {
                        'int'      => filter_var($arg, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                        'float'    => filter_var($arg, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                        'bool'     => filter_var($arg, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                        'array',
                        'object',
                        'callable',
                        'iterable',
                        'resource' => throw new RouterConfigurationException("The parameter '$parameter->name' in route method '$handlerName' has an unsupported type: '$parameter->type'."),
                        default    => $arg,
                    };
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