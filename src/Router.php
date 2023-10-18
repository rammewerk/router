<?php

namespace Rammewerk\Component\Router;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ArgumentCountError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

use Rammewerk\Component\Router\Error\RouteAccessDenied;

class Router {

    private ?Closure $classLoader = null;
    private ?Closure $methodLoader = null;

    /** @var array<string, Closure|class-string>
     */
    private array $routes = [];

    private ?string $authentication_method = null;


    /**
     * Register a method dependency loader
     *
     * This function accepts a closure for loading class method dependencies. This
     * closure is used to generate instances of objects when needed. The closure should
     * take a class-string and should return an instance of the same class.
     *
     * @template T of object
     * @param Closure(class-string<T>):T $closure
     */
    public function registerDependencyLoader(Closure $closure): void {
        $this->methodLoader = $closure;
    }


    /**
     * Registers a class dependency loader.
     *
     * This function is just an optional way to load dependencies for the router class. The closure takes a
     * ReflectionClass instance encapsulating the target class and returns an instance of it.
     *
     * @template T of object
     * @param Closure(ReflectionClass<T>):T $closure
     */
    public function registerClassDependencyLoader(Closure $closure): void {
        $this->classLoader = $closure;
    }


    /**
     * Register class authentication method
     * @param string $method
     * @return void
     */
    public function classAuthenticationMethod(string $method): void {
        $this->authentication_method = $method;
    }


    /**
     * Adds a new route to the application.
     *
     * @param string $path
     * @param Closure():void|class-string $handler The route handler to manage requests to this path.
     */
    public function add(string $path, Closure|string $handler): void {

        $path = '/' . strtolower( trim( $path, '/' ) );

        if( $path !== '/' && isset( $this->routes[$path] ) ) {
            throw new LogicException( "Duplicate route is not allowed. The path \"$path\" has already been registered." );
        }

        $this->routes[$path] = $handler;

    }


    /**
     * Finds and handles a route based on a provided path.
     *
     * The method locates a route using the input path and initiates its handler.
     *
     * @param string|null $path The route path to locate.
     *
     * @return mixed The called route method or closure response
     * @throws RouteAccessDenied If a route class is defined and access is not given
     */
    public function find(string $path = null): mixed {

        if( is_null( $path ) ) {
            $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', \PHP_URL_PATH ) ?: '', '/' );
        }

        # Require a default route to be set
        if( !isset( $this->routes['/'] ) ) {
            throw new LogicException( 'Default route "/" is not defined. The application requires a default route to handle unmatched route requests.' );
        }

        try {
            return $this->resolvePath( array_filter( explode( '/', $path ), static fn($s) => $s !== '' ) );
        } catch( ReflectionException $e ) {
            throw new LogicException( "Unable to load route: {$e->getMessage()}", $e->getCode(), $e );
        }

    }


    /**
     * Resolve route based on requested path
     *
     * @param string[] $paths
     *
     * @return mixed The called route method response
     * @throws ReflectionException
     * @throws Error\RouteAccessDenied
     */
    private function resolvePath(array $paths): mixed {

        $context = [];

        while( $paths ) {

            $path = '/' . strtolower( implode( '/', $paths ) );

            # Find route based on given path
            if( $handler = $this->routes[$path] ?? null ) {
                try {
                    # Try to load the route with the current context
                    return $this->loadRouteHandler( $path, $handler, $context );
                } catch( ArgumentCountError ) {
                    # If ArgumentCountError is caught, continue with next iteration to treat it as an unresolved path
                }
            }

            # Move the last path element to the beginning of the context
            $lastElement = array_pop( $paths );
            assert( $lastElement !== null );
            array_unshift( $context, $lastElement );

        }

        # Load the default route handler if no match is found in the loop
        return $this->loadRouteHandler( '/', $this->routes['/'], $context );

    }


    /**
     * @param string $path
     * @param Closure|class-string $handler
     * @param string[] $context
     *
     * @return mixed The called route method response
     * @throws ReflectionException
     * @throws RouteAccessDenied
     * @throws ArgumentCountError
     */
    private function loadRouteHandler(string $path, Closure|string $handler, array $context): mixed {

        # Just call the closure if defined
        if( $handler instanceof Closure ) {
            return $this->loadClosureHandler( $handler, $context, $path );
        }

        # Reflection class
        $reflectionClass = new ReflectionClass( $handler );

        # Load Route
        $class = $this->getRouteClassInstance( $reflectionClass );

        # Validate route access
        if( $this->authentication_method ) $this->authorizeClass( $class );

        # Resolve Method and call
        return $this->resolveClassMethod( $reflectionClass, $class, $context );

    }

    /**
     * @param Closure(): mixed $closure
     * @param string[] $params
     * @param string $path
     * @return mixed
     * @throws ReflectionException
     * @throws ArgumentCountError
     */
    private function loadClosureHandler(Closure $closure, array $params, string $path): mixed {
        $reflection = new ReflectionFunction( $closure );
        $args = $this->resolveParameters( $reflection->getParameters(), $params );
        if( $reflection->getNumberOfRequiredParameters() > count( $args ) ) {
            throw new ArgumentCountError( "The route \"$path\" has mismatching parameter counts." );
        }
        return $closure( ...$args );
    }


