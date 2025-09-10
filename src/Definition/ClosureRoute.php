<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Closure;
use Rammewerk\Router\Error\RouterConfigurationException;

class ClosureRoute extends RouteDefinition {


    /**
     * @param string $pattern
     * @param Closure $handler
     */
    public function __construct(
        public readonly string $pattern,
        public readonly Closure $handler,
    ) {}



    public function classMethod(string $method): RouteInterface {
        throw new RouterConfigurationException('Defining a class method on a callable (closure or array) is not supported');
    }



    public function disableReflection(): RouteInterface {
        $this->skipReflection = true;
        if ($this->middleware) {
            throw new RouterConfigurationException('Middleware is not supported unless reflection is enabled');
        }
        return $this;
    }



}