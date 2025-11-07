<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\ValidationTestController;

class RouteParameterValidationTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    /**
     * Test that missing wildcard throws error
     */
    public function testMissingWildcardThrowsError(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("has 0 wildcard(s) but handler 'missingWildcard()' expects 1 route parameter(s)");

        $this->router->entryPoint('/missing', ValidationTestController::class);
        $this->router->dispatch('/missing/123'); // Triggers factory creation
    }



    /**
     * Test that correct wildcard count works
     */
    public function testCorrectWildcardCount(): void {
        $this->router->entryPoint('/user/*', ValidationTestController::class);
        $result = $this->router->dispatch('/user/123');

        $this->assertSame('User: 123', $result);
    }



    /**
     * Test that named parameter works
     */
    public function testNamedParameterCounts(): void {
        $this->router->entryPoint('/user/{id}', ValidationTestController::class);
        $result = $this->router->dispatch('/user/456');

        $this->assertSame('User: 456', $result);
    }



    /**
     * Test multiple wildcards
     */
    public function testMultipleWildcards(): void {
        $this->router->entryPoint('/post/*/*', ValidationTestController::class);
        $result = $this->router->dispatch('/post/123/edit');

        $this->assertSame('Post: 123, Action: edit', $result);
    }



    /**
     * Test that optional parameter works with wildcard
     */
    public function testOptionalParameterAllowed(): void {
        $this->router->entryPoint('/optional/*', ValidationTestController::class);
        $result = $this->router->dispatch('/optional/test');

        $this->assertSame('Optional: test', $result);
    }



    /**
     * Test that extra wildcards are allowed (for optional params)
     */
    public function testExtraWildcardsAllowed(): void {
        $this->router->entryPoint('/optional/*', ValidationTestController::class);
        $result = $this->router->dispatch('/optional/value');

        $this->assertSame('Optional: value', $result);
    }



    /**
     * Test that DI parameters don't require wildcards
     */
    public function testDIParameterIgnored(): void {
        $this->router->entryPoint('/service', ValidationTestController::class);
        $result = $this->router->dispatch('/service');

        $this->assertSame('Service injected', $result);
    }



    /**
     * Test mixed DI and route parameters
     */
    public function testMixedDIAndRouteParams(): void {
        $this->router->entryPoint('/mixed/*', ValidationTestController::class);
        $result = $this->router->dispatch('/mixed/789');

        $this->assertSame('Mixed: 789 with service', $result);
    }



    /**
     * Test variadic parameter with wildcards
     */
    public function testVariadicParameter(): void {
        $this->router->entryPoint('/files/*/*', ValidationTestController::class);
        $result = $this->router->dispatch('/files/doc/readme.md');

        $this->assertSame('Files: doc/readme.md', $result);
    }



    /**
     * Test missing wildcard for multiple params
     */
    public function testMissingMultipleWildcards(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage("has 1 wildcard(s) but handler 'insufficientWildcards()' expects 2 route parameter(s)");

        $this->router->entryPoint('/insufficient/*', ValidationTestController::class);
        $this->router->dispatch('/insufficient/123/edit'); // Triggers factory creation
    }



    /**
     * Test that no wildcards with no params works
     */
    public function testNoWildcardsNoParams(): void {
        $this->router->entryPoint('/static', ValidationTestController::class);
        $result = $this->router->dispatch('/static');

        $this->assertSame('Static route', $result);
    }


}
