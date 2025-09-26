<?php

declare(strict_types=1);


namespace Rammewerk\Router\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;
use Rammewerk\Router\Tests\Fixtures\DependencyTestController;
use Rammewerk\Router\Tests\Fixtures\TestService;
use ReflectionClass;

class ClassRouterTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    public function testOverride(): void {
        $this->router->add('/number', RouterTestClass::class)->classMethod('index');
        $this->router->add('/number', RouterTestClass::class)->classMethod('number');
        // Line below should not affect the routes above
        $this->router->add('/*/number', RouterTestClass::class)->classMethod('check');
        $response = $this->router->dispatch('/number/20');
        $this->assertEquals(20, $response);
    }



    public function testClassRouterIndex(): void {
        $this->router->add('/', RouterTestClass::class);

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
        $this->router->add('/', RouterTestClass::class);
        $response = $this->router->dispatch('/wrong');
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



    /**
     * Test late container binding to prevent singleton leakage in FrankenPHP worker mode
     */
    public function testLateContainerBinding(): void {
        // Create router without container initially
        $router = new Router();

        // Add route that requires dependency injection
        $router->add('/service', DependencyTestController::class)->classMethod('getServiceId');

        // First container closure with specific service instance
        $firstService = new TestService('first-request');
        $router->setContainer(function($class) use ($firstService) {
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

        // Second container closure with different service instance (simulating new request)
        $secondService = new TestService('second-request');
        $router->setContainer(function($class) use ($secondService) {
            if ($class === TestService::class) {
                return $secondService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // Second dispatch should use second container, not cached from first
        $secondResponse = $router->dispatch('/service');
        $this->assertEquals('second-request', $secondResponse);

        // Verify they're different (no singleton leakage)
        $this->assertNotEquals($firstResponse, $secondResponse);
    }



    /**
     * Test that route factories remain cached for performance
     */
    public function testRouteCachingWithContainerSwapping(): void {
        $router = new Router();
        $router->add('/test', DependencyTestController::class)->classMethod('getServiceId');

        // First container setup
        $firstService = new TestService('cached-test-1');
        $router->setContainer(function($class) use ($firstService) {
            if ($class === TestService::class) {
                return $firstService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // First dispatch - should build route factory
        $response1 = $router->dispatch('/test');
        $this->assertEquals('cached-test-1', $response1);

        // Get the route to check if factory is cached
        $route = $router->routes['test'] ?? null;
        $this->assertNotNull($route);
        $this->assertNotNull($route->factory, 'Route factory should be cached after first dispatch');

        // Second container setup
        $secondService = new TestService('cached-test-2');
        $router->setContainer(function($class) use ($secondService) {
            if ($class === TestService::class) {
                return $secondService;
            }
            if ($class === DependencyTestController::class) {
                return new DependencyTestController();
            }
            return new $class();
        });

        // Second dispatch - should reuse cached factory but with new container
        $response2 = $router->dispatch('/test');
        $this->assertEquals('cached-test-2', $response2);

        // Factory should still be cached (not regenerated)
        $this->assertNotNull($route->factory, 'Route factory should remain cached after container swap');

        // But responses should be different due to different containers
        $this->assertNotEquals($response1, $response2);
    }



}
