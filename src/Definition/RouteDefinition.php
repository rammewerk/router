<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use function explode;

final class RouteDefinition implements RouteInterface {

    /** @var array<class-string|object> List of middlewares to run before the handler */
    public protected(set) array $middleware = [];

    /** @var array<RouteHandler> Method-specific route handlers */
    public array $handlers = [];

    public string $nodeContext = '';

    /** @var string The leftover part of the path still being worked on. */
    public string $context = '';



    /**
     * @param string $pattern
     * @param class-string $routeClass
     */
    public function __construct(
        public readonly string $pattern,
        public readonly string $routeClass,
    ) {}



    /** @inheritDoc */
    public function middleware(array $middleware, bool $prepend = false): RouteInterface {
        $this->middleware = $prepend
            ? array_merge($middleware, $this->middleware)
            : array_merge($this->middleware, $middleware);
        return $this;
    }



    /**
     * @return string[]
     */
    public function getArguments(): array {
        return $this->context !== '' ? explode('/', $this->context) : [];
    }



    /**
     * Find a handler that supports the given HTTP method
     */
    public function getHandlerForMethod(string $method): ?RouteHandler {
        return array_find($this->handlers, static fn(RouteHandler $handler) => $handler->supportsMethod($method));
    }



    /**
     * Add a route handler for specific HTTP methods
     */
    public function addHandler(RouteHandler $handler): void {
        $this->handlers[] = $handler;
    }



}