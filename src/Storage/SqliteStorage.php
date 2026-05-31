<?php

namespace Zasetsu\Lookout\Storage;

class SqliteStorage extends DatabaseStorage
{
    public function __construct()
    {
        parent::__construct();

        $this->configurePragmas();
    }

    protected function configurePragmas(): void
    {
        $path = config('lookout.storage.path', storage_path('lookout/lookout.sqlite'));

        if ($path !== ':memory:' && ! file_exists($path)) {
            return;
        }

        $pragmas = config('lookout.storage.pragmas', []);

        foreach ($pragmas as $pragma => $value) {
            if (is_string($value)) {
                $this->storageConnection()->statement("PRAGMA {$pragma} = {$value}");
            } elseif (is_int($value)) {
                $this->storageConnection()->statement("PRAGMA {$pragma} = {$value}");
            }
        }
    }

    public function getHealth(): array
    {
        $path = config('lookout.storage.path');
        $fileSize = is_string($path) && file_exists($path) ? filesize($path) : 0;

        return array_merge(parent::getHealth(), [
            'storage_driver' => 'sqlite',
            'storage_size_bytes' => $fileSize,
            'storage_size_mb' => round($fileSize / 1048576, 2),
        ]);
    }
}
