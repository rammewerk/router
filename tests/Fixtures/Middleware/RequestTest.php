<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\Middleware;


class RequestTest {

    public int $middleware_count = 0;

    /** @var class-string[] */
    public array $middlewares = [];


}