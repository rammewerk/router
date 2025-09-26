<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

class DependencyTestController {

    public function getServiceId(TestService $service): string {
        return $service->getIdentifier();
    }

    public function index(): string {
        return 'controller-index';
    }
}