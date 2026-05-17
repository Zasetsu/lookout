<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class RequestRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Sampler $sampler,
        private Filter $filter,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(RequestHandled::class, [$this, 'handleRequest']);
    }

    public function handleRequest(RequestHandled $event): void
    {
        $context = $this->buffer->getContext();

        if ($context === null) {
            if ($event->request->attributes->has('_lookout_trace_bootstrapped')) {
                return;
            }

            $context = ExecutionContext::forRequest($event->request);

            if (! $this->sampler->shouldSample($context)) {
                return;
            }

            if (! $this->filter->shouldKeepTrace($context)) {
                return;
            }

            $this->buffer->setContext($context);
            $this->buffer->markSampled();
        }

        if (! $this->buffer->isSampled()) {
            return;
        }

        $userId = $event->request->user()?->getAuthIdentifier();
        $context->userId = $userId !== null ? (string) $userId : null;
        $context->responseStatus = $event->response->getStatusCode();

        if ($event->response->getStatusCode() >= 500) {
            $context->markFailed();
        }

        $context->requestHeaders = $this->redactor->redact(
            $event->request->headers->all()
        );

        $requestBody = $this->redactor->redact(
            $event->request->except(array_keys($event->request->file() ?? []))
        );

        $context->requestBody = $this->limitRequestBody($requestBody);

        if (defined('LARAVEL_START')) {
            $context->duration = (int) ((microtime(true) - LARAVEL_START) * 1000);
        } else {
            $context->duration = (int) ((microtime(true) - $context->timestamp) * 1000);
        }
        $context->memoryPeak = memory_get_peak_usage(true);
    }

    protected function limitRequestBody(array $body): array
    {
        $encoded = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($encoded === false) {
            return [
                '_lookout_truncated' => true,
                '_lookout_original_size' => null,
                '_lookout_preview' => '',
            ];
        }

        $maxBytes = (int) config('lookout.ingestion.max_request_body_bytes', 16384);

        if ($maxBytes > 0 && strlen($encoded) <= $maxBytes) {
            return $body;
        }

        return [
            '_lookout_truncated' => true,
            '_lookout_original_size' => strlen($encoded),
            '_lookout_preview' => $maxBytes > 0 ? substr($encoded, 0, $maxBytes) : '',
        ];
    }
}
