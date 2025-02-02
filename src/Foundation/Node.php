<?php

declare(strict_types=1);

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;

class Node {

    public ?RouteDefinition $route = null;

    /** @var array<string,Node> Child nodes indexed by segment name */
    private array $children = [];

    /** @var Node|null Wildcard child node for '*' segments */
    private ?Node $wildcard = null;

    /** @var string Remaining (compact) part of the path stored in this node */
    private string $compact = '';



    /**
     * Inserts a route for a given path.
     *
     * If the current node holds a compact tail and the inserted path diverges, the tail is split,
     * and the differing portion is moved to a new child node.
     *
     * @param string $path           The URI path to insert.
     * @param RouteDefinition $route The route definition for the path.
     */
    public function insert(string $path, RouteDefinition $route): void {
        $currentNode = $this;
        while ($path !== '') {

            // If a compact tail exists, try to match its prefix with the new path.
            if ($currentNode->compact !== '') {

                // Determine the common prefix between the current compact tail and the remaining path.
                $commonPrefix = self::commonPrefix($currentNode->compact, $path);

                // If the common prefix doesn't cover the entire compact tail, we need to split.
                if ($commonPrefix !== $currentNode->compact) {
                    // The current compact tail and the new path diverge.
                    // Create a new child node with the remainder of the current compact tail.
                    $childNode = new self();
                    $childNode->route = $currentNode->route;
                    $childNode->children = $currentNode->children;
                    $childNode->wildcard = $currentNode->wildcard;
                    $childNode->compact = substr($currentNode->compact, strlen($commonPrefix));

                    $childKey = RouteUtility::extractFirstSegment($childNode->compact);
                    $childNode->compact = trim($childNode->compact, '/ ');

                    // Reset the current node to hold only the common prefix.
                    $currentNode->route = null;
                    $currentNode->children = [$childKey => $childNode];
                    $currentNode->wildcard = null;
                    $currentNode->compact = $commonPrefix;
                }

                // Remove the matched common prefix from the remaining path.
                $path = substr($path, strlen($commonPrefix));
                if ($path === '') {
                    break;
                }
            }

            // If no children or wildcard exist, try to store as much of the path as possible.
            // If no children or wildcard exist, try to store as much of the path as possible.
            if ($currentNode->wildcard === null && empty($currentNode->children) && empty($currentNode->route)) {
                if (!str_contains($path, '*')) {
                    // No wildcard in the remaining path; simply compact the entire remainder.
                    $currentNode->compact .= $path;
                    $path = '';
                    break;
                }
                // If there is a wildcard in the remaining path, not first character, compact only up to the wildcard.
                if (($wildcardPos = strpos($path, '*')) !== false) {
                    $currentNode->compact .= substr($path, 0, $wildcardPos);
                    $path = substr($path, $wildcardPos);
                    RouteUtility::extractFirstSegment($path);
                    $currentNode->wildcard = new self();
                    $currentNode = $currentNode->wildcard;
                    continue;
                }
            }



            // Extract the next segment from the remaining path.
            $segment = RouteUtility::extractFirstSegment($path);


            if ($segment === '*') {
                // Create or reuse the wildcard child.
                $currentNode->wildcard ??= new self();
                $currentNode = $currentNode->wildcard;
                continue;
            }

            // Create a new child for the segment if it doesn't exist.
            if (!isset($currentNode->children[$segment])) {
                $currentNode->children[$segment] = new self();
            }

            $currentNode = $currentNode->children[$segment];

        }

        // Set the route for the final node.
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
                    $path = substr($path, strlen($currentNode->compact));
                } else {
                    return null;
                }
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



    /**
     * Computes the common prefix between two strings.
     *
     * @param string $firstString  The first string.
     * @param string $secondString The second string.
     *
     * @return string The common prefix shared by both strings.
     */
    private static function commonPrefix(string $firstString, string $secondString): string {
        $minLength = min(strlen($firstString), strlen($secondString));
        $i = 0;
        while ($i < $minLength && $firstString[$i] === $secondString[$i]) {
            $i++;
        }
        return substr($firstString, 0, $i);
    }



}