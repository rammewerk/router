<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;

final class Node {

    public ?RouteDefinition $route = null;

    /** @var array<string,Node> */
    private array $children = [];

    /** @var Node|null For a '*' wildcard child */
    private ?Node $wildcard = null;



    /**
     * @param string $path
     * @param RouteDefinition $route
     */
    public function insert(string $path, RouteDefinition $route): void {

        if (!$path) {
            $this->route = $route;
            return;
        }

        $segment = RouteUtility::extractFirstSegment($path);

        if ($segment === '*') {
            $this->wildcard ??= new self();
            $this->wildcard->insert($path, $route);
            return;
        }

        $this->children[$segment] ??= new self();
        $this->children[$segment]->insert($path, $route);
    }



    /**
     * @param string $path
     *
     * @return RouteDefinition|null
     */
    public function match(string $path): ?RouteDefinition {

        if (!$path) {
            return $this->route;
        }

        $segment = RouteUtility::extractFirstSegment($path);

        // Exact child
        if (isset($this->children[$segment]) && $route = $this->children[$segment]->match($path)) {
            return $route;
        }

        // Wildcard child
        if ($this->wildcard && $route = $this->wildcard->match($path)) {
            RouteUtility::prependSegment($route->context, $segment);
            return $route;
        }

        RouteUtility::prependSegment($path, $segment);

        // If we're here and have a route, we accept leftover as extra params
        if ($this->route && $path !== '') {
            RouteUtility::appendSegment($this->route->context, $path);
        }

        return $this->route;

    }


}