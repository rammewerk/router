<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

class RouteParameter {

    /** Parameter name */
    public string $name = '';

    /** If parameter is variadic */
    public bool $variadic = false;

    /** If parameter is optional */
    public bool $optional = false;

    /** If parameter is nullable */
    public bool $nullable = false;

    /** If parameter is built-in */
    public bool $builtIn = false;

    /** Parameter type */
    public string $type = '';

    /** Parameter default value */
    public mixed $defaultValue = null;

    /** The enum class name if parameter is an enum */
    public ?string $enum = null;

    /** @var string[] Union types */
    public array $unionTypes;


}