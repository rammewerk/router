<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;

class DependencyTestController {

    #[Route('service')]
    #[Route('test')]
    public function index(TestService $service): string {
        return $service->getIdentifier();
    }


}