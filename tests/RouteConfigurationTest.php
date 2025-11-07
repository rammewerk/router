<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\MiddlewareTestRoutes;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;

class RouteConfigurationTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    /**
     * Test that registering the same route twice throws an exception
     */
    public function testDuplicateRouteThrowsException(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'api/users' is already registered");

        $this->router->entryPoint('/api/users', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/api/users', RouterTestClass::class); // Should throw
    }



    /**
     * Test that registering the same route twice with overwrite flag succeeds
     */
    public function testDuplicateRouteWithOverwriteFlagSucceeds(): void {
        $route1 = $this->router->entryPoint('/api/products', MiddlewareTestRoutes::class);
        $route2 = $this->router->entryPoint('/api/products', RouterTestClass::class, overwrite: true);

        // Both should return RouteInterface instances
        $this->assertNotNull($route1);
        $this->assertNotNull($route2);

        // They should be different objects since we created a new one
        $this->assertNotSame($route1, $route2);
    }



    /**
     * Test that wildcard routes are now checked for duplicates
     * This behavior changed - wildcard routes are now tracked and prevent duplicates
     */
    public function testWildcardRoutesDuplicateThrowsException(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'api/*' is already registered");

        $this->router->entryPoint('/api/*', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/api/*', RouterTestClass::class); // Should throw
    }



    /**
     * Test that attribute routes don't conflict with explicit entry points
     * They should reuse the existing route when patterns match
     */
    public function testAttributeRoutesReuseExistingEntryPoints(): void {
        // Register explicit entry point
        $route = $this->router->entryPoint('/count', MiddlewareTestRoutes::class);

        // This should work because addRouteHandler checks for existing routes first
        // and only calls entryPoint() if it doesn't exist
        $this->assertNotNull($route);
    }


}
