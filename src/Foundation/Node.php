<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;

class Node implements NodeInterface {

    public ?RouteDefinition $route = null;

    /** @var array<string,Node> Child nodes indexed by segment name */
    private array $children = [];

    /** @var Node|null Wildcard child node for '*' segments */
    private ?Node $wildcard = null;

    /** @var string Remaining (compact) part of the path stored in this node */
    private string $compact = '';



    /**
     * @param string $path
     * @param RouteDefinition $route
     */
    public function insert(string $path, RouteDefinition $route): void {

        $currentNode = $this;

        while (true) {

            if ($currentNode->compact !== '') {

                $childNode = new self();
                $childNode->route = $currentNode->route;
                $childNode->children = $currentNode->children;
                $childNode->wildcard = $currentNode->wildcard;
                $childNode->compact = $currentNode->compact;
                $childKey = RouteUtility::extractFirstSegment($childNode->compact);

                $currentNode->route = null;
                $currentNode->children = [$childKey => $childNode];
                $currentNode->wildcard = null;
                $currentNode->compact = '';

            }

            if ($path === '') {
                break;
            }

            $segment = RouteUtility::extractFirstSegment($path);

            if ($segment === '*') {
                $currentNode->wildcard ??= new self();
                $currentNode = $currentNode->wildcard;
                continue;
            }

            if (isset($currentNode->children[$segment])) {
                $currentNode = $currentNode->children[$segment];
                continue;
            }

            $currentNode->children[$segment] = new self();
            $currentNode = $currentNode->children[$segment];
            $currentNode->compact = $path;
            break;

        }

        $currentNode->route = $route;

    }



    /**
     * Matches a given path and returns the associated RouteDefinition.
     *
     * The method attempts to consume the compact tail and segments sequentially. If a wildcard
     * is encountered, any unmatched segments are passed as parameters.
     *
     * @param string $path The URI path to match.
     *
     * @return RouteDefinition|null The matched route or null if no match is found.
     */
    public function match(string $path): ?RouteDefinition {
        $currentNode = $this;

        while (true) {

            // If the node has a compact tail, it must match the beginning of the remaining path.
            if ($currentNode->compact !== '') {
                if (str_starts_with($path, $currentNode->compact)) {
                    $path = trim(substr($path, strlen($currentNode->compact)), '/');
                    break;
                }
                return null;
            }


            // If all segments are consumed, break out.
            if ($path === '') {
                break;
            }

            // Extract the next segment.
            $segment = RouteUtility::extractFirstSegment($path);

            // Check for an exact child node match.
            if (isset($currentNode->children[$segment])) {
                $currentNode = $currentNode->children[$segment];
                continue;
            }

            // If there's a wildcard child, attempt to match it recursively.
            if (($currentNode->wildcard !== null) && $route = $currentNode->wildcard->match($path)) {
                RouteUtility::prependSegment($route->nodeContext, $segment);
                return $route;
            }

            // If no match is found, restore the segment and break.
            RouteUtility::prependSegment($path, $segment);
            break;
        }


        // If there are leftover segments, append them as parameters if a route was found.
        if ($currentNode->route !== null && $path !== '') {
            RouteUtility::appendSegment($currentNode->route->nodeContext, $path);
        }

        return $currentNode->route;
    }



}