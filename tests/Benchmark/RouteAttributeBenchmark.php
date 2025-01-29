<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Foundation\Route;

#[Route('/user')]
class RouteAttributeBenchmark {

    public function index(): string {
        throw new \Exception('Invalid!! Should not be callable');
    }



    #[Route('/')]
    public function whateverItTakes(): string {
        return 'user';
    }



    #[Route('user/settings/email')]
    public function emailSettings(?int $id): string {
        return "user.settings.email.$id";
    }



    #[Route('/settings/email/save/many/trips')]
    public function longMethod(): string {
        return "user.long";
    }



    #[Route('/settings/email/save')]
    public function emailSettingsSave(): string {
        return "user.settings.email.save";
    }



}