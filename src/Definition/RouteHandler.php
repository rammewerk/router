<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Closure;
use ReflectionMethod;

final class RouteHandler {

    /** @var null|Closure(mixed[], object|null): mixed */
    public ?Closure $factory = null;

    /** @var array<'GET'|'POST'|'PUT'|'DELETE'> HTTP methods this handler supports. Empty array means all methods. */
    public array $methods = [];



    /**
     * @param array<'GET'|'POST'|'PUT'|'DELETE'> $methods HTTP methods this handler supports. Empty array means all methods.
     * @param array<class-string|object> $middleware      Method-specific middleware
     * @param string|null $classMethod                    The class method to call
     * @param ReflectionMethod|null $reflection           Reflection cache
     */
    public function __construct(
        array $methods = [],
        public array $middleware = [],
        public ?string $classMethod = null,
        public ?ReflectionMethod $reflection = null,
    ) {
        $this->methods = array_map(static fn(string $method) => strtoupper($method), $methods);
    }



    /**
     * Check if this handler supports the given HTTP method
     */
    public function supportsMethod(string $method): bool {
        return empty($this->methods) || in_array($method, $this->methods, true);
    }


}