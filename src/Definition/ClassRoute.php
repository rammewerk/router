<?php

declare(strict_types=1);

namespace Rammewerk\Router\Definition;

use Rammewerk\Router\Error\RouterConfigurationException;

final class ClassRoute extends RouteDefinition {

    /** @var string|null The class method to call */
    public private(set) ?string $classMethod = null;



    /**
     * @param string $pattern
     * @param class-string $handler
     */
    public function __construct(
        public readonly string $pattern,
        public readonly string $handler,
    ) {}



    /** @inheritDoc */
    public function classMethod(string $method): RouteInterface {
        $this->classMethod = $method;
        return $this;
    }



    public function disableReflection(): RouteInterface {
        throw new RouterConfigurationException('Disabling reflection is only supported for callables');
    }


}