<?php

namespace Rammewerk\Router\Tests\Fixtures\Middleware;

class MiddlewareBeforeTest {

    public function handle(RequestTest $request, \Closure $next): mixed {
        $request->middleware_count++;
        $request->middlewares[] = static::class;
        return $next($request);
    }


}