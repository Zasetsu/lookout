<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function lookoutTestRouteUriExists(string $uri): bool
{
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->uri() === $uri) {
            return true;
        }
    }

    return false;
}

describe('route registration', function () {
    it('does not register dashboard routes when the dashboard is disabled', function () {
        config([
            'lookout.enabled' => true,
            'lookout.dashboard.enabled' => false,
            'lookout.dashboard.path' => 'disabled-lookout',
        ]);

        require dirname(__DIR__, 2).'/routes/web.php';

        expect(lookoutTestRouteUriExists('disabled-lookout'))->toBeFalse();
    });

    it('does not register API routes when the API is disabled', function () {
        config([
            'lookout.enabled' => true,
            'lookout.api.enabled' => false,
            'lookout.dashboard.path' => 'disabled-api',
        ]);

        require dirname(__DIR__, 2).'/routes/api.php';

        expect(lookoutTestRouteUriExists('disabled-api/api/health'))->toBeFalse();
    });

    it('does not match exception group routes with non-numeric ids', function (string $method, string $uri) {
        expect(fn () => Route::getRoutes()->match(Request::create($uri, $method)))
            ->toThrow(NotFoundHttpException::class);
    })->with([
        ['GET', '/lookout/exceptions/not-a-number'],
        ['POST', '/lookout/exceptions/not-a-number/resolve'],
        ['POST', '/lookout/exceptions/not-a-number/ignore'],
    ]);
});
