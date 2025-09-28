<?php

declare(strict_types=1);


namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;
use Rammewerk\Router\Tests\Fixtures\DependencyTestController;
use Rammewerk\Router\Tests\Fixtures\TestService;

class ClassRouterTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    /**
     * @throws InvalidRoute
     */
    public function testClassRouterIndex(): void {
        $this->router->entryPoint('/', RouterTestClass::class);

        // Start output buffering
        ob_start();

        $this->router->dispatch('/no/return');

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals('Printed', $output);
    }



    public function testTooManyParameters(): void {
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Too many arguments');
        $this->router->entryPoint('/', RouterTestClass::class);
        $this->router->dispatch('/wrong');
    }



    /**
     * @throws InvalidRoute
     */
    public function testAutoResolve(): void {
        $this->router->entryPoint('/', RouterTestClass::class);

        // Start output buffering
        ob_start();

        $param_check = 'testing';

        $this->router->dispatch("/check/$param_check");

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals($param_check, $output);
    }



    /**
     * @throws InvalidRoute
     */
    public function testNumberParameter(): void {
        $this->router->entryPoint('/', RouterTestClass::class);
        $number = 20;
        $value = $this->router->dispatch("/number/$number");
        $this->assertEquals($number, $value);
    }



    /**
     * @throws InvalidRoute
     */
    public function testSameReturnAsParam(): void {
        $this->router->entryPoint('/', RouterTestClass::class);
        $same = 'Hello there - it is the same';
        $value = $this->router->dispatch("/same/$same");
        $this->assertEquals($same, $value);
    }



    public function testNoAutoResolve(): void {
        $this->expectException(InvalidRoute::class);
        $router = clone $this->router;
        $router->entryPoint('/', RouterTestClass::class);
        $router->dispatch("/check/testing/too/many/parameters");
    }



    public function testFewParameters(): void {
        $this->expectException(InvalidRoute::class);
        $router = clone $this->router;
        $router->entryPoint('/check', RouterTestClass::class);
        $router->dispatch("/check/multiple/too/12/parameters");
    }



    /**
     * Test late container binding to prevent singleton leakage in FrankenPHP worker mode
     *
     * @throws InvalidRoute
     */
    public function testLateContainerBinding(): void {
        // Create a router without a container initially
        $router = new Router();

        // Add a route that requires dependency injection
        $router->entryPoint('/service', DependencyTestController::class);

        // First container closure with a specific service instance
        $router->setContainer(function ($class) {
            $firstService = new TestService('first-request');
            if ($class === TestService::class) {
                return $firstService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // First dispatch should use first container
        $firstResponse = $router->dispatch('/service');
        $this->assertEquals('first-request', $firstResponse);

        // Second container closure with a different service instance (simulating new request)
        $router->setContainer(function ($class) {
            $secondService = new TestService('second-request');
            if ($class === TestService::class) {
                return $secondService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // Second dispatch should use second container, not cached from the first
        $secondResponse = $router->dispatch('/service');
        $this->assertEquals('second-request', $secondResponse);

        // Verify they're different (no singleton leakage)
        $this->assertNotEquals($firstResponse, $secondResponse);
    }



    /**
     * Test that route factories remain cached for performance
     *
     * @throws InvalidRoute
     */
    public function testRouteCachingWithContainerSwapping(): void {
        $router = new Router();
        /** @todo convert to /test */
        $router->entryPoint('/service', DependencyTestController::class);

        // First container setup
        $router->setContainer(function ($class) {
            $firstService = new TestService('cached-test-1');
            if ($class === TestService::class) {
                return $firstService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // First dispatch - should build route factory
        $response1 = $router->dispatch('/service', requestMethod: 'GET');
        $this->assertEquals('cached-test-1', $response1);

        // Get the route to check if the factory is cached
        $route = $router->routes['service'] ?? null;
        $this->assertNotNull($route);
        $this->assertNotNull($route->getHandlerForMethod('GET')?->factory, 'Route factory should be cached after first dispatch');

        // Second container setup
        $router->setContainer(function ($class) {
            $secondService = new TestService('cached-test-2');
            if ($class === TestService::class) {
                return $secondService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // Second dispatch - should reuse cached factory but with a new container
        $response2 = $router->dispatch('/service');
        $this->assertEquals('cached-test-2', $response2);

        // Factory should still be cached (not regenerated)
        $this->assertNotNull($route->getHandlerForMethod('GET')?->factory, 'Route factory should remain cached after container swap');

        // But responses should be different due to different containers
        $this->assertNotEquals($response1, $response2);
    }



}
