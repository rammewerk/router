<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route {

    public function __construct(public string $path = '') {}


}