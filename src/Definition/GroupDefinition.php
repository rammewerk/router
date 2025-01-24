<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

final class GroupDefinition implements GroupInterface {

    /** @var RouteDefinition[] */
    private array $routes = [];



    /** Define a route within the group */
    public function registerRoute(RouteDefinition $definition): void {
        $this->routes[] = $definition;
    }



    /** @inheritDoc */
    public function middleware(array $middleware): GroupInterface {
        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }
        return $this;
    }


}