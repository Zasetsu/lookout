<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class QueryRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(QueryExecuted::class, [$this, 'handleQuery']);
    }

    public function handleQuery(QueryExecuted $event): void
    {
        if ($event->connectionName === config('lookout.storage.connection', 'lookout')) {
            return;
        }

        if (! $this->buffer->isSampled()) {
            return;
        }

        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $redactedSql = $this->redactor->redact($event->sql);

        $bindings = ($redactedSql !== $event->sql || $this->redactor->containsSensitiveContent($event->sql))
            ? array_map(fn () => '***', $event->bindings)
            : $this->redactor->redact($event->bindings);

        $childEvent = ChildEvent::make($traceId, 'query')
            ->withDuration((int) $event->time)
            ->withLabel("{$redactedSql} — ".number_format($event->time, 2).'ms')
            ->withPayload([
                'sql' => $redactedSql,
                'bindings' => $bindings,
                'connection' => $event->connectionName,
            ]);

        $this->buffer->addEvent($childEvent);
    }
}
