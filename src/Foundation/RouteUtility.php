<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use function ltrim;
use function str_replace;
use function strpos;

class RouteUtility {



    public static function extractFirstSegment(string &$path): string {
        if (($p = strpos($path, '/')) !== false) {
            $firstSegment = \substr($path, 0, $p);
            $path = \substr($path, $p + 1);
            return $firstSegment;
        }
        $firstSegment = $path;
        $path = '';
        return $firstSegment;
    }



    public static function prependSegment(string &$path, string $segment): void {
        if ($segment === '') return;
        $path = $segment . ($path !== '' ? '/' . ltrim($path, '/ ') : '');
    }



    public static function appendSegment(string &$path, string $segment): void {
        if ($segment === '') return;
        $path .= ($path !== '' ? '/' : '') . $segment;
    }



    public static function convertMethodNameToPath(string $method_name): string {
        return str_replace('_', '/', $method_name);
    }


}