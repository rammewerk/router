<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

interface RouteInterface {

    /**
     * Define middleware to run before handler
     *
     * @param array<class-string|object> $middleware
     *
     * @return RouteInterface
     */
    public function middleware(array $middleware): RouteInterface;



    /**
     * Set the class method to call
     *
     * @param string $method
     *
     * @return RouteInterface
     */
    public function classMethod(string $method): RouteInterface;



    /**
     * Disables reflection for this route.
     *
     * When called, this prevents the Router from using PHP's Reflection API
     * to inspect the handler's parameters. This can improve performance,
     * especially in high-throughput scenarios
     *
     * @return RouteInterface
     */
    public function disableReflection(): RouteInterface;



}