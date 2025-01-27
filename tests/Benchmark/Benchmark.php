<?php

namespace Rammewerk\Router\Tests\Benchmark;

abstract class Benchmark {

    protected int $iterations = 1000;
    private array $results = [];



    abstract public function case(): void;



    public function runTest(): void {
        $this->case();
        $this->printResults($this->results);
    }



    protected function benchmark(string $id, callable $test): void {
        gc_collect_cycles();
        $start = hrtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $test();
        }
        $end = hrtime(true);

        $time = ($end - $start) / 1e+6; // Convert nanoseconds to milliseconds
        $this->results[$id] = $time;
    }



    private function printResults(array $results): void
    {
        // Print results
        $classExploded = explode('\\', get_class($this));
        $class = array_pop($classExploded);

        // Sort results by time, fastest first
        asort($results);

        // Get the fastest time for percentage calculations
        $fastestTime = reset($results);

        // Table header
        $output = "<pre>\n";
        $output .= "<strong>";
        $output .= $class . ' with ' . $this->iterations . " iterations\n";
        $output .= "</strong>\n";
        $output .= "| Rank | Method                 | Time (ms) |   %  |\n";
        $output .= "|------|------------------------|-----------|------|\n";

        $rank = 1;
        foreach ($results as $method => $time) {
            $timeMs = $time;
            $timePercentage = round(($timeMs / $fastestTime) * 100, 2);
            $output .= sprintf(
                "| %-4d | %-22s | %-5.7f | %-3.0f%% |\n",
                $rank,
                $method,
                $timeMs,
                $timePercentage
            );
            $rank++;
        }

        $output .= "</pre>";

        echo $output;
    }


}