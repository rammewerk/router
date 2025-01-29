<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\PSR;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddAttributeMiddleware implements MiddlewareInterface {

    /**
     * @var string The name of the attribute to add.
     */
    private string $attributeName;

    /**
     * @var mixed The value of the attribute to add.
     */
    private $attributeValue;



    /**
     * Constructor.
     *
     * @param string $attributeName The name of the attribute to add.
     * @param mixed $attributeValue The value of the attribute to add.
     */
    public function __construct(string $attributeName = 'customAttribute', $attributeValue = 'AttributeValue') {
        $this->attributeName = $attributeName;
        $this->attributeValue = $attributeValue;
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
        // Add the attribute to the request
        $modifiedRequest = $request->withAttribute($this->attributeName, $this->attributeValue);

        // Delegate to the next middleware/handler with the modified request
        return $handler->handle($modifiedRequest);
    }


}