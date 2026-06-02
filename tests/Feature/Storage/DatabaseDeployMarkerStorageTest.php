<?php

use Zasetsu\Lookout\Storage\DatabaseStorage;

function makeDatabaseDeployMarkerAttributes(array $overrides = []): array
{
    return array_merge([
        'version' => 'v2.0.0',
        'environment' => 'production',
        'commit' => 'def456',
        'branch' => 'main',
        'author' => 'Release Bot',
        'source' => 'github_actions',
        'compare_url' => 'https://github.com/acme/app/compare/old...def456',
        'notes' => 'Generic storage deploy',
        'deployed_at' => '2026-05-31 16:00:00',
    ], $overrides);
}

describe('generic database deploy marker storage', function () {
    beforeEach(function () {
        config([
            'lookout.storage.driver' => env('LOOKOUT_TEST_STORAGE_DRIVER', 'mysql'),
            'lookout.storage.connection' => 'lookout',
        ]);

        $this->storage = new DatabaseStorage;
    });

    it('upserts deploy markers idempotently when a commit is present', function () {
        $created = $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes());
        $updated = $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes([
            'notes' => 'Updated notes',
        ]));

        expect($created['created'])->toBeTrue()
            ->and($updated['created'])->toBeFalse()
            ->and($updated['marker']['id'])->toBe($created['marker']['id'])
            ->and($updated['marker']['notes'])->toBe('Updated notes')
            ->and($this->storage->getDeployMarkers()['total'])->toBe(1);
    });

    it('upserts deploy markers idempotently when no commit is present', function () {
        $created = $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes([
            'commit' => null,
            'notes' => 'Manual deploy',
        ]));
        $updated = $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes([
            'commit' => '',
            'notes' => 'Manual deploy updated',
        ]));

        expect($created['marker']['commit'])->toBeNull()
            ->and($updated['created'])->toBeFalse()
            ->and($updated['marker']['id'])->toBe($created['marker']['id'])
            ->and($updated['marker']['notes'])->toBe('Manual deploy updated')
            ->and($this->storage->getDeployMarkers()['total'])->toBe(1);
    });

    it('returns the latest deploy marker through the generic storage path', function () {
        $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes([
            'version' => 'v1.9.0',
            'commit' => 'old',
            'deployed_at' => '2026-05-31 15:00:00',
        ]));
        $latest = $this->storage->upsertDeployMarker(makeDatabaseDeployMarkerAttributes([
            'version' => 'v2.0.0',
            'commit' => 'new',
            'deployed_at' => '2026-05-31 16:00:00',
        ]));

        expect($this->storage->getLatestDeployMarker()['id'])->toBe($latest['marker']['id'])
            ->and($this->storage->getLatestDeployMarker('production')['id'])->toBe($latest['marker']['id'])
            ->and($this->storage->getLatestDeployMarker('staging'))->toBeNull();
    });
});
