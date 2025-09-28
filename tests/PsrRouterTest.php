<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Extension\PsrRouter;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Tests\Fixtures\Middleware\MiddlewareAfterTest;
use Rammewerk\Router\Tests\Fixtures\PSR\AddAttributeMiddleware;
use Rammewerk\Router\Tests\Fixtures\PSR\AddCustomHeaderMiddleware;
use Rammewerk\Router\Tests\Fixtures\PSR\PsrRouterClass;
use Rammewerk\Router\Tests\Fixtures\PsrTestRoutes;

class PsrRouterTest extends TestCase {

    private PsrRouter $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new PsrRouter(fn($class) => $container->create($class));
    }



    /**
     * @throws InvalidRoute
     */
    public function testAddsAttributeToRequest(): void {
        // Create an instance of the middleware
        $middleware = new AddAttributeMiddleware('testAttribute', 'TestValue');

        $this->router->entryPoint('/test/attribute', PsrTestRoutes::class)->middleware([$middleware]);

        // Create a server request
        $request = new ServerRequest('POST', 'https://example.com/test/attribute');
        $response = $this->router->dispatch($request->getUri()->getPath(), $request);

        // Assert that the response is as expected (basic assertion)$handler
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('TestValue', $response->getBody()->getContents());
    }



    /**
     * @throws InvalidRoute
     */
    public function testAddsCustomHeader(): void {

        // Create an instance of the middleware
        $middleware = new AddCustomHeaderMiddleware('X-Test-Header', 'TestValue');

        // Create a mock RequestHandler that returns a response
        $this->router->entryPoint('/test/header', PsrTestRoutes::class)->middleware([$middleware]);

        // Create a server request
        $request = new ServerRequest('GET', 'https://example.com/test/header');

        // Process the request through the middleware
        $response = $this->router->dispatch($request->getUri()->getPath(), $request);

        // Assert that the response is an instance of ResponseInterface
        $this->assertInstanceOf(ResponseInterface::class, $response, 'Response should implement ResponseInterface');

        // Assert that the response has the custom header
        $this->assertTrue(
            $response->hasHeader('X-Test-Header'),
            'Response should have the X-Test-Header header',
        );

        $this->assertEquals(
            ['TestValue'],
            $response->getHeader('X-Test-Header'),
            'X-Test-Header should have the value TestValue',
        );

        // Optionally, assert the response body content
        $this->assertEquals(
            'Header Test',
            (string)$response->getBody(),
            'Response body should be "Header Test"',
        );

        // Optionally, assert other headers or status code
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Response status code should be 200',
        );

        $this->assertEquals(
            ['text/plain'],
            $response->getHeader('Content-Type'),
            'Content-Type header should be text/plain',
        );


    }



    public function testInvalidRoute(): void {
        $this->expectException(InvalidRoute::class);
        $this->router->entryPoint('/correct', PsrTestRoutes::class);
        $this->router->dispatch('/wrong');
    }



    /**
     * @throws InvalidRoute
     */
    public function testInvalidMiddleware(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->router->entryPoint('/correct', PsrTestRoutes::class)->middleware([MiddlewareAfterTest::class]);
        $this->router->dispatch('/correct', new ServerRequest('GET', 'https://example.com/correct'));
    }



    /**
     * @throws InvalidRoute
     */
    public function testInvalidServerRequest(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage('PSR Router requires a ServerRequestInterface');
        $this->router->entryPoint('/correct', PsrTestRoutes::class)->middleware([AddAttributeMiddleware::class]);
        $this->router->dispatch('/correct');
    }



    /**
     * @throws InvalidRoute
     */
    public function testPsrRoutingClass(): void {
        $router = clone $this->router;
        $router->entryPoint('/home', PsrRouterClass::class);
        $request = new ServerRequest('GET', 'https://example.com/home');
        $response = $router->dispatch($request->getUri()->getPath(), $request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('This is index', $response->getBody()->getContents());
    }



    /**
     * @throws InvalidRoute
     */
    public function testPsrRoutingClassWithParameters(): void {
        $router = clone $this->router;
        $router->entryPoint('/home', PsrRouterClass::class);
        $request = new ServerRequest('GET', 'https://example.com/home/string/test');
        $response = $router->dispatch($request->getUri()->getPath(), $request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('test', $response->getBody()->getContents());
    }


}