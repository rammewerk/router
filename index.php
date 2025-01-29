<?php

/**
 * This is a demo file for the router.
 * It is not meant to be used in production.
 */

use Rammewerk\Router\Router;

require __DIR__ . '/vendor/autoload.php';

$memoryBefore = memory_get_usage(); // Memory usage before the test
$memoryPeakBefore = memory_get_peak_usage()/1024/1024;                     // Peak memory after the test
$router = new Router(fn($class) => new $class());

$benchmark = new \Rammewerk\Router\Tests\Benchmark\BenchmarkTest($router);
$benchmark->runTest();

$memoryAfter = memory_get_peak_usage();                     // Peak memory after the test
$memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert bytes to MB
echo "<pre>Memory peak usage: " . $memoryUsed . " MB\n</pre>";
echo "<pre>Memory peak usage: " . $memoryPeakBefore . " MB\n</pre>";

exit;
