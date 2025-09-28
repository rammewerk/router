<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

interface RouteInterface {

    /**
     * Define middleware to run before the handler
     *
     * @param array<class-string|object> $middleware
     * @param bool $prepend // Add middleware before previous registered middleware
     *
     * @return RouteInterface
     */
    public function middleware(array $middleware, bool $prepend = false): RouteInterface;



}