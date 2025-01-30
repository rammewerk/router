<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Foundation\RouteUtility;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\ParameterTestRoute;

class BenchmarkTest extends Benchmark {

    protected int $iterations = 1000000;



    public function case(): void {

        $route = new RouteDefinition('/', ParameterTestRoute::class)->classMethod('stringTest');
        $instance = new ParameterTestRoute();

        $this->benchmark('call_user_func_array', function () use ($instance, $route) {
            $result = call_user_func([$instance, $route->classMethod], 'hello');
            if (!$result === 'hello') {
                throw new \Exception('Invalid result');
            }
        });

        $this->benchmark('call', function () use ($instance, $route) {
            $result = $instance->{$route->classMethod}(...['hello']);
            if (!$result === 'hello') {
                throw new \Exception('Invalid result');
            }
        });

    }


}