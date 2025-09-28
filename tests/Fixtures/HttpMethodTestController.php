<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;

class HttpMethodTestController {

    #[Route('/users', methods: ['GET'])]
    public function getUsers(): string {
        return 'GET users';
    }

    #[Route('/create', methods: ['POST'])]
    public function createResource(): string {
        return 'POST create';
    }

    #[Route('/update', methods: ['PUT'])]
    public function updateResource(): string {
        return 'PUT update';
    }

    #[Route('/delete', methods: ['DELETE'])]
    public function deleteResource(): string {
        return 'DELETE delete';
    }

    // Same path with different methods
    #[Route('/api/resource', methods: ['GET'])]
    public function getResource(): string {
        return 'GET resource';
    }

    #[Route('/api/resource', methods: ['POST'])]
    public function postResource(): string {
        return 'POST resource';
    }

    #[Route('/api/resource', methods: ['PUT'])]
    public function putResource(): string {
        return 'PUT resource';
    }

    #[Route('/api/resource', methods: ['DELETE'])]
    public function deleteApiResource(): string {
        return 'DELETE resource';
    }
}