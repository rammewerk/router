<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\PSR;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddCustomHeaderMiddleware implements MiddlewareInterface {

    /**
     * @var string The name of the custom header.
     */
    private string $headerName;

    /**
     * @var string The value of the custom header.
     */
    private string $headerValue;



    /**
     * Constructor.
     *
     * @param string $headerName  The name of the custom header.
     * @param string $headerValue The value of the custom header.
     */
    public function __construct(string $headerName = 'X-Custom-Header', string $headerValue = 'MyValue') {
        $this->headerName = $headerName;
        $this->headerValue = $headerValue;
    }



    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        // Delegate to the next middleware/handler
        $response = $handler->handle($request);

        // Add the custom header to the response
        return $response->withHeader($this->headerName, $this->headerValue);
    }


}