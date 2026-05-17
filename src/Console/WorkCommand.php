<?php

namespace Zasetsu\Lookout\Console;

use Illuminate\Console\Command;

class WorkCommand extends Command
{
    protected $signature = 'lookout:work
        {--queue= : The queue to listen on}
        {--tries=3 : Number of times to attempt a job}
        {--daemon : Run the worker in daemon mode}';

    protected $description = 'Start the Lookout queue worker';

    public function handle(): int
    {
        $queue = $this->option('queue') ?? config('lookout.ingestion.queue', 'default');
        $tries = (int) $this->option('tries');
        $connection = config('lookout.ingestion.connection') ?? config('queue.default');

        $this->info("Starting Lookout worker on queue: {$queue}");
        $this->info("Connection: {$connection}");

        $this->call('queue:work', [
            $connection,
            '--queue' => $queue,
            '--tries' => $tries,
            '--stop-when-empty' => false,
        ]);

        return self::SUCCESS;
    }
}
