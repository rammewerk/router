<?php

namespace Rammewerk\Component\Router\Tests;

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

    public function same( string $same): string {
        return $same;
    }

}