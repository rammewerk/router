<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

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

        $node = $this;

        while ($path !== '') {

            if ($node->compact !== '') {
                $child = new self();
                $child->route = $node->route;
                $child->children = $node->children;
                $child->wildcard = $node->wildcard;
                $child->compact = $node->compact;
                $childSegment = RouteUtility::extractFirstSegment($child->compact);

                $node->route = null;
                $node->children = [$childSegment => $child];
                $node->wildcard = null;
                $node->compact = '';
            }

            $segment = RouteUtility::extractFirstSegment($path);

            if ($segment === '*') {
                if ($path === '') {
                    break;
                }
                $node->wildcard ??= new self();
                $node = &$node->wildcard;
                continue;
            }

            if (isset($node->children[$segment])) {
                $node = &$node->children[$segment];
                continue;
            }

            $node->children[$segment] = new self();
            $node = &$node->children[$segment];

            if (!str_contains($path, '*')) {
                $node->compact = $path;
                break;
            }

        }

        $node->route = $route;

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

        while ($path !== '') {

            // If the node has a compact tail, it must match the beginning of the remaining path.
            if ($currentNode->compact !== '') {

                if (str_starts_with($path, $currentNode->compact)) {
                    $path = trim(substr($path, strlen($currentNode->compact)), '/');
                    break;
                }
                return null;
            }

            // Extract the next segment.
            $segment = RouteUtility::extractFirstSegment($path);

            // Check for an exact child node match.
            if (isset($currentNode->children[$segment])) {
                $currentNode = &$currentNode->children[$segment];
                continue;
            }

            // If there's a wildcard child, attempt to match it recursively.
            if ($currentNode->wildcard && $route = $currentNode->wildcard->match($path)) {
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