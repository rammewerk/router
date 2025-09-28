<?php

namespace Rammewerk\Router\Tests\Fixtures;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Rammewerk\Router\Foundation\Route;

class PsrTestRoutes {

    #[Route('/test/attribute')]
    public function count(ServerRequest $request): Response {
        // Retrieve the 'testAttribute' from the request
        $attribute = $request->getAttribute('testAttribute', '');
        // Create a stream with the attribute content
        $stream = Stream::create($attribute);
        // Create a new response with the stream as the body
        return new Response()->withBody($stream);
    }

    #[Route('/test/header')]
    public function header(): Response {
        return new Response()
            ->withBody(Stream::create('Header Test'))
            ->withHeader('Content-Type', 'text/plain');
    }





    #[Route('correct')]
    public function correct(): Response {
        return new Response();
    }

    #[Route('group')]
    public function group(): string {
        return 'Group';
    }




}