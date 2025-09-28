<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures;

use DateTime;
use DateTimeImmutable;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Tests\Fixtures\Enums\NonBackedEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestIntEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestStringEnum;

class ParameterTestRoute {

    #[Route('/parameters/string')]
    public function stringTest(string $argument): string {
        return $argument;
    }



    #[Route('/parameters/int')]
    public function intTest(int $argument): int {
        return $argument;
    }



    #[Route('/parameters/float')]
    public function floatTest(float $argument): float {
        return $argument;
    }



    #[Route('/parameters/bool')]
    public function boolTest(bool $argument): bool {
        return $argument;
    }



    #[Route('/parameters/array')]
    public function arrayTest(array $argument): array {
        return $argument;
    }



    #[Route('/parameters/object')]
    public function objectTest(object $argument): object {
        return $argument;
    }



    #[Route('/parameters/callable')]
    public function callableTest(callable $argument): callable {
        return $argument;
    }



    #[Route('/parameters/enum/string')]
    public function enumStringTest(TestStringEnum $argument): TestStringEnum {
        return $argument;
    }



    #[Route('/parameters/enum/int')]
    public function enumIntTest(TestIntEnum $argument): TestIntEnum {
        return $argument;
    }



    #[Route('/parameters/enum/optional')]
    public function enumIntOptional(TestIntEnum $argument = TestIntEnum::A): TestIntEnum {
        return $argument;
    }



    #[Route('/parameters/enum/status')]
    public function enumStatus(NonBackedEnum $argument): NonBackedEnum {
        return $argument;
    }



    #[Route('/parameters/nullable')]
    public function nullableTest(?string $argument): ?string {
        return $argument;
    }


    #[Route('/parameters/dateTime')]
    public function dateTimeTest(DateTime $argument): DateTime {
        return $argument;
    }



    #[Route('/parameters/dateTimeImmutable')]
    public function dateTimeImmutableTest(DateTimeImmutable $argument): DateTimeImmutable {
        return $argument;
    }



    #[Route('/parameters/optional')]
    public function optionalTest(?string $argument = 'Default'): ?string {
        return $argument;
    }



    #[Route('/parameters/mixed')]
    public function mixedTest(mixed $argument): mixed {
        return $argument;
    }



    #[Route('/parameters/variadic')]
    public function variadicTest(string ...$arguments): string {
        return implode('', $arguments);
    }


}