<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class OutgoingHttpRecorder implements RecorderContract
{
    private array $requestStartTimes = [];

    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(RequestSending::class, [$this, 'handleRequestSending']);
        Event::listen(ResponseReceived::class, [$this, 'handleResponseReceived']);
        Event::listen(ConnectionFailed::class, [$this, 'handleConnectionFailed']);
    }

    public function handleRequestSending(RequestSending $event): void
    {
        $this->requestStartTimes[spl_object_id($event->request)] = microtime(true);
    }

    public function handleResponseReceived(ResponseReceived $event): void
    {
        $key = spl_object_id($event->request);
        $startTime = $this->requestStartTimes[$key] ?? microtime(true);
        $duration = (int) ((microtime(true) - $startTime) * 1000);
        unset($this->requestStartTimes[$key]);

        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $redactedUrl = $this->redactor->redactUrl($event->request->url());

        $childEvent = ChildEvent::make($traceId, 'outgoing_http')
            ->withDuration($duration)
            ->withLabel("{$event->request->method()} {$redactedUrl} — {$event->response->status()}")
            ->withPayload([
                'method' => $event->request->method(),
                'url' => $redactedUrl,
                'headers' => $this->redactor->redact($event->request->headers()),
                'response_status' => $event->response->status(),
                'response_headers' => $this->redactor->redact($event->response->headers()),
                'duration_ms' => $duration,
            ]);

        $this->buffer->addEvent($childEvent);
    }

    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $key = spl_object_id($event->request);
        $startTime = $this->requestStartTimes[$key] ?? microtime(true);
        $duration = (int) ((microtime(true) - $startTime) * 1000);
        unset($this->requestStartTimes[$key]);

        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $redactedUrl = $this->redactor->redactUrl($event->request->url());

        $childEvent = ChildEvent::make($traceId, 'outgoing_http')
            ->withDuration($duration)
            ->withLabel("{$event->request->method()} {$redactedUrl} — CONNECTION_FAILED")
            ->withPayload([
                'method' => $event->request->method(),
                'url' => $redactedUrl,
                'headers' => $this->redactor->redact($event->request->headers()),
                'failed' => true,
                'error' => $this->redactor->redact($event->exception->getMessage()),
                'duration_ms' => $duration,
            ]);

        $this->buffer->addEvent($childEvent);
    }
}
