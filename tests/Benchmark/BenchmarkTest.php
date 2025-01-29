<?php

declare(strict_types=1);

namespace Rammewerk\Router\Tests\Benchmark;

use Rammewerk\Router\Foundation\RouteUtility;
use Rammewerk\Router\Router;

class BenchmarkTest extends Benchmark {

    protected int $iterations = 10000;



    public function __construct(private readonly Router $router) {}



    public function case(): void {

        $router = $this->router;



        $path = '/hello/world/i/am/a/test/path/a/very/long/one/that/is/longer/than/the/context/segment/and/should/match/the';
        $router->add($path, function (string $name) {
            return $name;
        });
        $path = '/hello/world/i/am/a/test/path/a/very/long/one/that/is/longer/than/the/context/segment/and/should/match/the/name';
        $this->benchmark('closure', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'name') {
                throw new \Exception('Invalid result: ' . $res);
            }
        });


        $router->add('/profile', RouteBenchmarkProfileClass::class);

        #$router->add('/profile/settings/email', RouteBenchmarkProfileClass::class)->classMethod('settings_email');
        $path = '/profile/settings/email/240';
        $this->benchmark('profile_email', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'profile.settings.email.240') {
                throw new \Exception('Invalid result: ' . $res);
            }
        });


        $path = '/profile';
        $this->benchmark('profile_index', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'profile.index') {
                throw new \Exception('Invalid result');
            }
        });


        $path = '/profile/newsletter';
        $this->benchmark('profile_newsletter', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'profile.newsletter') {
                throw new \Exception('Invalid result');
            }
        });



        $router->add('/user', RouteAttributeBenchmark::class);
        $path = '/user';
        $this->benchmark('user', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'user') {
                throw new \Exception('Invalid result: ' . $res);
            }
        });

        $path = '/user/settings/email/120';
        $this->benchmark('user_email', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'user.settings.email.120') {
                throw new \Exception('Invalid result: ' . $res);
            }
        });

        $path = '/user/settings/email/save/many/trips';
        $this->benchmark('user_long', function () use ($router, $path) {
            $res = $router->dispatch($path);
            if ($res !== 'user.long') {
                throw new \Exception('Invalid result: ' . $res);
            }
        });


    }


}