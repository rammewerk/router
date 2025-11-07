<?php

namespace Rammewerk\Router\Tests\Fixtures;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Rammewerk\Router\Foundation\Route;

class WildCardTestRoute {

    #[Route('/wild/*')]
    public function wild(string $path): string {
        return $path;
    }

    #[Route('/wild/*/wild')]
    public function wildWild(string $path): string {
        return $path;
    }

    #[Route('/wild/int/*/wild')]
    public function wildIntWild(int $path): int {
        return $path;
    }

    #[Route('/wild/*/wild/*/wild')]
    public function firstSecond(string $first, string $second): string {
        return $first . $second;
    }

    #[Route('/wild/*/*/*/*/*/*/*/*/*/wild')]
    public function aLotOfWildcards(int ...$args): int {
        return array_sum($args);
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