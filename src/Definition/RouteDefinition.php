<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;

final class RouteDefinition implements RouteInterface {

    /** @var array<class-string|object> List of middleware to run before handler */
    public private(set) array $middleware = [];

    /** @var string|null The class method to call */
    public private(set) ?string $classMethod = null;

    /** @var ?string The route handler default method */
    public private(set) ?string $default_method = null;

    public string $nodeContext = '';

    /** @var string The leftover part of the path still being worked on. */
    public string $context = '';

    /** @var null|Closure(mixed[], object|null): mixed */
    public ?Closure $factory = null;

    /**
     * @var mixed|ReflectionMethod
     */
    public ReflectionMethod|ReflectionFunction|null $reflection = null;



    /**
     * @param string $pattern
     * @param class-string|Closure $handler
     */
    public function __construct(
        public readonly string $pattern,
        public string|Closure $handler,
    ) {}



    /** @inheritDoc */
    public function middleware(array $middleware): RouteInterface {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }



    /** @inheritDoc */
    public function classMethod(string $method): RouteInterface {
        if ($this->handler instanceof Closure) {
            throw new LogicException('Setting method on closure route is not supported');
        }
        $this->classMethod = $method;
        return $this;
    }



    /** @inheritDoc */
    public function defaultMethod(string $method_name): RouteInterface {
        $this->default_method = $method_name;
        return $this;
    }



    /**
     * @return string[]
     */
    public function getArguments(): array {
        return $this->context ? explode('/', $this->context) : [];
    }



}