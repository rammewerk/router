<?php

declare(strict_types=1);


namespace Rammewerk\Router\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\RouterTestClass;
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


}
