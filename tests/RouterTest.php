<?php

namespace Rammewerk\Component\Router\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Router\Error\RouteAccessDenied;
use Rammewerk\Component\Router\Error\RouteNotFound;
use Rammewerk\Component\Router\Router;
use ReflectionClass;

class RouterTest extends TestCase {

    private Router $router;

    public function setUp(): void {
        $this->router = new Router();
    }


    public function testAddClosureRoute(): void {
        $routePath = "/test";
        $routeHandler = function() {
            echo "Test route";
        };

        $this->router->add( $routePath, $routeHandler );

        // We can't directly check the added route due to its private property status.
        // So, we will use Reflection to check if our route was added.
        $routerReflection = new ReflectionClass( $this->router );
        $routesPropertyReflection = $routerReflection->getProperty( 'routes' );

        /** @var array<string, Closure> $routes */
        $routes = $routesPropertyReflection->getValue( $this->router );

        $this->assertArrayHasKey( $routePath, $routes );
        $this->assertSame( $routeHandler, $routes[$routePath] );
    }

    public function testAddClosureEchoHello(): void {
        $this->router->add( '/', function() {
            echo 'hello';
        } );

        // Start output buffering
        ob_start();

        $this->router->find( '/' );

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals( 'hello', $output );
    }

    public function testClassRouterIndex(): void {
        $this->router->add( '/', RouterTestClass::class );

        // Start output buffering
        ob_start();

        $this->router->find( '/' );

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals( 'Hello', $output );
    }


    public function testNoRouteAccess(): void {
        $this->expectException( RouteAccessDenied::class );
        $this->router->add( '/', RouterTestClass::class );
        $this->router->classAuthenticationMethod( 'hasRouteAccess' );
        $this->router->find( '/' );
    }


    public function testAutoResolve(): void {
        $this->router->add( '/', RouterTestClass::class );

        // Start output buffering
        ob_start();

        $param_check = 'testing';

        $this->router->find( "/check/$param_check/something" );

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals( $param_check, $output );
    }


    public function testNumberParameter(): void {
        $this->router->add( '/', RouterTestClass::class );
        $number = 20;
        $value = $this->router->find( "/number/$number" );
        $this->assertEquals( $number, $value );
    }


    public function testSameReturnAsParam(): void {
        $this->router->add( '/', RouterTestClass::class );
        $same = 'Hello there - it is the same';
        $value = $this->router->find( "/same/$same" );
        $this->assertEquals( $same, $value );
    }

    public function testNoAutoResolve(): void {
        $this->expectException( RouteNotFound::class );
        $router = clone $this->router;
        $router->autoResolve( false );
        $router->add( '/', RouterTestClass::class );
        $router->find( "/check/testing/too/many/parameters" );
    }

}
