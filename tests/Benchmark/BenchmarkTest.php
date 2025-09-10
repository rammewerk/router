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

        $testPathsData = __DIR__ . '/../TestData/test_suite_8_paths.json';
        $testPaths = json_decode(file_get_contents($testPathsData), true);

        $testResults = __DIR__ . '/../TestData/test_suite_8_results.json';
        $results = json_decode(file_get_contents($testResults), true);

        $testStatic = array_map(function ($path) {
            if (($p = strrpos($path, '/')) !== false) {
                $lastSegment = substr($path, $p + 1);
                return rtrim(substr($path, 0, $p + 1), '/');
            }
            return $path;
        }, $results);

        $count = 0;

        $this->benchmark('static', function () use ($results, $testStatic, &$count) {
            $router = new Router();
            foreach ($testStatic as $static_path) {
                $router->add($static_path, static fn() => $static_path)->disableReflection();
            }
            for ($i = 0; $i < 5; $i++) {
                foreach ($results as $path) {
                    $res = $router->dispatch($path);
                    $count++;
                    if (!str_starts_with($path, $res)) {
                        throw new \Exception('Failed ' . $res . ' vs ' . $path);
                    }
                }
            }
        });



        $this->benchmark('dynamics', function () use ($testPaths, $results, &$count) {
            $router = new Router();
            foreach ($testPaths as $path) {
                $path = rtrim(str_replace(['!S!', '!D!'], '*', $path), '*');
                $router->add($path, static fn(string $d, string $v) => 'true')->disableReflection();
            }
            for ($i = 0; $i < 5; $i++) {
                foreach ($results as $path) {
                    $res = $router->dispatch($path);
                    $count++;
                    if ($res !== 'true') {
                        throw new \Exception('Failed ' . $res . ' vs ' . $path);
                    }
                }
            }
        });

        echo 'Count: ' . $count;


    }


}