<?php

namespace Zasetsu\Lookout\Console;

use Illuminate\Console\Command;
use Zasetsu\Lookout\Storage\StorageContract;

class PruneCommand extends Command
{
    protected $signature = 'lookout:prune
        {--days= : Number of days to retain (default: from config)}';

    protected $description = 'Prune old Lookout traces and events';

    public function handle(StorageContract $storage): int
    {
        $days = (int) ($this->option('days') ?? config('lookout.retention.days', 14));

        $this->info("Pruning records older than {$days} days...");

        $deleted = $storage->prune($days);

        $storage->logAudit('prune_run', null, null, [
            'days' => $days,
            'deleted_traces' => $deleted,
        ]);

        $this->info("Pruned {$deleted} traces (and their events).");

        return self::SUCCESS;
    }
}
