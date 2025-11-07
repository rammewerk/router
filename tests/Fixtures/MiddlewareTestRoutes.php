<?php

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareAfterTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareBeforeTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\RequestTest;

class MiddlewareTestRoutes {

    #[Route('count')]
    public function count(RequestTest $req): int {
        return $req->middleware_count;
    }



    #[Route('hello')]
    public function hello(): string {
        return 'Hello';
    }



    #[Route('group')]
    public function group(): string {
        return 'Group';
    }



    #[Route('hasMiddleware', [MiddlewareBeforeTest::class, new MiddlewareAfterTest()])]
    public function hasMiddleware(RequestTest $req): int {
        return $req->middleware_count;
    }



    #[Route('recman/translations')]
    public function translations(RequestTest $req): array {
        return $req->middlewares;
    }


}