<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareAfterTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareBeforeTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\RequestTest;
use Rammewerk\Router\Tests\Fixtures\MiddlewareTestRoutes;

class MiddlewareTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    /**
     * @throws InvalidRoute
     */
    public function testMiddlewareOrderAndModification(): void {

        $request = new RequestTest();

        $this->router->entryPoint('/count', MiddlewareTestRoutes::class)->middleware([
            MiddlewareBeforeTest::class,
            new MiddlewareAfterTest(),
        ]);

        $count = $this->router->dispatch('/count', $request);

        // Assertions for the response
        $this->assertSame(1, $count);
        $this->assertSame(2, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
                MiddlewareAfterTest::class,
            ],
            $request->middlewares,
        );
    }



    /**
     * @throws InvalidRoute
     */
    public function testMiddlewareOnAttribute(): void {

        $request = new RequestTest();

        $this->router->entryPoint('/', MiddlewareTestRoutes::class);
        $count = $this->router->dispatch('/hasMiddleware', $request);

        // Assertions for the response
        $this->assertSame(1, $count);
        $this->assertSame(2, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
                MiddlewareAfterTest::class,
            ],
            $request->middlewares,
        );
    }



    /**
     * @throws InvalidRoute
     */
    public function testMiddlewareGroup(): void {

        $request = new RequestTest();

        $this->router->group(function () {
            $this->router->entryPoint('/hello', MiddlewareTestRoutes::class);
        })->middleware([
            MiddlewareBeforeTest::class,
            new MiddlewareAfterTest(),
        ]);

        $response = $this->router->dispatch('/hello', $request);

        // Assertions for the response
        $this->assertSame('Hello', $response);
        #$this->assertSame(2, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
                MiddlewareAfterTest::class,
            ],
            $request->middlewares,
        );
    }



    /**
     * @throws InvalidRoute
     */
    public function testMiddlewareGroupWithNestedMiddleware(): void {

        $request = new RequestTest();

        $this->router->group(function () {
            $this->router->entryPoint('/group', MiddlewareTestRoutes::class)->middleware([
                new MiddlewareAfterTest(),
            ]);
        })->middleware([
            MiddlewareBeforeTest::class,
        ]);

        $response = $this->router->dispatch('/group', $request);

        // Assertions for the response
        $this->assertSame('Group', $response);
        $this->assertSame(2, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
                MiddlewareAfterTest::class,
            ],
            $request->middlewares,
        );
    }



    /**
     * Test that sub-routes inherit middleware from parent entry points
     *
     * This test verifies the bug fix where attribute routes with parent prefixes
     * should inherit the middleware from their parent entry point.
     *
     * @throws InvalidRoute
     */
    public function testSubRouteInheritsParentEntryPointMiddleware(): void {

        $request = new RequestTest();

        // Register parent entry point with middleware
        $this->router->entryPoint('/recman', MiddlewareTestRoutes::class)->middleware([
            MiddlewareBeforeTest::class,
        ]);

        // Dispatch to sub-route that should inherit parent middleware
        $middlewares = $this->router->dispatch('/recman/translations', $request);

        // Assertions - the sub-route should have the parent's middleware
        $this->assertSame(1, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
            ],
            $middlewares,
        );
    }


}