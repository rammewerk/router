<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Closure;
use ReflectionFunction;
use ReflectionMethod;


abstract class RouteDefinition implements RouteInterface {

    /** @var array<class-string|object> List of middleware to run before handler */
    public protected(set) array $middleware = [];

    /** @var bool Disables reflection for this route */
    public protected(set) bool $skipReflection = false;

    public string $nodeContext = '';

    /** @var string The leftover part of the path still being worked on. */
    public string $context = '';

    /** @var null|Closure(mixed[], object|null): mixed */
    public ?Closure $factory = null;

    /** @var null|ReflectionMethod|ReflectionFunction Reflection cache */
    public ReflectionMethod|ReflectionFunction|null $reflection = null;



    /** @inheritDoc */
    public function middleware(array $middleware, bool $prepend = false): RouteInterface {
        $this->middleware = $prepend
            ? array_merge($middleware, $this->middleware)
            : array_merge($this->middleware, $middleware);
        if ($this->skipReflection) {
            throw new \LogicException('Middleware is not supported unless reflection is enabled');
        }
        return $this;
    }



    /**
     * @return string[]
     */
    public function getArguments(): array {
        return $this->context !== '' ? \explode('/', $this->context) : [];
    }



}