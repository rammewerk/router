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



}