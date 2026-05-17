<?php

use Zasetsu\Lookout\Http\Controllers\Api\ApiController;
use Zasetsu\Lookout\Http\Controllers\Dashboard\DashboardController;
use Zasetsu\Lookout\Http\Middleware\BootstrapTrace;
use Zasetsu\Lookout\LookoutServiceProvider;
use Zasetsu\Lookout\Storage\StorageContract;

describe('Lookout controller middleware order', function () {
    it('applies dashboard throttling before dashboard auth checks', function () {
        config(['lookout.dashboard.rate_limit' => 3]);

        $controller = new DashboardController(app(StorageContract::class));
        $middleware = $controller->getMiddleware();

        expect($middleware[0]['middleware'])->toBe('throttle:3');
    });

    it('applies API throttling before API token checks', function () {
        $controller = new ApiController(app(StorageContract::class));
        $middleware = $controller->getMiddleware();

        expect($middleware[0]['middleware'])->toBe('throttle:120');
    });

    it('prepends trace bootstrap before host web and api middleware', function () {
        $router = app('router');
        $router->middlewareGroup('web', ['HostWebMiddleware']);
        $router->middlewareGroup('api', ['HostApiMiddleware']);

        $provider = new LookoutServiceProvider(app());
        $method = new ReflectionMethod($provider, 'registerMiddleware');
        $method->invoke($provider);

        $groups = $router->getMiddlewareGroups();

        expect($groups['web'][0])->toBe(BootstrapTrace::class)
            ->and($groups['api'][0])->toBe(BootstrapTrace::class)
            ->and($groups['web'][1])->toBe('HostWebMiddleware')
            ->and($groups['api'][1])->toBe('HostApiMiddleware');
    });
});
