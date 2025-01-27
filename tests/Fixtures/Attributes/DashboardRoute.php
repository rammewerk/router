<?php

namespace Rammewerk\Router\Tests\Fixtures\Attributes;

use Rammewerk\Router\Foundation\Route;

#[Route('/dashboard')]
class DashboardRoute {

    #[Route('/stats/*/details')]
    public function stats_page(string $param1, string $param2, ?string $param3 = null): string {
        return $param1 . $param2 . $param3;
    }



    #[Route('/profile')]
    public function profile_page(): string {
        return 'Profile page';
    }


}