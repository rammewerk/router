<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

class RouteUtility {



    public static function extractFirstSegment(string &$path): string {
        if (($p = strpos($path, '/')) !== false) {
            $firstSegment = substr($path, 0, $p);
            $path = substr($path, $p + 1);
            return $firstSegment;
        }
        $firstSegment = $path;
        $path = '';
        return $firstSegment;
    }


    public static function extractLastSegment(string &$path): string {
        if (($p = strrpos($path, '/')) !== false) {
            $lastSegment = substr($path, $p + 1);
            $path = substr($path, 0, $p);
            return $lastSegment;
        }
        $lastSegment = $path;
        $path = '';
        return $lastSegment;
    }




    public static function prependSegment(string &$path, string $segment): void {
        if (!$segment) return;
        $path = $segment . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }



    public static function appendSegment(string &$path, string $segment): void {
        if (!$segment) return;
        $path .= ($path !== '' ? '/' : '') . $segment;
    }



    public static function removeLastSegmentFromMethodName(string &$path): string {
        if (($p = strpos($path, '_')) !== false) {
            $lastSegment = substr($path, $p + 1);
            $path = substr($path, 0, $p);
            return $lastSegment;
        }
        $lastSegment = $path;
        $path = '';
        return $lastSegment;
    }


}