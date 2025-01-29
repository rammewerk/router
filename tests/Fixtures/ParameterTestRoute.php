<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Tests\Fixtures\Enums\NonBackedEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestIntEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestStringEnum;

#[Route('/parameters')]
class ParameterTestRoute {

    #[Route('/string')]
    public function stringTest(string $argument): string {
        return $argument;
    }



    #[Route('/int')]
    public function intTest(int $argument): int {
        return $argument;
    }



    #[Route('/float')]
    public function floatTest(float $argument): float {
        return $argument;
    }



    #[Route('/bool')]
    public function boolTest(bool $argument): bool {
        return $argument;
    }



    #[Route('/array')]
    public function arrayTest(array $argument): array {
        return $argument;
    }



    #[Route('/object')]
    public function objectTest(object $argument): object {
        return $argument;
    }



    #[Route('/callable')]
    public function callableTest(callable $argument): callable {
        return $argument;
    }



    #[Route('/enum/string')]
    public function enumStringTest(TestStringEnum $argument): TestStringEnum {
        return $argument;
    }



    #[Route('/enum/int')]
    public function enumIntTest(TestIntEnum $argument): TestIntEnum {
        return $argument;
    }

    #[Route('/enum/optional')]
    public function enumIntOptional(TestIntEnum $argument = TestIntEnum::A): TestIntEnum {
        return $argument;
    }

    #[Route('/enum/status')]
    public function enumStatus(NonBackedEnum $argument): NonBackedEnum {
        return $argument;
    }



    #[Route('/nullable')]
    public function nullableTest(?string $argument): ?string {
        return $argument;
    }



    #[Route('/optional')]
    public function optionalTest(?string $argument = 'Default'): ?string {
        return $argument;
    }



    #[Route('/mixed')]
    public function mixedTest(mixed $argument): mixed {
        return $argument;
    }



    #[Route('/variadic')]
    public function variadicTest(string ...$arguments): string {
        return implode('', $arguments);
    }


}