<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Fixtures\Attributes;

use Rammewerk\Router\Foundation\Route;

class DashboardRoute {

    #[Route('/test/multiple/*/*/*/*')]
    #[Route('/dashboard/stats/*/details/*/*')]
    #[Route('/dashboard/stats/extra/*/*')]
    public function stats_page(string $param1, string $param2, ?string $param3 = null): string {
        return $param1 . $param2 . $param3;
    }



    #[Route('/dashboard/profile')]
    public function profile_page(): string {
        return 'Profile page';
    }


}