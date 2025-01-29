<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;

class RouterTestClass {



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