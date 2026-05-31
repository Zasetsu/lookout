<?php

use Zasetsu\Lookout\Storage\DatabaseStorage;
use Zasetsu\Lookout\Storage\SqliteStorage;
use Zasetsu\Lookout\Storage\StorageDriverResolver;

describe('StorageDriverResolver', function () {
    it('uses sqlite as the default package-managed storage driver', function () {
        config(['lookout.storage.driver' => null]);

        $resolver = new StorageDriverResolver;

        expect($resolver->driver())->toBe('sqlite')
            ->and($resolver->storageClass())->toBe(SqliteStorage::class)
            ->and($resolver->shouldRegisterManagedSqliteConnection())->toBeTrue();
    });

    it('resolves mysql and pgsql to the generic database storage driver', function (string $driver) {
        config(['lookout.storage.driver' => $driver]);

        $resolver = new StorageDriverResolver;

        expect($resolver->driver())->toBe($driver)
            ->and($resolver->storageClass())->toBe(DatabaseStorage::class)
            ->and($resolver->shouldRegisterManagedSqliteConnection())->toBeFalse();
    })->with(['mysql', 'pgsql']);

    it('rejects unsupported storage drivers with a clear exception', function () {
        config(['lookout.storage.driver' => 'redis']);

        expect(fn () => (new StorageDriverResolver)->storageClass())
            ->toThrow(InvalidArgumentException::class, 'Unsupported Lookout storage driver [redis].');
    });
});
