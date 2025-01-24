<?php

namespace Rammewerk\Router\Tests\Fixtures\Middleware;

class MiddlewareAfterTest {

    public function handle(RequestTest $request, \Closure $next): mixed {
        $response = $next($request);
        $request->middleware_count++;
        $request->middlewares[] = static::class;
        return $response;
    }


}