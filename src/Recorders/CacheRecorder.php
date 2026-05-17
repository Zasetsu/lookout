<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class CacheRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(CacheHit::class, [$this, 'handleHit']);
        Event::listen(CacheMissed::class, [$this, 'handleMissed']);
        Event::listen(KeyWritten::class, [$this, 'handleWritten']);
        Event::listen(KeyForgotten::class, [$this, 'handleForgotten']);
    }

    public function handleHit(CacheHit $event): void
    {
        $this->record('cache_hit', $event->key, $event->storeName);
    }

    public function handleMissed(CacheMissed $event): void
    {
        $this->record('cache_miss', $event->key, $event->storeName);
    }

    public function handleWritten(KeyWritten $event): void
    {
        $this->record('cache_write', $event->key, $event->storeName, $event->seconds);
    }

    public function handleForgotten(KeyForgotten $event): void
    {
        $this->record('cache_forget', $event->key, $event->storeName);
    }

    protected function record(string $operation, string $key, ?string $store, ?int $ttl = null): void
    {
        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $redactedKey = $this->redactor->redact($key);

        $payload = ['key' => $redactedKey, 'store' => $store ?? 'unknown', 'operation' => $operation];
        if ($ttl !== null) {
            $payload['ttl'] = $ttl;
        }

        $childEvent = ChildEvent::make($traceId, 'cache')
            ->withLabel("Cache {$operation}: {$redactedKey}")
            ->withPayload($payload);

        $this->buffer->addEvent($childEvent);
    }
}
