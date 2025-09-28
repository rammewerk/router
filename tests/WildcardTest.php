<?php

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\WildCardTestRoute;

class WildcardTest extends TestCase {

    private Router $router;



    protected function setUp(): void {
        // Create a router with a basic dependency handler
        $this->router = new Router(fn(string $class) => new $class());
    }



    /**
     * @throws InvalidRoute
     */
    public function testSingleWildcardAtEnd(): void {
        $this->router->entryPoint('/wild/*', WildCardTestRoute::class);
        $response = $this->router->dispatch('/wild/test');
        $this->assertSame('test', $response);
    }



    /**
     * @throws InvalidRoute
     */
    public function testSingleWildcardsInMiddle(): void {
        $this->router->entryPoint('/wild/*/wild', WildCardTestRoute::class);
        $response = $this->router->dispatch('/wild/something/wild');
        $this->assertSame('something', $response);
    }



    public function testMissingWildcardsInMiddle(): void {
        $this->router->entryPoint('/wild/*/wild', WildCardTestRoute::class);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('No route found for path');
        $this->router->dispatch('/wild/something');
    }



    public function testInvalidWildcardsInMiddle(): void {
        $this->router->entryPoint('/wild', WildCardTestRoute::class);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Missing required parameter');
        $this->router->dispatch('/wild/int/test/wild');
    }



    /**
     * @throws InvalidRoute
     */
    public function testMultipleWildcardsInMiddle(): void {
        $this->router->entryPoint('/wild/', WildCardTestRoute::class);
        $response = $this->router->dispatch('/wild/something/wild/-else/wild');
        $this->assertSame('something-else', $response);
    }



    /**
     * @throws InvalidRoute
     */
    public function testALotOfWildcards(): void {
        $this->router->entryPoint('/wild', WildCardTestRoute::class);
        $response = $this->router->dispatch('/wild/1/2/3/4/5/6/7/8/9/wild');
        $this->assertSame(45, $response);
    }


}