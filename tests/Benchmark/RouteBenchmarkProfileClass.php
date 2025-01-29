<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

class RouteBenchmarkProfileClass {

    public function index(): string {
        return 'profile.index';
    }



    public function newsletter(): string {
        return 'profile.newsletter';
    }



    public function settings_email(int $id): string {
        return "profile.settings.email.$id";
    }


}