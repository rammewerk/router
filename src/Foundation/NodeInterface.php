<?php

namespace Rammewerk\Router\Foundation;

use Rammewerk\Router\Definition\RouteDefinition;

interface NodeInterface {

    public function insert(string $path, RouteDefinition $route): void;



    public function match(string $path): ?RouteDefinition;


}