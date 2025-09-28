<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Rammewerk\Router\Tests;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rammewerk\Router\Error\RouterConfigurationException;
use Rammewerk\Router\Router;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Tests\Fixtures\Enums\NonBackedEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestIntEnum;
use Rammewerk\Router\Tests\Fixtures\Enums\TestStringEnum;
use Rammewerk\Router\Tests\Fixtures\ParameterTestRoute;

class ParameterTypeTest extends TestCase {

    private Router $router;



    protected function setUp(): void {
        // Create a router with a basic dependency handler
        $this->router = new Router(fn(string $class) => new $class());
        $this->router->entryPoint('/parameters', ParameterTestRoute::class);
    }



    public function testStringParameter(): void {
        $response = $this->router->dispatch('/parameters/string/test');
        $this->assertSame('test', $response);
        # Rerun
        $response = $this->router->dispatch('/parameters/string/hello');
        $this->assertSame('hello', $response);
    }



    public function testIntParameter(): void {
        $response = $this->router->dispatch('/parameters/int/123');
        $this->assertSame(123, $response);
        # Rerun
        $response = $this->router->dispatch('/parameters/int/0');
        $this->assertSame(0, $response);
    }



    public function testFloatParameter(): void {
        $response = $this->router->dispatch('/parameters/float/123.456');
        $this->assertSame(123.456, $response);
        $response = $this->router->dispatch('/parameters/float/57');
        $this->assertSame(57.00, $response);
        $response = $this->router->dispatch('/parameters/float/0.0');
        $this->assertSame(0.0, $response);
    }



    public function testBoolTrueParameter(): void {
        $response = $this->router->dispatch('/parameters/bool/true');
        $this->assertTrue($response);
        $response = $this->router->dispatch('/parameters/bool/1');
        $this->assertTrue($response);
        $response = $this->router->dispatch('/parameters/bool/on');
        $this->assertTrue($response);
        $response = $this->router->dispatch('/parameters/bool/yes');
        $this->assertTrue($response);
    }



    public function testBoolFalseParameter(): void {
        $response = $this->router->dispatch('/parameters/bool/false');
        $this->assertFalse($response);
        $response = $this->router->dispatch('/parameters/bool/0');
        $this->assertFalse($response);
        $response = $this->router->dispatch('/parameters/bool/off');
        $this->assertFalse($response);
        $response = $this->router->dispatch('/parameters/bool/no');
        $this->assertFalse($response);
    }



    public function testArrayParameter(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage('unsupported type');
        $this->router->dispatch('/parameters/array/test');
    }



    public function testObjectParameter(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage('unsupported type');
        $this->router->dispatch('/parameters/object/test');
    }



    public function testCallableParameter(): void {
        $this->expectException(RouterConfigurationException::class);
        $this->expectExceptionMessage('unsupported type');
        $this->router->dispatch('/parameters/callable/test');
    }



    public function testEnumStringParameter(): void {
        $response = $this->router->dispatch('/parameters/enum/string/B');
        $this->assertSame(TestStringEnum::B, $response);
    }



    public function testEnumIntParameter(): void {
        $response = $this->router->dispatch('/parameters/enum/int/3');
        $this->assertSame(TestIntEnum::C, $response);
    }



    public function testEnumIntOptionalParameter(): void {
        $response = $this->router->dispatch('/parameters/enum/optional');
        $this->assertSame(TestIntEnum::A, $response);
        $response = $this->router->dispatch('/parameters/enum/optional/3');
        $this->assertSame(TestIntEnum::C, $response);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Invalid enum value');
        $this->router->dispatch('/parameters/enum/optional/fail');
    }



    public function testNonBackedEnum(): void {
        $response = $this->router->dispatch('/parameters/enum/status/pending');
        $this->assertSame(NonBackedEnum::PENDING, $response);
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Invalid enum value');
        $this->router->dispatch('/parameters/enum/status/fail');
    }



    public function testNullableParameter(): void {
        $response = $this->router->dispatch('/parameters/nullable');
        $this->assertNull($response);
        $response = $this->router->dispatch('/parameters/nullable/string');
        $this->assertSame('string', $response);
    }



    public function testOptionalParameter(): void {
        $response = $this->router->dispatch('/parameters/optional');
        $this->assertSame('Default', $response);
        $response = $this->router->dispatch('/parameters/optional/string');
        $this->assertSame('string', $response);
    }


    public function testDateTimeParameter(): void {
        $response = $this->router->dispatch('/parameters/dateTime/2022-01-01');
        $this->assertInstanceOf(DateTime::class, $response);
    }


    public function testDateTimeImmutableParameter(): void {
        $response = $this->router->dispatch('/parameters/dateTimeImmutable/2022-01-01');
        $this->assertInstanceOf(DateTimeImmutable::class, $response);
    }



    public function testMixedParameter(): void {
        $response = $this->router->dispatch('/parameters/mixed/test');
        $this->assertSame('test', $response);
        $response = $this->router->dispatch('/parameters/mixed/12');
        $this->assertSame('12', $response);
    }



    public function testVariadicParameter(): void {
        $response = $this->router->dispatch('/parameters/variadic/is-/var/i/a/dic');
        $this->assertSame('is-variadic', $response);
    }



}