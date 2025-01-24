<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Closure;
use LogicException;

final class RouteDefinition implements RouteInterface {

    /** @var array<class-string|object> List of middleware to run before handler */
    public private(set) array $middleware = [];

    /** @var string|null The class method to call */
    public private(set) ?string $method = null;

    /** @var string[] The matched subpath segments */
    public array $context = [];

    /** @var string[] The matched arguments */
    public array $args = [];

    /** @var ?string The route handler default method */
    public private(set) ?string $default_method = null;



    /**
     * @param string $regex
     * @param class-string|Closure $handler
     */
    public function __construct(
        public string $regex,
        public string|Closure $handler,
    ) {}



    /**
     * Add matches from preg_match
     *
     * @param string[] $matches
     *
     * @return void
     */
    public function setMatchesFromPath(array $matches): void {

        // First match is full match, drop it
        array_shift($matches);

        // last group is leftover
        $leftover = array_pop($matches) ?? '';

        $this->context = $matches;

        if ($leftover !== '') {
            $this->context = array_merge($this->context, explode('/', $leftover));
        }

    }



    /** @inheritDoc */
    public function middleware(array $middleware): RouteInterface {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }



    /** @inheritDoc */
    public function method(string $method): RouteInterface {
        if ($this->handler instanceof Closure) {
            throw new LogicException('Setting method on closure route is not supported');
        }
        $this->method = $method;
        return $this;
    }



    /** @inheritDoc */
    public function defaultMethod(string $method_name): RouteInterface {
        $this->default_method = $method_name;
        return $this;
    }


}