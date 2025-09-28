<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Router\Router;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Tests\Fixtures\Attributes\DashboardRoute;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;

class AttributeTest extends TestCase {

    private Router $router;



    protected function setUp(): void {
        // Create a router with a basic dependency handler
        $this->router = new Router(fn(string $class) => new $class());

        // Register routes
        $this->router->entryPoint('/dashboard', DashboardRoute::class);
    }



    /**
     * @throws InvalidRoute
     */
    public function multipleRoutesSameClass(): void {

        $this->router->entryPoint('/test', DashboardRoute::class);

        $response = $this->router->dispatch('/dashboard/stats/123/details/456');
        $this->assertSame('123456', $response);

        $response = $this->router->dispatch('/test/multiple/123456');
        $this->assertSame('123456', $response);

        $response = $this->router->dispatch('/dashboard/stats/extra/92819');
        $this->assertSame('92819', $response);

        // Dispatch and check response for /dashboard/stats/123/details/extra
        $response = $this->router->dispatch('/dashboard/stats/123/details/456/Extra');
        $this->assertSame('123456Extra', $response);
    }



    /**
     * @throws InvalidRoute
     */
    public function testStatsRouteWithParameters(): void {
        // Dispatch and check response for /dashboard/stats/123/details
        $response = $this->router->dispatch('/dashboard/stats/123/details/456');
        $this->assertSame('123456', $response);

        // Dispatch and check response for /dashboard/stats/123/details/extra
        $response = $this->router->dispatch('/dashboard/stats/123/details/456/Extra');
        $this->assertSame('123456Extra', $response);
    }



    /**
     * @throws InvalidRoute
     */
    public function testProfileRoute(): void {
        // Dispatch and check response for /dashboard/profile
        $response = $this->router->dispatch('/dashboard/profile');
        $this->assertSame('Profile page', $response);
    }



    public function testInvalidRoute(): void {
        // Attempt to dispatch an invalid route
        $this->expectException(InvalidRoute::class);
        $this->router->dispatch('/dashboard/unknown');
    }

    public function testMissingClassRouteAttribute(): void {
        // Attempt to dispatch an invalid route
        $this->router->entryPoint('/test', RouterTestClass::class);
        $this->expectException(InvalidRoute::class);
        $this->router->dispatch('/test/invalid');
    }


}