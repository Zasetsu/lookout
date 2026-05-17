<?php

namespace Zasetsu\Lookout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Zasetsu\Lookout\Alerting\ThresholdEvaluator;
use Zasetsu\Lookout\Storage\StorageContract;

class IngestTraceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public array $context,
        public array $events,
    ) {}

    public function handle(StorageContract $storage): void
    {
        $storage->storeTraceBatch($this->context, $this->events);

        $this->maybePrune($storage);

        if (config('lookout.alerting.enabled', false)) {
            try {
                app(ThresholdEvaluator::class)->evaluate();
            } catch (\Throwable $e) {
                logger()->warning('Lookout threshold evaluation failed', [
                    'trace_id' => $this->context['trace_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function maybePrune(StorageContract $storage): void
    {
        $chance = (int) config('lookout.retention.prune_chance', 1000);

        if ($chance > 0 && mt_rand(1, $chance) === 1) {
            try {
                $days = (int) config('lookout.retention.days', 14);
                $storage->prune($days);
            } catch (\Throwable $e) {
                logger()->warning('Lookout retention pruning failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        logger()->warning('Lookout ingest job failed', [
            'trace_id' => $this->context['trace_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
