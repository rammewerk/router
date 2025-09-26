<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

class TestService {

    public function __construct(
        private string $identifier
    ) {}

    public function getIdentifier(): string {
        return $this->identifier;
    }
}