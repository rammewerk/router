<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

class RouteParameter {

    public string $name = '';
    public bool $variadic = false;
    public bool $optional = false;
    public bool $nullable = false;
    public bool $builtIn = false;
    public string $type = '';
    public mixed $defaultValue = null;
    public ?string $enum = null;



}