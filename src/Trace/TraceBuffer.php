<?php

namespace Zasetsu\Lookout\Trace;

class TraceBuffer
{
    private ?ExecutionContext $context = null;

    private array $events = [];

    private bool $sampled = false;

    public function setContext(ExecutionContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): ?ExecutionContext
    {
        return $this->context;
    }

    public function addEvent(ChildEvent $event): void
    {
        $this->events[] = $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function markSampled(): void
    {
        $this->sampled = true;
    }

    public function isSampled(): bool
    {
        return $this->sampled;
    }

    public function shouldCollect(): bool
    {
        return config('lookout.enabled', true) && $this->sampled && $this->context !== null;
    }

    public function flush(): array
    {
        $data = [
            'context' => $this->context?->toArray(),
            'events' => array_map(fn (ChildEvent $e) => $e->toArray(), $this->events),
        ];

        $this->clear();

        return $data;
    }

    public function clear(): void
    {
        $this->context = null;
        $this->events = [];
        $this->sampled = false;
    }

    public function hasException(): bool
    {
        return collect($this->events)->contains(
            fn (ChildEvent $e) => $e->eventType === 'exception'
        );
    }
}
