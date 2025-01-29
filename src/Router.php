<?php

# No strict types!
declare(strict_types=0);

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
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use const PHP_URL_PATH;

/**
 * Rammewerk Router
 *
 * A fast, simple and flexible router for PHP
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
    public array $staticRoutes = [];

    private Node $node;
    private string $path = '';
    private array $classReflections = [];



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
        $this->node = new Node();
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
        if (!str_contains($pattern, '*')) {
            $this->staticRoutes[$pattern] = $route;
        }
        $this->node->insert($pattern, $route);
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

        $this->path = $path;

        // If path matches static route, the path given has no extra context/arguments
        $route = $this->staticRoutes[$path] ?? $this->node->match($path);

        if (!$route) {
            throw new InvalidRoute("No route found for path: $path");
        }

        // Reset nodeContext
        $route->context = $route->nodeContext;
        $route->nodeContext = '';

        # What we're left with is a context that must be matched to a method, else it is parameters.

        # Get lazy request handler

        if (!$route->factory) {
            $route = $this->requestHandlerFactory($route);
        }

        $args = $route->getArguments();

        // Return early if there are no middleware
        if (empty($route->middleware)) {
            return ($route->factory)($args, $serverRequest);
        }

        $pipelineHandler = static function (object|null $request) use ($route, $args) {
            return ($route->factory)($args, $request);
        };

        $middlewareFactories = $this->createMiddlewareFactories($route->middleware);

        return $this->runPipeline($middlewareFactories, $pipelineHandler, $serverRequest);

    }



    /**
     * @param RouteDefinition $route
     * @param object|null $serverRequest
     *
     * @return Closure
     * @throws InvalidRoute
     */
    private function requestHandlerFactory(RouteDefinition $route): RouteDefinition {

        # Either we have a reflection or we need to reflect the handler
        # We should change this by returning the new route instead of the reflection
        if (!$route->reflection) {
            $route = $this->reflectHandler($route);
        }

        if (!$route->reflection) {
            throw new InvalidRoute("Unable to reflect route handler for route: '$route->pattern'");
        }

        $argumentFactory = $this->getParameterClosure($route->reflection);
        $route->reflection = null;

        if (is_string($route->handler)) {
            $route->factory = function (array $context, object|null $request) use ($route, $argumentFactory) {
                $instance = ($this->dependencyHandler)($route->handler);
                $args = $argumentFactory($context, $request);
                return call_user_func_array([$instance, $route->classMethod], $args);
            };
            return $route;
        }

        // Generate request handler factory by Closure-based route
        $route->factory = static function (array $context, object|null $request) use ($route, $argumentFactory) {
            $args = $argumentFactory($context, $request);
            return ($route->handler)(...$args);
        };
        return $route;

    }



    /**
     * Reflect the route request handler
     *
     * @param RouteDefinition $route
     *
     * @return RouteDefinition
     * @throws InvalidRoute
     */
    public function reflectHandler(RouteDefinition $route): RouteDefinition {
        try {

            if ($route->handler instanceof Closure) {
                if (!$route->reflection) {
                    $route->reflection = new ReflectionFunction($route->handler);
                }
                return $route;
            }

            $classReflection = $this->classReflections[$route->handler] ?? null;
            if (!$classReflection) {
                $this->classReflections[$route->handler] = $classReflection = new ReflectionClass($route->handler);
            }

            return $this->resolveClassRoute($classReflection, $route);

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
     * @return RouteDefinition
     * @throws InvalidRoute
     * @throws ReflectionException
     */
    private function resolveClassRoute(ReflectionClass $reflection, RouteDefinition $route): RouteDefinition {

        // 1. If route->method explicitly set, always go straight to method
        if ($route->classMethod) {
            $route->reflection = $reflection->getMethod($route->classMethod);
            if ($route->reflection->isPublic()) {
                return $route;
            }
            throw new RouterConfigurationException("Route method '$route->classMethod' is not a public method");
        }

        // 2. If class supports Route attribute, we only resolve attributes
        if ($classRouteAttr = $reflection->getAttributes(Route::class)[0] ?? null) {
            return $this->resolveClassAttributes($reflection, $classRouteAttr, $route);
        }

        // Cache class methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodPath = $this->convertClassMethodNameToPath($method->getName());
            $pattern = $route->pattern;
            if ($methodPath !== 'index' && $methodPath !== '__invoke') {
                RouteUtility::appendSegment($pattern, $methodPath);
            }
            $new_route = $this->createNewSpecificRoute($pattern, $method->getName(), $route->handler);
            $new_route->context = '';
            if (!$new_route->classMethod) {
                $new_route->classMethod($method->getName());
            }
            $new_route->reflection = $method;
        }

        # Can we here just use radix tree to get the correct method?

        $replace_route = $this->node->match($this->path);
        if ($replace_route) {
            $route->context = '';
            $replace_route->middleware($route->middleware);
            $replace_route->context = $replace_route->nodeContext;
            $replace_route->nodeContext = '';
            return $replace_route;
        }

        throw new InvalidRoute("No valid method found in {$reflection->getName()}. Missing default method or __invoke()?");
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
     * @param ReflectionMethod|ReflectionFunction $handler
     *
     * @return Closure(mixed[], object|null): mixed[]
     * @throws ReflectionException
     */
    public function getParameterClosure(ReflectionMethod|ReflectionFunction $handler): Closure {

        $handlerName = $handler->getName();
        $parameterClosures = [];

        foreach ($handler->getParameters() as $parameter) {
            $set = new RouteParameter();
            $set->variadic = $parameter->isVariadic();
            $set->optional = $parameter->isOptional();
            $set->nullable = $parameter->allowsNull();
            $set->builtIn = $parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->isBuiltin();
            $set->type = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : '';
            $set->defaultValue = $parameter->isOptional() ? $parameter->getDefaultValue() : null;
            $set->isUnionType = $parameter->getType() instanceof ReflectionUnionType;
            $parameterClosures[] = $set;
        }

        return function (array $args, object|null $request) use ($parameterClosures, $handlerName) {

            $arguments = array_map(function (RouteParameter $parameter) use (&$args, $request, $handlerName) {

                if ($parameter->variadic) {
                    $value = $args;
                    $args = [];
                    return $value;
                }

                if (!$parameter->builtIn) {

                    // Use the given instance of request handler, if parameter is of same type
                    if ($request && $request instanceof $parameter->type) {
                        return $request;
                    }

                    // Then backed enum check:
                    if (is_subclass_of($parameter->type, BackedEnum::class)) {
                        $arg = array_shift($args);
                        /** @phpstan-ignore-next-line We will always just try the given argument */
                        return $arg !== null ? $parameter->type::from($arg) : $parameter->defaultValue;
                    }

                    /** @phpstan-ignore-next-line Complains about not being a class-string */
                    return ($this->dependencyHandler)($parameter->type);

                }

                $arg = array_shift($args);

                if (!is_null($arg)) {
                    return $arg;
                }

                if (($parameter->optional || $parameter->nullable)) {
                    return $parameter->defaultValue;
                }

                throw new InvalidRoute("No arguments left to resolve parameter: $parameter->type in $handlerName");

            }, $parameterClosures);

            if (!empty($args)) {
                throw new InvalidRoute('Too many arguments passed to route handler method ' . $handlerName);
            }

            return $arguments;

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



    /**
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

        $pattern = $route->pattern;
        $base_segment = RouteUtility::extractFirstSegment($pattern);

        // Validate that the base segment matches the class-level route
        if (trim($classRoute, '/ ') !== $base_segment) {
            throw new RouterConfigurationException("Route mismatch: Class-level route attribute '$classRoute' does not match added base segment '/$base_segment'");
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($attribute = $method->getAttributes(Route::class)[0] ?? null) {
                $path = trim($attribute->newInstance()->path, '/ ');
                if (str_starts_with($path, $classRoute)) {
                    $path = trim(substr($path, strlen($classRoute)), '/ ');
                }
                $addPath = $path;
                RouteUtility::prependSegment($addPath, $classRoute);
                $new_route = $this->createNewSpecificRoute($addPath, $method->getName(), $route->handler);
                if (!$new_route->classMethod) {
                    $new_route->classMethod($method->getName());
                }
                $new_route->reflection = $method;
                $new_route->context = '';
            }
        }

        $replace_route = $this->node->match($this->path);
        if ($replace_route) {
            $route->context = '';
            $replace_route->middleware($route->middleware);
            $replace_route->context = $replace_route->nodeContext;
            $replace_route->nodeContext = '';
            return $replace_route;
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



    /**
     * Extend routes with found class methods
     *
     * @param string $path
     * @param string $method
     * @param class-string|Closure $handler
     *
     * @return RouteDefinition
     */
    private function createNewSpecificRoute(string $path, string $method, string|Closure $handler): RouteDefinition {
        $path = trim($path, '/ ');
        if (isset($this->staticRoutes[$path])) return $this->staticRoutes[$path];
        /** @var @phpstan-ignore-next-line */
        return $this->add($path, $handler)->classMethod($method);
    }



    public function convertClassMethodNameToPath(string $method_name): string {
        return str_replace('_', '/', $method_name);
    }


}