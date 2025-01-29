<?php

declare(strict_types=1);

namespace Rammewerk\Router\Adapters;

use Closure;
use Rammewerk\Router\Definition\RouteInterface;
use Rammewerk\Router\Router;

class MethodAwareRouter extends Router {

    private string $method_override = '_METHOD';



    /*
     * Shortcuts for any
     *
     * @param string $pattern
     * @param class-string|Closure $handler
     * @return RouteInterface
     */
    public function any(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler);
    }



    /*
     * Shortcuts for POST
     *
     * @param string $pattern
     * @param class-string|Closure $handler
     * @return RouteInterface
     */
    public function post(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler)->middleware([$this->generateMethodMiddleware('POST', $this->getRequestMethod())]);
    }



    /*
    * Shortcuts for GET
    *
    * @param string $pattern
    * @param class-string|Closure $handler
    * @return RouteInterface
    */
    public function get(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler)->middleware([$this->generateMethodMiddleware('GET', $this->getRequestMethod())]);
    }



    /*
     * Shortcuts for PUT
     *
     * @param string $pattern
     * @param class-string|Closure $handler
     * @return RouteInterface
     */
    public function put(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler)->middleware([$this->generateMethodMiddleware('PUT', $this->getRequestMethod())]);
    }



    /*
     * Shortcuts for PATCH
     *
     * @param string $pattern
     * @param class-string|Closure $handler
     * @return RouteInterface
     */
    public function patch(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler)->middleware([$this->generateMethodMiddleware('PATCH', $this->getRequestMethod())]);
    }



    /*
    * Shortcuts for DELETE
    *
    * @param string $pattern
    * @param class-string|Closure $handler
    * @return RouteInterface
    */
    public function delete(string $pattern, Closure|string $handler): RouteInterface {
        return $this->add($pattern, $handler)->middleware([$this->generateMethodMiddleware('DELETE', $this->getRequestMethod())]);
    }



    /**
     * Override the default method name
     *
     * @param string $method
     *
     * @return void
     */
    public function setMethodOverrideKey(string $method): void {
        $this->method_override = $method;
    }



    /**
     *
     * @return string
     */
    private function getRequestMethod(): string {

#        return strtoupper(filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'GET');

        /** @var string $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $override = $_POST[$this->method_override] ?? null;
        if ($override && is_string($override) && strtoupper($method) === 'POST') {
            $override = strtoupper($override);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }



    private function generateMethodMiddleware(string $expectedMethod, string $method): object {
        return new readonly class($expectedMethod, $method) {

            public function __construct(private string $expectedMethod, private string $method) {}



            public function handle(object|null $request, \Closure $handler): mixed {
                if ($this->method !== $this->expectedMethod) {
                    http_response_code(405); // Method Not Allowed
                    exit("405 Method Not Allowed");
                }
                return $handler($request);
            }


        };
    }


}