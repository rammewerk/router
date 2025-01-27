<?php

namespace Rammewerk\Router\Tests;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Adapters\PsrRouter;
use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Tests\Fixtures\PSR\AddAttributeMiddleware;
use Rammewerk\Router\Tests\Fixtures\PSR\AddCustomHeaderMiddleware;
use Rammewerk\Router\Tests\Fixtures\PSR\PsrRouterClass;

class PsrRouterTest extends TestCase {

    private PsrRouter $router;



    public function setUp(): void {
        $container = new Container();
        $this->router = new PsrRouter(fn($class) => $container->create($class));
    }



    public function testAddsAttributeToRequest(): void {
        // Create an instance of the middleware
        $middleware = new AddAttributeMiddleware('testAttribute', 'TestValue');

        $this->router->add('/test/attribute', function (ServerRequest $request): ResponseInterface {
            // Retrieve the 'testAttribute' from the request
            $attribute = $request->getAttribute('testAttribute', '');
            // Create a stream with the attribute content
            $stream = Stream::create($attribute);
            // Create a new response with the stream as the body
            return new Response()->withBody($stream);
        })->middleware([$middleware]);

        // Create a server request
        $request = new ServerRequest('POST', 'https://example.com/test/attribute');
        $response = $this->router->dispatch($request->getUri()->getPath(), $request);

        // Assert that the response is as expected (basic assertion)$handler
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('TestValue', $response->getBody()->getContents());
    }



    public function testAddsCustomHeader(): void {

        // Create an instance of the middleware
        $middleware = new AddCustomHeaderMiddleware('X-Test-Header', 'TestValue');

        // Create a mock RequestHandler that returns a response
        $this->router->add('/test/header', function (): ResponseInterface {
            return new Response()
                ->withBody(Stream::create('Header Test'))
                ->withHeader('Content-Type', 'text/plain');
        })->middleware([$middleware]);

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
        $this->router->add('/correct', function (): ResponseInterface {
            return new Response();
        });
        $this->router->dispatch('/wrong');
    }



    public function testInvalidMiddleware(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->router->add('/correct', function (): ResponseInterface {
            return new Response();
        })->middleware([RouteDefinition::class]);
        $this->router->dispatch('/correct', new ServerRequest('GET', 'https://example.com/correct'));
    }



    public function testInvalidServerRequest(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage('PSR Router requires a ServerRequestInterface');
        $this->router->add('/correct', function (): ResponseInterface {
            return new Response();
        })->middleware([AddAttributeMiddleware::class]);
        $this->router->dispatch('/correct', null);
    }



    public function testPsrRoutingClass(): void {
        $router = clone $this->router;
        $router->add('/home', PsrRouterClass::class);
        $request = new ServerRequest('GET', 'https://example.com/home');
        $response = $router->dispatch($request->getUri()->getPath(), $request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('This is index', $response->getBody()->getContents());
    }


    public function testPsrRoutingClassWithParameters(): void {
        $router = clone $this->router;
        $router->add('/home', PsrRouterClass::class);
        $request = new ServerRequest('GET', 'https://example.com/home/string/test');
        $response = $router->dispatch($request->getUri()->getPath(), $request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('test', $response->getBody()->getContents());
    }


}