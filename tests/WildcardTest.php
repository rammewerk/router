<?php

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;

class WildcardTest extends TestCase {

    private Router $router;



    protected function setUp(): void {
        // Create a router with a basic dependency handler
        $this->router = new Router(fn(string $class) => new $class());
    }



    public function testSingleWildcardAtEnd(): void {
        $this->router->add('/wild/*', static fn(string $path): string => $path);
        $response = $this->router->dispatch('/wild/test');
        $this->assertSame('test', $response);
    }



    public function testSingleWildcardAtStart(): void {
        # THIS SHOULD BE AVOIDED!
        $this->router->add('/*/wild', static fn(string $path): string => $path);
        $response = $this->router->dispatch('/test/wild');
        $this->assertSame('test', $response);
    }



    public function testSingleWildcardsInMiddle(): void {
        $this->router->add('/wild/*/wild', static fn(string $path): string => $path);
        $response = $this->router->dispatch('/wild/something/wild');
        $this->assertSame('something', $response);
    }



    public function testMissingWildcardsInMiddle(): void {
        $this->router->add('/wild/*/wild', static fn(string $path): string => $path);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('No route found for path');
        $this->router->dispatch('/wild/something');
    }



    public function testInvalidWildcardsInMiddle(): void {
        $this->router->add('/wild/*/wild', static fn(int $path): string => $path);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Missing required parameter');
        $this->router->dispatch('/wild/test/wild');
    }



    public function testMultipleWildcardsInMiddle(): void {
        $this->router->add('/wild/*/wild/*/wild', static fn(string $first, string $second): string => $first . $second);
        $response = $this->router->dispatch('/wild/something/wild/-else/wild');
        $this->assertSame('something-else', $response);
    }



    public function testALotOfWildcards(): void {
        $this->router->add('/wild/*/*/*/*/*/*/*/*/*/wild', static fn(int ...$args): int => array_sum($args));
        $response = $this->router->dispatch('/wild/1/2/3/4/5/6/7/8/9/wild');
        $this->assertSame(45, $response);
    }


}