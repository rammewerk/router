<?php


namespace Rammewerk\Component\Router;

abstract class Route implements RouteInterface {

    /**
     * Check if request has access to route
     *
     * The router will check if the given request has access before calling any route methods.
     *
     * @return bool
     */
    public function hasRouteAccess(): bool {
        return true;
    }




    /**
     * Route index
     *
     * All route must have a default fallback to index
     *
     * @return void
     */
    abstract public function index(): void;

}