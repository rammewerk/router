<?php

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Attributes\Route;

class RouterTestClass {

    public function hasRouteAccess(): bool {
        return false;
    }



    public function index(): void {
        echo 'Hello';
    }



    public function check(string $param): void {
        echo $param;
    }



    public function number(int $number): int {
        return $number;
    }



    public function same(string $same): string {
        return $same;
    }



    public function multiple(string $name, int $hello): string {
        return $name . $hello;
    }



    #[Route('/invalid')]
    public function invalidRoute(): string {
        return 'Invalid route attribute, should never be resolved';
    }


}