<?php

declare(strict_types=1);

namespace Rammewerk\Router\Extension;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;

class PsrRouter extends Router {

    /**
     * @param array<Closure():object> $middlewareQueue
     * @param Closure(ServerRequestInterface):ResponseInterface $requestHandler
     * @param null|object $serverRequest
     *
     * @return ResponseInterface The response from the handler
     */
    protected function runPipeline(array $middlewareQueue, Closure $requestHandler, object|null $serverRequest): ResponseInterface {

        if (!$serverRequest instanceof ServerRequestInterface) {
            throw new RouterConfigurationException('PSR Router requires a ServerRequestInterface');
        }

        return new class($middlewareQueue, $requestHandler) implements RequestHandlerInterface {

            /**
             * @param array<Closure():object> $middlewareQueue
             * @param Closure(ServerRequestInterface):ResponseInterface $requestHandler
             */
            public function __construct(private array $middlewareQueue, private readonly Closure $requestHandler) {}



            public function handle(ServerRequestInterface $request): ResponseInterface {

                if (empty($this->middlewareQueue)) {
                    $response = ($this->requestHandler)($request);
                    /** @phpstan-ignore instanceof.alwaysTrue */
                    return ($response instanceof ResponseInterface)
                        ? $response
                        : throw new RouterConfigurationException('Request handler must return a ResponseInterface');
                }

                $middleware = array_shift($this->middlewareQueue)();

                return ($middleware instanceof MiddlewareInterface)
                    ? $middleware->process($request, $this)
                    : throw new RouterConfigurationException('Middleware must implement MiddlewareInterface');

            }


        }->handle($serverRequest);


    }


}