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
    public function method(string $method): RouteInterface;



    /**
     * Override the default method name
     *
     * @param string $method_name
     *
     * @return RouteInterface
     */
    public function defaultMethod(string $method_name): RouteInterface;


}