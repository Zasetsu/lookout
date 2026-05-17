<?php

namespace Zasetsu\Lookout\Support;

use Illuminate\Contracts\Bus\Dispatcher;
use Zasetsu\Lookout\Jobs\IngestTraceJob;

class TraceDispatcher
{
    public static function dispatch(array $context, array $events): void
    {
        try {
            $job = new IngestTraceJob($context, $events);
            $job->onConnection(config('lookout.ingestion.connection'));
            $job->onQueue(config('lookout.ingestion.queue'));

            app(Dispatcher::class)->dispatch($job);
        } catch (\Throwable $e) {
            logger()->warning('Lookout trace dispatch failed', [
                'trace_id' => $context['trace_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
