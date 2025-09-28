<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route {

    /**
     * @param string $path
     * @param array<class-string|object> $middleware
     * @param array<'GET'|'POST'|'PUT'|'DELETE'> $methods Empty array means all methods are allowed.
     */
    public function __construct(public string $path, public array $middleware = [], public array $methods = []) {}


}