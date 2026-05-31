<?php

namespace Zasetsu\Lookout\Storage;

use InvalidArgumentException;

class StorageDriverResolver
{
    public function driver(): string
    {
        $driver = config('lookout.storage.driver', 'sqlite');

        return is_string($driver) && $driver !== '' ? strtolower($driver) : 'sqlite';
    }

    public function storageClass(): string
    {
        return match ($this->driver()) {
            'sqlite' => SqliteStorage::class,
            'mysql', 'pgsql' => DatabaseStorage::class,
            default => throw new InvalidArgumentException("Unsupported Lookout storage driver [{$this->driver()}]."),
        };
    }

    public function shouldRegisterManagedSqliteConnection(): bool
    {
        return $this->driver() === 'sqlite';
    }
}
