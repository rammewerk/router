<?php

/**
 * This is a demo file for the router.
 * It is not meant to be used in production.
 */

require __DIR__ . '/vendor/autoload.php';

$benchmark = new \Rammewerk\Router\Tests\Benchmark\BenchmarkTest();
$benchmark->runTest();

exit;
