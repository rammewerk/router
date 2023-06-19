<?php

namespace Rammewerk\Component\Router;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Rammewerk\Component\Router\Error\RouteAccessDenied;

class Router {

    /** @var Closure(ReflectionClass<RouteInterface>):RouteInterface|null */
    private ?Closure $loadRouteCallback;

    /** @var array<string, RoutePath> */
    private array $routeList = [];




    /**
     * Construct route class
     *
     * @param class-string|Closure $default_route Define default route handler
     * @param Closure|null $closure               Optional callback for custom loading of route class
     *
     * @phpstan-param null|Closure(ReflectionClass<RouteInterface>):RouteInterface $closure
     */
    public function __construct(string|Closure $default_route, Closure $closure = null) {
        $this->add( '/', $default_route );
        $this->loadRouteCallback = $closure;
    }




    /**
     * Add route closure
     *
     * @param string $path
     * @param Closure(Router):void|class-string $handler
     * @param string|null $method
     * @param bool $authorize
     *
     * @return void
     */
    public function add(
        string         $path,
        Closure|string $handler,
        ?string        $method = null,
        bool           $authorize = true
    ): void {
        $this->map( new RoutePath( $path, $handler, $method, $authorize ) );
    }




    /**
     * Add route map
     *
     * @param RoutePath $route
     */
    private function map(RoutePath $route): void {
        if( $route->getPath() !== '/' && isset( $this->routeList[$route->getPath()] ) ) {
            throw new LogicException( "Duplicate route ({$route->getPath()}) is not allowed" );
        }
        $this->routeList[$route->getPath()] = $route;
    }




    /**
     * Resolve and get path
     *
     * @param string $path
     * @param string|null $noAccessRelocatePath
     *
     * @return Router
     * @throws Error\RouteAccessDenied
     */
    public function find(string $path, string $noAccessRelocatePath = null): self {
        try {
            $this->resolvePath( array_filter( explode( '/', $path ), static fn($s) => $s !== '' ) );
            return $this;
        } catch ( ReflectionException $e ) {
            throw new LogicException( "Unable to load route: {$e->getMessage()}", $e->getCode(), $e );
        } catch ( RouteAccessDenied $e ) {
            if( $noAccessRelocatePath !== null ) {
                return $this->find( $noAccessRelocatePath );
            }
            throw $e;
        }
    }




    /**
     * Resolve route based on requested path
     *
     * @param string[] $paths
     * @param string[] $method
     *
     * @throws Error\RouteAccessDenied
     * @throws ReflectionException
     */
    private function resolvePath(array $paths, array $method = []): void {

        if( ! $paths ) {
            $this->loadRoute( $this->routeList['/'], $method );
            return;
        }

        # Find route based on given path
        if( $route = $this->routeList['/' . strtolower( implode( '/', $paths ) )] ?? null ) {
            $this->loadRoute( $route, $method );
            return;
        }

        # Move end of path to the beginning of method array
        array_unshift( $method, array_pop( $paths ) );

        # Check reduced path for match
        $this->resolvePath( $paths, $method );

    }




    /**
     * @param RoutePath $routePath
     * @param string[] $method
     *
     * @throws Error\RouteAccessDenied
     * @throws ReflectionException
     */
    private function loadRoute(RoutePath $routePath, array $method): void {

        # Just call the closure if defined
        if( $routePath->getClosure() ) {
            ( $routePath->getClosure() )( ...$method );
            return;
        }

        # Reflection class
        $reflectionClass = $this->getReflectionClass( $routePath->getClass() );

        # Load Route
        $route = $this->newRouteInstance( $reflectionClass );

        # Validate route access
        $this->authorize( $route, $routePath->authorize() );

        # Call route method if defined
        if( $routePath->getMethod() ) {
            if( $this->callRouteMethod( $reflectionClass, $route, $routePath->getMethod(), $method ) ) return;
            throw new LogicException( 'RoutePath has a required method, but the route class method is not available.' );
        }

        # Resolve Method and call
        $this->resolveMethod( $reflectionClass, $route, $method );

    }




    /**
     * @param class-string $class
     *
     * @return ReflectionClass<RouteInterface>
     * @throws ReflectionException
     */
    private function getReflectionClass(string $class): ReflectionClass {
        $reflectionClass = new ReflectionClass( $class );
        if( ! $reflectionClass->isSubclassOf( RouteInterface::class ) ) {
            throw new LogicException( "{$reflectionClass->getName()} is not an instance of " . Route::class );
        }
        return $reflectionClass;
    }




    /**
     * @param ReflectionClass<RouteInterface> $reflectionClass
     *
     * @return RouteInterface
     * @throws ReflectionException
     */
    private function newRouteInstance(ReflectionClass $reflectionClass): RouteInterface {
        if( $this->loadRouteCallback ) return ( $this->loadRouteCallback )( $reflectionClass );

        if( is_null( $reflectionClass->getConstructor() ) ) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        # Routes with constructors must be created through the closure given when initializing the router.
        throw new LogicException( 'A route with constructor can only be loaded through a closure' );

    }




    /**
     * @param RouteInterface $route
     * @param bool $authorize
     *
     * @return void
     * @throws RouteAccessDenied
     */
    private function authorize(RouteInterface $route, bool $authorize): void {
        if( ! $authorize || $route->hasRouteAccess() ) return;
        throw new RouteAccessDenied( 'Request does not have access to this route' );
    }




    /**
     * Resolve and call route method
     *
     * Will default to index if unable to resolve path to public method.
     *
     * @param ReflectionClass<RouteInterface> $reflection
     * @param RouteInterface $route
     * @param string[] $method
     * @param string[] $params
     *
     * @throws ReflectionException
     */
    private function resolveMethod(
        ReflectionClass $reflection,
        RouteInterface  $route,
        array           $method,
        array           $params = []
    ): void {

        # Call route index if path is empty
        if( empty( $method ) ) {
            $this->callRouteMethod( $reflection, $route, 'index', $params );
            return;
        }

        # Run method if exists
        if( $this->callRouteMethod( $reflection, $route, implode( '_', $method ), $params ) ) return;

        # Shift end of route to end of parameters
        array_unshift( $params, array_pop( $method ) );

        # Try next route
        $this->resolveMethod( $reflection, $route, $method, $params );

    }




    /**
     * Validate and call route method
     *
     * @param ReflectionClass<RouteInterface> $reflection
     * @param RouteInterface $route
     * @param string $method
     * @param string[] $params
     *
     * @return bool method has been called
     * @throws ReflectionException
     */
    private function callRouteMethod(
        ReflectionClass $reflection,
        RouteInterface  $route,
        string          $method,
        array           $params
    ): bool {

        if( $method === 'hasRouteAccess' ) return false;

        if( ! $reflection->hasMethod( $method ) ) return false;

        # Route method must be public
        if( ! $reflection->getMethod( $method )->isPublic() ) return false;

        # If method has required arguments, we must make sure we have enough parameters to fill them.
        if( $reflection->getMethod( $method )->getNumberOfRequiredParameters() > count( $params ) ) return false;

        # Call route method, pas the parameters
        $reflection->getMethod( $method )->invokeArgs( $route, $params );

        return true;

    }

}