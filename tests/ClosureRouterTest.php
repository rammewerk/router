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

class ClosureRouterTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new Router(fn($class) => $container->create($class));
    }



    public function testWildcard(): void {
        $this->router->add('/profile/*/settings', function (int $id, string $hello) {
            return $id;
        });
        $response = $this->router->dispatch('/profile/123/settings/whatever');
        $this->assertSame(123, $response);
    }



    public function testAddClosureEchoHello(): void {
        $this->router->add('/', function () {
            echo 'hello';
        });

        // Start output buffering
        ob_start();

        $this->router->dispatch('/');

        // Get the content from the output buffer and clean it
        $output = ob_get_clean();

        // Check if 'hello' is printed
        $this->assertEquals('hello', $output);
    }



}
