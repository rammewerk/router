<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\Middleware;

use Closure;

class MiddlewareBeforeTest {

    public function handle(RequestTest $request, Closure $next): mixed {
        $request->middleware_count++;
        $request->middlewares[] = static::class;
        return $next($request);
    }


}