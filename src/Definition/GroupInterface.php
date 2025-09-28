<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

interface GroupInterface {

    /**
     * Add middlewares to a group
     *
     * Middlewares are executed in the order they are added. They will run before the handler and before any
     * middlewares added to the entry points within the group.
     *
     * @param array<class-string|object> $middleware
     *
     * @return GroupInterface
     */
    public function middleware(array $middleware): GroupInterface;


}