<?php

namespace Rammewerk\Component\Router;

use Closure;

final readonly class RoutePath {

    /** @var class-string|null */
    private ?string $class;
    private string $path;
    private ?string $method;
    private bool $authorize;
    private ?Closure $closure;




    /**
     * Route Path constructor
     *
     * Should not be used outside the Router class.
     *
     * @param string $path                               The path to match
     * @param class-string|Closure(Router):void $handler The handler to call, either a class or a closure
     * @param string|null $method                        The method to match if the handler is a class
     * @param bool $authorize                            Whether the route class (or route class method) requires authorization
     */
    public function __construct(
        string         $path,
        string|Closure $handler,
        ?string        $method = null,
        bool           $authorize = true
    ) {
        # Path to match
        $this->path = '/' . strtolower( trim( $path, '/' ) );
        $this->closure = $handler instanceof Closure ? $handler : null;
        $this->class = is_string( $handler ) ? $handler : null;
        $this->method = $method;
        $this->authorize = $authorize;
    }




    /**
     * @return class-string|null
     */
    public function getClass(): ?string {
        return $this->class;
    }




    /**
     * @return Closure|null
     */
    public function getClosure(): ?Closure {
        return $this->closure;
    }




    /**
     * @return string|null
     */
    public function getMethod(): ?string {
        return $this->method;
    }




    /**
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }




    /**
     * @return bool
     */
    public function authorize(): bool {
        return $this->authorize;
    }


}