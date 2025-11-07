<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\MiddlewareTestRoutes;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;

class RoutePatternTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    /**
     * Test that named parameters are normalized to wildcards
     */
    public function testNamedParametersNormalizeToWildcards(): void {
        // Register with named parameter
        $this->router->entryPoint('/user/{id}', MiddlewareTestRoutes::class);

        // Trying to register same pattern with wildcard should throw (they're the same)
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        $this->router->entryPoint('/user/*', RouterTestClass::class);
    }



    /**
     * Test that different named parameters are treated as duplicates
     */
    public function testDifferentNamedParametersAreDuplicates(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        $this->router->entryPoint('/user/{id}', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/{userId}', RouterTestClass::class);
    }



    /**
     * Test that static and wildcard routes can coexist
     */
    public function testStaticAndWildcardCoexist(): void {
        // These are different routes and should both succeed
        $route1 = $this->router->entryPoint('/user', MiddlewareTestRoutes::class);
        $route2 = $this->router->entryPoint('/user/*', RouterTestClass::class);

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
    }



    /**
     * Test that static segment and wildcard routes can coexist
     */
    public function testStaticSegmentAndWildcardCoexist(): void {
        // /user/id is a static route, /user/* is a wildcard route
        $route1 = $this->router->entryPoint('/user/id', MiddlewareTestRoutes::class);
        $route2 = $this->router->entryPoint('/user/*', RouterTestClass::class);

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
    }



    /**
     * Test that multiple wildcard patterns with different structures coexist
     */
    public function testMultipleWildcardPatternsCoexist(): void {
        // These are different wildcard patterns
        $route1 = $this->router->entryPoint('/test/*/*', MiddlewareTestRoutes::class);
        $route2 = $this->router->entryPoint('/test/*/id', RouterTestClass::class);

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
    }



    /**
     * Test that duplicate wildcard patterns throw error
     */
    public function testDuplicateWildcardPatternsThrowError(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        $this->router->entryPoint('/user/*', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/*', RouterTestClass::class);
    }



    /**
     * Test that duplicate named patterns throw error
     */
    public function testDuplicateNamedPatternsThrowError(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        $this->router->entryPoint('/user/{id}', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/{userId}', RouterTestClass::class);
    }



    /**
     * Test that mixed wildcard and named parameters are duplicates
     */
    public function testMixedWildcardAndNamedAreDuplicates(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        $this->router->entryPoint('/user/*', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/{id}', RouterTestClass::class);
    }



    /**
     * Test that overwrite flag allows wildcard duplicates
     */
    public function testOverwriteFlagAllowsWildcardDuplicates(): void {
        $route1 = $this->router->entryPoint('/user/{id}', MiddlewareTestRoutes::class);
        $route2 = $this->router->entryPoint('/user/*', RouterTestClass::class, overwrite: true);

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
        // They should be different objects since we created a new one
        $this->assertNotSame($route1, $route2);
    }



    /**
     * Test complex named parameters normalize correctly
     */
    public function testComplexNamedParametersNormalize(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*' is already registered");

        // All these should normalize to the same pattern
        $this->router->entryPoint('/user/{client.id}', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/{.}', RouterTestClass::class);
    }



    /**
     * Test multiple named parameters in one route
     */
    public function testMultipleNamedParameters(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*/*' is already registered");

        $this->router->entryPoint('/user/{id}/{action}', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/*/*', RouterTestClass::class);
    }



    /**
     * Test mixed named and wildcard parameters in one route
     */
    public function testMixedNamedAndWildcardParameters(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("Route with pattern 'user/*/*' is already registered");

        $this->router->entryPoint('/user/{id}/*', MiddlewareTestRoutes::class);
        $this->router->entryPoint('/user/*/{action}', RouterTestClass::class);
    }


}
