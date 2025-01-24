<?php

namespace Rammewerk\Router\Tests\Fixtures\PSR;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PsrRouterClass {

    public function index(): ResponseInterface {
        return new Response(200, [], 'This is index');
    }



    public function string(ServerRequestInterface $request, string $string): ResponseInterface {
        return new Response(200, [], $string);
    }


}