<?php

declare(strict_types=1);


namespace Rammewerk\Router\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use ReflectionClass;

class RouterTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    public function testAddClosureRoute(): void {
        $routePath = "/test";
        $routeHandler = function () {
            echo "Test route";
        };

        $this->router->add($routePath, $routeHandler);

        // We can't directly check the added route due to its private property status.
        // So, we will use Reflection to check if our route was added.
        $routerReflection = new ReflectionClass($this->router);
        $routesPropertyReflection = $routerReflection->getProperty('routes');

        /** @var array<string, Closure> $routes */
        $routes = $routesPropertyReflection->getValue($this->router);

        $this->assertArrayHasKey(trim($routePath, '/'), $routes);
        #$this->assertSame($routeHandler, $routes[$routePath]);
    }



    public function testAddClosureEchoHello(): void {
        $this->router->add('/', function () {
            echo 'hello';
        });

        // Start output buffering
        ob_start();

        $this->router->dispatch('/');

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals('hello', $output);
    }



    public function testClassRouterIndex(): void {
        $this->router->add('/', RouterTestClass::class);

        // Start output buffering
        ob_start();

        $this->router->dispatch('/');

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals('Hello', $output);
    }



    public function testNoRouteAccess(): void {
        $this->expectException(InvalidRoute::class);
        $this->router->add('/', RouterTestClass::class);
        $this->router->dispatch('/wrong');
    }



    public function testAutoResolve(): void {
        $this->router->add('/', RouterTestClass::class);

        // Start output buffering
        ob_start();

        $param_check = 'testing';

        $this->router->dispatch("/check/$param_check");

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals($param_check, $output);
    }



    public function testNumberParameter(): void {
        $this->router->add('/', RouterTestClass::class);
        $number = 20;
        $value = $this->router->dispatch("/number/$number");
        $this->assertEquals($number, $value);
    }



    public function testSameReturnAsParam(): void {
        $this->router->add('/', RouterTestClass::class);
        $same = 'Hello there - it is the same';
        $value = $this->router->dispatch("/same/$same");
        $this->assertEquals($same, $value);
    }



    public function testNoAutoResolve(): void {
        $this->expectException(InvalidRoute::class);
        $router = clone $this->router;
        $router->add('/', RouterTestClass::class);
        $router->dispatch("/check/testing/too/many/parameters");
    }



    public function testFewParameters(): void {
        $this->expectException(InvalidRoute::class);
        $router = clone $this->router;
        $router->add('/check', RouterTestClass::class);
        $router->dispatch("/check/multiple/too/12/parameters");
    }


}
