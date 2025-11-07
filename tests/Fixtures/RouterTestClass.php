<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;

class RouterTestClass {


    #[Route('/')]
    public function index(): string {
        return 'index';
    }


    #[Route('/no/return')]
    public function no_return(): void {
        echo 'Printed';
    }


    #[Route('/check/*')]
    public function check(string $param): void {
        echo $param;
    }


    #[Route('/number/*')]
    public function number(int $number): int {
        return $number;
    }


    #[Route('/same/*')]
    public function same(string $same): string {
        return $same;
    }


    #[Route('/multiple')]
    public function multiple(string $name, int $hello): string {
        return $name . $hello;
    }



    #[Route('/invalid')]
    public function invalidRoute(): string {
        return 'Invalid route attribute, should never be resolved';
    }


}