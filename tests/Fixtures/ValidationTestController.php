<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Tests\Fixtures\Middleware\RequestTest;

class ValidationTestController {

    #[Route('/user/*')]
    public function requiresParam(int $id): string {
        return "User: $id";
    }



    #[Route('/post/*/*')]
    public function twoParams(int $id, string $action): string {
        return "Post: $id, Action: $action";
    }



    #[Route('/optional/*')]
    public function optionalParam(string $value = 'default'): string {
        return "Optional: $value";
    }



    #[Route('/service')]
    public function diParameter(RequestTest $service): string {
        return 'Service injected';
    }



    #[Route('/mixed/*')]
    public function mixedParams(RequestTest $service, int $id): string {
        return "Mixed: $id with service";
    }



    #[Route('/files/*/*')]
    public function variadicParams(string ...$paths): string {
        return 'Files: ' . implode('/', $paths);
    }



    #[Route('/static')]
    public function noParams(): string {
        return 'Static route';
    }



    #[Route('/missing')]
    public function missingWildcard(int $id): string {
        return "Should fail: $id";
    }



    #[Route('/insufficient/*')]
    public function insufficientWildcards(int $id, string $action): string {
        return "Should fail: $id, $action";
    }


}
