<?php

use Zasetsu\Lookout\Storage\SqliteStorage;

function makeDeployMarkerAttributes(array $overrides = []): array
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

describe('deploy marker storage', function () {
    beforeEach(function () {
        $this->storage = new SqliteStorage;
    });

    it('creates deploy markers with all fields', function () {
        $result = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes());

        expect($result['created'])->toBeTrue()
            ->and($result['marker']['id'])->toBeInt()
            ->and($result['marker']['version'])->toBe('v1.2.3')
            ->and($result['marker']['environment'])->toBe('production')
            ->and($result['marker']['commit'])->toBe('abc123')
            ->and($result['marker']['branch'])->toBe('main')
            ->and($result['marker']['author'])->toBe('Jane Doe')
            ->and($result['marker']['source'])->toBe('github_actions')
            ->and($result['marker']['compare_url'])->toBe('https://github.com/acme/app/compare/old...abc123')
            ->and($result['marker']['notes'])->toBe('Checkout latency fix')
            ->and($result['marker']['deployed_at'])->toBe('2026-05-31 15:00:00');
    });

    it('updates existing deploy markers by environment version and commit', function () {
        $created = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes());
        $updated = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'branch' => 'release/v1.2',
            'author' => 'Release Bot',
            'notes' => 'Retried from CI',
        ]));

        $listed = $this->storage->getDeployMarkers();

        expect($updated['created'])->toBeFalse()
            ->and($updated['marker']['id'])->toBe($created['marker']['id'])
            ->and($updated['marker']['branch'])->toBe('release/v1.2')
            ->and($updated['marker']['author'])->toBe('Release Bot')
            ->and($updated['marker']['notes'])->toBe('Retried from CI')
            ->and($listed['total'])->toBe(1);
    });

    it('updates existing deploy markers without a commit by environment and version', function () {
        $created = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'commit' => '',
            'notes' => 'Manual deploy',
        ]));
        $updated = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'commit' => null,
            'notes' => 'Manual deploy notes updated',
        ]));

        $listed = $this->storage->getDeployMarkers();

        expect($created['marker']['commit'])->toBeNull()
            ->and($updated['created'])->toBeFalse()
            ->and($updated['marker']['id'])->toBe($created['marker']['id'])
            ->and($updated['marker']['commit'])->toBeNull()
            ->and($updated['marker']['notes'])->toBe('Manual deploy notes updated')
            ->and($listed['total'])->toBe(1);
    });

    it('keeps separate deploy markers for different environments', function () {
        $production = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'environment' => 'production',
            'commit' => null,
        ]));
        $staging = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'environment' => 'staging',
            'commit' => null,
        ]));

        expect($production['created'])->toBeTrue()
            ->and($staging['created'])->toBeTrue()
            ->and($this->storage->getDeployMarkers()['total'])->toBe(2)
            ->and($this->storage->getDeployMarkers(['environment' => 'production'])['total'])->toBe(1);
    });

    it('lists deploy markers newest first and returns the latest marker', function () {
        $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'version' => 'v1.0.0',
            'commit' => 'old',
            'deployed_at' => '2026-05-31 10:00:00',
        ]));
        $latest = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'version' => 'v1.1.0',
            'commit' => 'new',
            'deployed_at' => '2026-05-31 12:00:00',
        ]));

        $listed = $this->storage->getDeployMarkers();

        expect($listed['data'][0]['id'])->toBe($latest['marker']['id'])
            ->and($this->storage->getLatestDeployMarker()['id'])->toBe($latest['marker']['id'])
            ->and($this->storage->getLatestDeployMarker('production')['id'])->toBe($latest['marker']['id'])
            ->and($this->storage->getLatestDeployMarker('staging'))->toBeNull();
    });

    it('returns deploy markers between two timestamps ordered oldest first', function () {
        $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'version' => 'v1.0.0',
            'commit' => 'before',
            'deployed_at' => '2026-05-31 09:00:00',
        ]));
        $first = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'version' => 'v1.1.0',
            'commit' => 'first',
            'deployed_at' => '2026-05-31 10:00:00',
        ]));
        $second = $this->storage->upsertDeployMarker(makeDeployMarkerAttributes([
            'version' => 'v1.2.0',
            'commit' => 'second',
            'deployed_at' => '2026-05-31 11:00:00',
        ]));

        $markers = $this->storage->getDeployMarkersBetween('2026-05-31 09:30:00', '2026-05-31 11:30:00', 'production');

        expect($markers)->toHaveCount(2)
            ->and($markers[0]['id'])->toBe($first['marker']['id'])
            ->and($markers[1]['id'])->toBe($second['marker']['id']);
    });
});
