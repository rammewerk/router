<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Foundation\RouteUtility;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\ParameterTestRoute;

class ClassRoute {}


class BenchmarkTest extends Benchmark {

    protected int $iterations = 1;



    public function case(): void {

        $testData = __DIR__ . '/TestData/test_suite_7_results.json';
        $results = json_decode(file_get_contents($testData), true);


        $this->benchmark('dispatch', function () use ($results) {
            $router = new Router();
            foreach ($results as $path) {
                $router->add($path, static fn() => $path)->disableReflection();
            }
            for ($i = 0; $i < 5; $i++) {
                foreach ($results as $path) {
                    $res = $router->dispatch($path);
                    if ($res !== $path) {
                        throw new \Exception('Failed');
                    }
                }
            }
        });


    }


}