    /**
     * @template T of object
     * @param ReflectionClass<T> $reflectionClass
     *
     * @return T
     * @throws ReflectionException
     */
    private function getRouteClassInstance(ReflectionClass $reflectionClass) {

        if( $this->classLoader ) {
            return ($this->classLoader)( $reflectionClass );
        }

        if( $this->methodLoader ) {
            return ($this->methodLoader)( $reflectionClass->getName() );
        }

        if( is_null( $reflectionClass->getConstructor() ) ) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        # Routes with constructors must be created through the closure given when initializing the router.
        throw new LogicException( 'A route with a constructor has been encountered, but no class loader is registered to handle object instantiation. Please register a class loader when initializing the router.' );

    }


    /**
     * @param object $class
     *
     * @return void
     * @throws RouteAccessDenied
     * @throws ReflectionException
     */
    private function authorizeClass(object $class): void {

        if( is_null( $this->authentication_method ) ) return;

        if( !method_exists( $class, $this->authentication_method ) ) {
            throw new RouteAccessDenied( "Route requires authorization, but the method \"hasRouteAccess\" is not defined in class: " . get_class( $class ) );
        }

        $reflection = new ReflectionMethod( $class, $this->authentication_method );

        if( !$reflection->isPublic() ) {
            throw new LogicException( "The '" . $this->authentication_method . "' method in class " . get_class( $class ) . " must be public to properly perform access control." );
        }

        $args = [];
        if( $this->methodLoader ) {
            foreach( $reflection->getParameters() as $parameter ) {
                if( $parameter->getType() instanceof ReflectionNamedType && class_exists( $parameter->getType()->getName() ) ) {
                    $args[] = ($this->methodLoader)( $parameter->getType()->getName() );
                }
            }
        }

        if( $reflection->invokeArgs( $class, $args ) ) return;
        throw new RouteAccessDenied( 'Request does not have access to this route' );
    }


    /**
     * Resolve and call route method
     *
     * Will default to index if unable to resolve path to public method.
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param object $class
     * @param string[] $context
     *
     * @return mixed
     * @throws ReflectionException
     */
    private function resolveClassMethod(ReflectionClass $reflection, object $class, array $context): mixed {

        $params = [];

        while( $context ) {

            $method = $this->getValidMethod( $reflection, implode( '_', $context ) );

            # Run method if exists
            if( $method instanceof ReflectionMethod ) {
                $args = $this->createParameters( $method, $params );
                if( $args !== null ) {
                    return $this->callAndReturnMethod( $method, $class, $args );
                }
            }

            # Move the last context element to the beginning of the parameter
            $lastElement = array_pop( $context );
            assert( $lastElement !== null );
            array_unshift( $params, $lastElement );

        }

        # Call route index if path is empty
        $index_method = $this->getValidMethod( $reflection, 'index' );
        # Run method if exists
        if( $index_method instanceof ReflectionMethod ) {
            $args = $this->createParameters( $index_method, $params );
            if( $args !== null ) {
                return $this->callAndReturnMethod( $index_method, $class, $args );
            }
        }

        throw new LogicException( 'The path matched the route class ' . $reflection->getName() . ', but no "index" method was found. Ensure the route class defines this method.' );

    }


    /**
     * Validate and call route method
     *
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param string $name
     * @return ReflectionMethod|null method has been called
     */
    private function getValidMethod(ReflectionClass $reflection, string $name): ?ReflectionMethod {

        # Never allow direct access to authorization
        if( $this->authentication_method && strtolower( $name ) === strtolower( $this->authentication_method ) ) return null;

        # Method must exist
        if( !$reflection->hasMethod( $name ) ) return null;

        $method = $reflection->getMethod( $name );

        # Method must be public
        if( $method->isPublic() ) return $method;

        return null;

    }


    /**
     * @param ReflectionMethod $method
     * @param string[] $params
     * @return object[]|string[]|null
     */
    private function createParameters(ReflectionMethod $method, array $params): ?array {
        # Resolve parameters
        $args = $this->resolveParameters( $method->getParameters(), $params );
        # If method has required arguments, we must make sure we have enough parameters to fill them.
        if( $method->getNumberOfRequiredParameters() > count( $args ) ) return null;
        return $args;
    }


    /**
     * @param ReflectionMethod $method
     * @param object $class
     * @param array<int, string|object|int> $args
     * @return mixed The called method response.
     * @throws ReflectionException
     */
    private function callAndReturnMethod(ReflectionMethod $method, object $class, array $args): mixed {
        return $method->invokeArgs( $class, $args );
    }


    /**
     * @param ReflectionParameter[] $parameters
     * @param string[] $params
     * @return array<int, string|object>
     */
    private function resolveParameters(array $parameters, array $params): array {
        $args = [];

        foreach( $parameters as $parameter ) {

            $type_name = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : null;

            if( $params && $type_name === null ) {
                $args[] = array_shift( $params );
            } elseif( $params && $type_name === 'string' ) {
                $args[] = array_shift( $params );
            } elseif( $params && $type_name === 'int' ) {
                $args[] = (int)array_shift( $params );
            } elseif( $this->methodLoader && $type_name && class_exists( $type_name ) ) {
                // Load any class dependencies via the method loader.
                $args[] = ($this->methodLoader)( $type_name );
            }
        }

        // If there are still parameters left, add them for possible variadic parameters.
        return array_merge( $args, $params );
    }

}