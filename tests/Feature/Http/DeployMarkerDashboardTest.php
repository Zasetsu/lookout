<?php

use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->withoutMiddleware();
});

function createDashboardDeployMarker(array $overrides = []): array
{
    return app(StorageContract::class)->upsertDeployMarker(array_merge([
        'version' => 'v1.2.3',
        'environment' => 'testing',
        'commit' => 'abc123',
        'branch' => 'main',
        'author' => 'Jane Doe',
        'source' => 'github_actions',
        'compare_url' => 'https://github.com/acme/app/compare/old...abc123',
        'notes' => 'Checkout latency fix',
        'deployed_at' => now()->subHour()->toDateTimeString(),
    ], $overrides))['marker'];
}

it('renders latest deploy context on the overview page', function () {
    /** @var TestCase $this */
    createDashboardDeployMarker();

    $this->get('/lookout')
        ->assertOk()
        ->assertSee('Latest deploy')
        ->assertSee('v1.2.3')
        ->assertSee('testing')
        ->assertSee('abc123')
        ->assertSee('Deploy markers');
});

it('renders a neutral overview state when no deploy has been recorded', function () {
    /** @var TestCase $this */
    $this->get('/lookout')
        ->assertOk()
        ->assertSee('Latest deploy')
        ->assertSee('No deploy recorded')
        ->assertDontSee('undefined');
});

it('renders deploy markers near the request trend', function () {
    /** @var TestCase $this */
    createDashboardDeployMarker([
        'version' => 'v2.0.0',
        'commit' => 'def456',
    ]);

    $this->get('/lookout/requests')
        ->assertOk()
        ->assertSee('Deploy markers')
        ->assertSee('v2.0.0')
        ->assertSee('def456')
        ->assertDontSee('undefined');
});

it('renders deploy markers near the exception trend', function () {
    /** @var TestCase $this */
    createDashboardDeployMarker([
        'version' => 'v3.0.0',
        'commit' => 'fedcba',
    ]);

    $this->get('/lookout/exceptions')
        ->assertOk()
        ->assertSee('Deploy markers')
        ->assertSee('v3.0.0')
        ->assertSee('fedcba')
        ->assertDontSee('undefined');
});
