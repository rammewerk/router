<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

abstract class Benchmark {

    protected int $iterations = 1000;
    private array $results = [];



    abstract public function case(): void;



    public function runTest(): void {
        $memoryBefore = memory_get_usage(); // Memory usage before the test
        $this->case();
        $this->printResults($this->results);
        $memoryAfter = memory_get_peak_usage();                     // Peak memory after the test
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert bytes to MB
        echo "<pre>Memory peak usage: " . $memoryUsed . " MB\n</pre>";
    }



    protected function benchmark(string $id, callable $test): void {
        gc_collect_cycles();
        $memoryBefore = memory_get_usage(false); // Memory usage before the test
        $start = hrtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $test();
        }
        $end = hrtime(true);
        $memoryAfter = memory_get_peak_usage(false);                // Peak memory after the test
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert bytes to MB

        $time = ($end - $start) / 1e+6; // Convert nanoseconds to milliseconds
        $this->results[$id] = [$time, $memoryUsed];
    }



    private function printResults(array $results): void {
        // Print results
        $classExploded = explode('\\', get_class($this));
        $class = array_pop($classExploded);

        // Sort results by time, fastest first
        usort($results, static fn($a, $b) => $a[0] <=> $b[0]);

        // Get the fastest time for percentage calculations
        $fastestTime = reset($results)[0];

        // Table header
        $output = "<pre>\n";
        $output .= "<strong>";
        $output .= $class . ' with ' . $this->iterations . " iterations\n";
        $output .= "</strong>\n";
        $output .= "| Rank | Method                 | Time (ms) |   %  |  Memory |\n";
        $output .= "|------|------------------------|-----------|------|---------|\n";

        $rank = 1;
        foreach ($results as $method => [$time, $memory]) {
            $timeMs = $time;
            $timePercentage = round(($timeMs / $fastestTime) * 100, 2);
            $output .= sprintf(
                "| %-4d | %-22s | %-5.7f | %-3.0f%% | %-6.4f |\n",
                $rank,
                $method,
                $timeMs,
                $timePercentage,
                $memory,
            );
            $rank++;
        }

        $output .= "</pre>";

        echo $output;
    }


}