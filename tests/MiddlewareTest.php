<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareAfterTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareBeforeTest;
use Rammewerk\Router\Tests\Fixtures\Middleware\RequestTest;

class MiddlewareTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    public function testMiddlewareOrderAndModification(): void {

        $request = new RequestTest();

        $this->router->add('/count', function (RequestTest $req): int {
            return $req->middleware_count;
        })->middleware([
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



    public function testMiddlewareGroup(): void {

        $request = new RequestTest();

        $this->router->group(function () {
            $this->router->add('/hello', function (RequestTest $req): string {
                return 'Hello';
            });
        })->middleware([
            MiddlewareBeforeTest::class,
            new MiddlewareAfterTest(),
        ]);

        $response = $this->router->dispatch('/hello', $request);

        // Assertions for the response
        $this->assertSame('Hello', $response);
        $this->assertSame(2, $request->middleware_count);
        $this->assertSame(
            [
                MiddlewareBeforeTest::class,
                MiddlewareAfterTest::class,
            ],
            $request->middlewares,
        );
    }



    public function testMiddlewareGroupWithNestedMiddleware(): void {

        $request = new RequestTest();

        $this->router->group(function () {
            $this->router->add('/group', function (RequestTest $req): string {
                return 'Group';
            })->middleware([
                new MiddlewareAfterTest()
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
                MiddlewareAfterTest::class
            ],
            $request->middlewares,
        );
    }


}