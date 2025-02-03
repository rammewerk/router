<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;

final class PreviousNodeHandler implements NodeInterface {

    public ?RouteDefinition $route = null;

    /** @var array<string,PreviousNodeHandler> */
    private array $children = [];

    /** @var PreviousNodeHandler|null For a '*' wildcard child */
    private ?PreviousNodeHandler $wildcard = null;



    /**
     * @param string $path
     * @param RouteDefinition $route
     */
    public function insert(string $path, RouteDefinition $route): void {

        $currentNode = $this;

        while ($path !== '') {
            $segment = RouteUtility::extractFirstSegment($path);

            if ($segment === '*') {
                $currentNode->wildcard ??= new self();
                $currentNode = $currentNode->wildcard;
                continue;
            }

            $currentNode->children[$segment] ??= new self();
            $currentNode = $currentNode->children[$segment];
        }

        $currentNode->route = $route;

    }



    /**
     * @param string $path
     *
     * @return RouteDefinition|null
     */
    public function match(string $path): ?RouteDefinition {

        $currentNode = $this;

        while ($path !== '') {

            $segment = RouteUtility::extractFirstSegment($path);

            // Exact child
            if (isset($currentNode->children[$segment])) {
                $currentNode = $currentNode->children[$segment];
                continue;
            }

            // Wildcard child
            if ($currentNode->wildcard && $route = $currentNode->wildcard->match($path)) {
                RouteUtility::prependSegment($route->nodeContext, $segment);
                return $route;
            }

            RouteUtility::prependSegment($path, $segment);
            break;
        }

        // Handle leftover segments
        if ($currentNode->route && $path !== '') {
            RouteUtility::appendSegment($currentNode->route->nodeContext, $path);
        }

        return $currentNode->route;


    }


}