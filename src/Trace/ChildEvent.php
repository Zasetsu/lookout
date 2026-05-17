<?php

namespace Zasetsu\Lookout\Trace;

class ChildEvent
{
    public string $traceId;

    public string $eventType;

    public float $timestamp;

    public ?int $duration = null;

    public ?string $labels = null;

    public array $payload = [];

    public array $tags = [];

    public function __construct(string $traceId, string $eventType)
    {
        $this->traceId = $traceId;
        $this->eventType = $eventType;
        $this->timestamp = microtime(true);
    }

    public static function make(string $traceId, string $eventType): self
    {
        return new self($traceId, $eventType);
    }

    public function withDuration(int $ms): self
    {
        $this->duration = $ms;

        return $this;
    }

    public function withLabel(string $label): self
    {
        $this->labels = $label;

        return $this;
    }

    public function withPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function withTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    protected function formatTimestamp(): string
    {
        $sec = (int) $this->timestamp;
        $usec = (int) (($this->timestamp - $sec) * 1000000);

        return date('Y-m-d H:i:s', $sec).sprintf('.%06d', $usec);
    }

    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'event_type' => $this->eventType,
            'timestamp' => $this->formatTimestamp(),
            'duration' => $this->duration,
            'labels' => $this->labels,
            'payload' => json_encode($this->payload),
            'tags' => ! empty($this->tags) ? json_encode($this->tags) : null,
        ];
    }
}
