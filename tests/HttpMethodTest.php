<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\HttpMethodTestController;

class HttpMethodTest extends TestCase {

    private Router $router;



    public function setUp(): void {
        $this->router = new Router();
    }



    /**
     * @throws InvalidRoute
     */
    public function testDifferentMethodsWithDifferentPaths(): void {
        $this->router->entryPoint('/', HttpMethodTestController::class);

        // Test GET /users
        $response = $this->router->dispatch('/users', null, 'GET');
        $this->assertEquals('GET users', $response);

        // Test POST /create
        $response = $this->router->dispatch('/create', null, 'POST');
        $this->assertEquals('POST create', $response);

        // Test PUT /update
        $response = $this->router->dispatch('/update', null, 'PUT');
        $this->assertEquals('PUT update', $response);

        // Test DELETE /delete
        $response = $this->router->dispatch('/delete', null, 'DELETE');
        $this->assertEquals('DELETE delete', $response);
    }



    /**
     * @throws InvalidRoute
     */
    public function testSamePathWithDifferentMethods(): void {
        $this->router->entryPoint('/', HttpMethodTestController::class);

        // Test GET /api/resource
        $response = $this->router->dispatch('/api/resource', null, 'GET');
        $this->assertEquals('GET resource', $response);

        // Test POST /api/resource
        $response = $this->router->dispatch('/api/resource', null, 'POST');
        $this->assertEquals('POST resource', $response);

        // Test PUT /api/resource
        $response = $this->router->dispatch('/api/resource', null, 'PUT');
        $this->assertEquals('PUT resource', $response);

        // Test DELETE /api/resource
        $response = $this->router->dispatch('/api/resource', null, 'DELETE');
        $this->assertEquals('DELETE resource', $response);
    }



    public function testMethodNotAllowedForPath(): void {
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Method PATCH not allowed for path: users');

        $this->router->entryPoint('/', HttpMethodTestController::class);
        $this->router->dispatch('/users', null, 'PATCH');
    }



    /**
     * @throws InvalidRoute
     */
    public function testDefaultGetMethod(): void {
        $this->router->entryPoint('/', HttpMethodTestController::class);

        // Test without specifying a method (should default to GET)
        $response = $this->router->dispatch('/users');
        $this->assertEquals('GET users', $response);
    }


}