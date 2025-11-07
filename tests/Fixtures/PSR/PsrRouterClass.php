<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\PSR;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rammewerk\Router\Foundation\Route;

class PsrRouterClass {

    #[Route('home/')]
    public function index(): ResponseInterface {
        return new Response(200, [], 'This is index');
    }



    /** @noinspection PhpUnusedParameterInspection */
    #[Route('home/string/*')]
    public function string(ServerRequestInterface $request, string $string): ResponseInterface {
        return new Response(200, [], $string);
    }


}