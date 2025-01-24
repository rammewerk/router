<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

interface GroupInterface {

    /**
     * @param array<class-string|object> $middleware
     *
     * @return GroupInterface
     */
    public function middleware(array $middleware): GroupInterface;


}