<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Definition\RouteDefinition;
use Rammewerk\Router\Foundation\RouteUtility;
use Rammewerk\Router\Router;
use Rammewerk\Router\Tests\Fixtures\ParameterTestRoute;

class ClassRoute {}


class BenchmarkTest extends Benchmark {

    protected int $iterations = 10;



    public function case(): void {

        $testPathsData = __DIR__ . '/../TestData/test_suite_1_paths.json';
        $testPaths = json_decode(file_get_contents($testPathsData), true);

        $testResults = __DIR__ . '/../TestData/test_suite_1_results.json';
        $results = json_decode(file_get_contents($testResults), true);

        $count = 0;

        $this->benchmark('dispatch', function () use ($testPaths, $results, &$count) {
            $router = new Router();
            foreach ($testPaths as $path) {
                $path = str_replace('!S!', '', $path);
                $router->add($path, static fn($v) => $path . $v)->disableReflection();
            }
            for ($i = 0; $i < 5; $i++) {
                foreach ($results as $path) {
                    $res = $router->dispatch($path);
                    $count++;
                    if ($res !== $path) {
                        throw new \Exception('Failed ' . $res . ' vs ' . $path);
                    }
                }
            }
        });

        echo 'Count: ' . $count;


    }


}