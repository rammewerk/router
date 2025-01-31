<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Foundation\RouteUtility;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\ParameterTestRoute;

class ClassRoute {}


class BenchmarkTest extends Benchmark {

    protected int $iterations = 1000000;



    public function case(): void {

        $route = new RouteDefinition('/', ParameterTestRoute::class)->classMethod('stringTest');
        $instance = new ParameterTestRoute();

        $handler = ParameterTestRoute::class;
        $handler_class = new ClassRoute();

        $this->benchmark('is_array', function () use ($handler) {
            if (is_array($handler)) {
                throw new \Exception('Failed');
            }
        });

        $this->benchmark('is_string', function () use ($handler) {
            if (!is_string($handler)) {
                throw new \Exception('Failed');
            }
        });

        $this->benchmark('isClass', function () use ($handler_class) {
            if ($handler_class instanceof ClassRoute) {

            } else {
                throw new \Exception('Failed');
            }
        });

    }


}