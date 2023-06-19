<?php

namespace Rammewerk\Component\Router;

interface RouteInterface {

    /**
     * Check if request has access to route
     *
     * The router will check if the given request has access before calling any route methods.
     *
     * @return bool
     */
    public function hasRouteAccess(): bool;

}