<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route {

    public string $path;



    /**
     * @param string $path
     * @param array<class-string|object> $middleware
     * @param array<'GET'|'POST'|'PUT'|'DELETE'> $methods Empty array means all methods are allowed.
     */
    public function __construct(string $path, public array $middleware = [], public array $methods = []) {
        // Normalize named parameters to wildcards: {anything} → *
        $this->path = RouteUtility::normalizePattern($path);
    }


}