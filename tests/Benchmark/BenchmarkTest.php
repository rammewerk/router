<?php

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Foundation\RouteUtility;

class BenchmarkTest extends Benchmark {

    protected int $iterations = 10000;



    private function mockTryMethod(string $match): bool {
        return $match === 'hello_world';
    }


    private function createMockRoute(): object {
        return new class() {
            public string $context_match = 'hello/world/we/are/absolutely/fine';
            public string $context_no_match = 'no/match/path/exists/here';
            public string $args = '';
        };
    }



    public function case(): void {

        $route = $this->createMockRoute();

        // Benchmark: workingContext and no match
        $this->benchmark('new_matcher', function () use ($route) {

            $workingContext = $route->context_no_match;

            while ($workingContext) {
                if ($this->mockTryMethod(str_replace('/', '_', $workingContext))) {
                    throw new \Exception('This should never be reached');
                }
                RouteUtility::extractLastSegment($workingContext);
            }

            $workingContext = $route->context_match;
            while ($workingContext) {
                if ($this->mockTryMethod(str_replace('/', '_', $workingContext))) {
                    $result = ltrim(substr($route->context_match, strlen($workingContext)), '/');
                    if( $result !== 'we/are/absolutely/fine' ) throw new \Exception('Output not same in new_matcher');
                    break;
                }
                RouteUtility::extractLastSegment($workingContext);
            }

        });


        $route = $this->createMockRoute();

        $this->benchmark('old_matcher', function () use ($route) {

            $workingContext = $route->context_no_match;
            $args = '';

            while ($workingContext) {
                if ($this->mockTryMethod(str_replace('/', '_', $workingContext))) {
                    throw new \Exception('This should never be reached');
                }
                RouteUtility::prependSegment($args, RouteUtility::extractLastSegment($workingContext));
            }
            $result = $args;

            $workingContext = $route->context_match;
            $args = '';
            while ($workingContext) {
                if ($this->mockTryMethod(str_replace('/', '_', $workingContext))) {
                    $result = $args;
                    if( $result !== 'we/are/absolutely/fine' ) throw new \Exception('Output not same in new_matcher');
                    break;
                }
                RouteUtility::prependSegment($args, RouteUtility::extractLastSegment($workingContext));
            }

        });



        $route = $this->createMockRoute();

        // Benchmark: workingContext and no match
        $this->benchmark('new_alt', function () use ($route) {

            $workingContext = str_replace('/', '_', $route->context_no_match);

            while ($workingContext) {
                if ($this->mockTryMethod($workingContext)) {
                    throw new \Exception('This should never be reached');
                }

                RouteUtility::removeLastSegmentFromMethodName($workingContext);
            }

            $workingContext = str_replace('/', '_', $route->context_match);
            while ($workingContext) {
                if ($this->mockTryMethod($workingContext)) {
                    $result = ltrim(substr($route->context_match, strlen($workingContext)), '/');
                    if( $result !== 'we/are/absolutely/fine' ) throw new \Exception('Output not same in new_matcher');
                    break;
                }
                RouteUtility::removeLastSegmentFromMethodName($workingContext);
            }

        });


    }


}