<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Storage\StorageContract;

function latestDeployAudit(): array
{
    $entry = DB::connection('lookout')
        ->table('lookout_audit_log')
        ->where('action', 'deploy_marker_created')
        ->orderByDesc('id')
        ->first();

    expect($entry)->not->toBeNull();

    return [
        'action' => $entry->action,
        'details' => json_decode((string) $entry->details, true),
    ];
}

describe('MarkDeployCommand', function () {
    it('requires version and environment', function () {
        $code = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
        ]);

        expect($code)->toBe(1)
            ->and(Artisan::output())->toContain('The --environment option is required.');
    });

    it('creates deploy markers and audits the command even when the API is disabled', function () {
        config(['lookout.api.enabled' => false]);

        $code = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--commit' => 'abc123',
            '--branch' => 'main',
            '--author' => 'Jane Doe',
            '--source' => 'github_actions',
            '--compare-url' => 'https://github.com/acme/app/compare/old...abc123',
            '--notes' => 'Checkout latency fix',
            '--deployed-at' => '2026-05-31 15:00:00',
        ]);

        $marker = app(StorageContract::class)->getLatestDeployMarker('production');
        $audit = latestDeployAudit();

        expect($code)->toBe(0)
            ->and($marker['version'])->toBe('v1.2.3')
            ->and($marker['environment'])->toBe('production')
            ->and($marker['commit'])->toBe('abc123')
            ->and($marker['deployed_at'])->toBe('2026-05-31 15:00:00')
            ->and($audit['details']['marker_id'])->toBe($marker['id'])
            ->and($audit['details']['created'])->toBeTrue()
            ->and($audit['details']['via'])->toBe('command');
    });

    it('updates existing deploy markers idempotently and audits update outcomes', function () {
        Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--commit' => 'abc123',
            '--notes' => 'Initial deploy',
            '--deployed-at' => '2026-05-31 15:00:00',
        ]);

        $code = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--commit' => 'abc123',
            '--notes' => 'Updated deploy notes',
            '--deployed-at' => '2026-05-31 15:05:00',
        ]);

        $markers = app(StorageContract::class)->getDeployMarkers();
        $audit = latestDeployAudit();

        expect($code)->toBe(0)
            ->and($markers['total'])->toBe(1)
            ->and($markers['data'][0]['notes'])->toBe('Updated deploy notes')
            ->and($markers['data'][0]['deployed_at'])->toBe('2026-05-31 15:05:00')
            ->and($audit['details']['created'])->toBeFalse();
    });

    it('rejects invalid compare URLs and deploy timestamps', function () {
        $invalidUrl = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--compare-url' => 'not a url',
        ]);

        expect($invalidUrl)->toBe(1)
            ->and(Artisan::output())->toContain('The --compare-url option must be a valid URL.');

        $invalidDate = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--deployed-at' => 'not a date',
        ]);

        expect($invalidDate)->toBe(1)
            ->and(Artisan::output())->toContain('The --deployed-at option must be a valid date.');
    });

    it('rejects deploy marker values that exceed storage limits', function () {
        $longRelease = Artisan::call('lookout:mark-deploy', [
            '--release' => str_repeat('v', 121),
            '--environment' => 'production',
        ]);

        expect($longRelease)->toBe(1)
            ->and(Artisan::output())->toContain('The --release option may not be greater than 120 characters.');

        $longEnvironment = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => str_repeat('p', 81),
        ]);

        expect($longEnvironment)->toBe(1)
            ->and(Artisan::output())->toContain('The --environment option may not be greater than 80 characters.');

        $longCompareUrl = Artisan::call('lookout:mark-deploy', [
            '--release' => 'v1.2.3',
            '--environment' => 'production',
            '--compare-url' => 'https://example.test/'.str_repeat('a', 2049),
        ]);

        expect($longCompareUrl)->toBe(1)
            ->and(Artisan::output())->toContain('The --compare-url option may not be greater than 2048 characters.');
    });
});
