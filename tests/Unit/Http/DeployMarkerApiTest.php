<?php

use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Tests\TestCase;

function loadLookoutApiRoutesForDeployMarkerTest(string $path = 'lookout'): void
{
    config([
        'lookout.enabled' => true,
        'lookout.api.enabled' => true,
        'lookout.dashboard.path' => $path,
        'cache.default' => 'array',
    ]);

    require dirname(__DIR__, 3).'/routes/api.php';
}

function deployMarkerApiPayload(array $overrides = []): array
{
    return array_merge([
        'version' => 'v1.2.3',
        'environment' => 'production',
        'commit' => 'abc123',
        'branch' => 'main',
        'author' => 'Jane Doe',
        'source' => 'github_actions',
        'compare_url' => 'https://github.com/acme/app/compare/old...abc123',
        'notes' => 'Checkout latency fix',
        'deployed_at' => '2026-05-31 15:00:00',
    ], $overrides);
}

describe('Deploy marker API', function () {
    it('does not register the deploy marker endpoint when the API is disabled', function () {
        /** @var TestCase $this */
        $this->postJson('/lookout/api/deploy-markers', deployMarkerApiPayload())
            ->assertNotFound();
    });

    it('keeps read endpoints on the general API token and rejects the deploy token', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('read-token-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => 'deploy-token',
        ]);

        $this->withToken('read-token')
            ->getJson('/read-token-lookout/api/health')
            ->assertOk();

        $this->withToken('deploy-token')
            ->getJson('/read-token-lookout/api/health')
            ->assertUnauthorized();
    });

    it('creates deploy markers with the deploy-specific token', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('deploy-token-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => 'deploy-token',
        ]);

        $response = $this->withToken('deploy-token')
            ->postJson('/deploy-token-lookout/api/deploy-markers', deployMarkerApiPayload());

        $response->assertCreated()
            ->assertJsonPath('data.version', 'v1.2.3')
            ->assertJsonPath('data.environment', 'production')
            ->assertJsonPath('meta.created', true);

        $audit = DB::connection('lookout')
            ->table('lookout_audit_log')
            ->where('action', 'deploy_marker_created')
            ->first();

        expect($audit)->not->toBeNull();
        expect(json_decode((string) $audit->details, true))
            ->toMatchArray([
                'version' => 'v1.2.3',
                'environment' => 'production',
                'commit' => 'abc123',
                'source' => 'github_actions',
                'created' => true,
                'via' => 'api',
            ]);
    });

    it('rejects the general API token for deploy marker writes', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('wrong-token-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => 'deploy-token',
        ]);

        $this->withToken('read-token')
            ->postJson('/wrong-token-lookout/api/deploy-markers', deployMarkerApiPayload())
            ->assertUnauthorized();
    });

    it('returns not found when the deploy marker token is not configured', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('missing-token-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => null,
        ]);

        $this->withToken('deploy-token')
            ->postJson('/missing-token-lookout/api/deploy-markers', deployMarkerApiPayload())
            ->assertNotFound();
    });

    it('validates deploy marker payloads before storage', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('invalid-deploy-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => 'deploy-token',
        ]);

        $this->withToken('deploy-token')
            ->postJson('/invalid-deploy-lookout/api/deploy-markers', deployMarkerApiPayload([
                'version' => '',
                'compare_url' => 'not a url',
                'deployed_at' => 'not a date',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version', 'compare_url', 'deployed_at']);
    });

    it('updates deploy markers idempotently', function () {
        /** @var TestCase $this */
        loadLookoutApiRoutesForDeployMarkerTest('idempotent-deploy-lookout');
        config([
            'lookout.api.token' => 'read-token',
            'lookout.api.deploy_marker_token' => 'deploy-token',
        ]);

        $this->withToken('deploy-token')
            ->postJson('/idempotent-deploy-lookout/api/deploy-markers', deployMarkerApiPayload([
                'notes' => 'Initial deploy',
            ]))
            ->assertCreated()
            ->assertJsonPath('meta.created', true);

        $this->withToken('deploy-token')
            ->postJson('/idempotent-deploy-lookout/api/deploy-markers', deployMarkerApiPayload([
                'notes' => 'Updated deploy notes',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.created', false)
            ->assertJsonPath('data.notes', 'Updated deploy notes');
    });
});
