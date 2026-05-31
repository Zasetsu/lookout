<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
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

        $context->requestBody = $this->captureRequestBody($event->request);

        if (defined('LARAVEL_START')) {
            $context->duration = (int) ((microtime(true) - LARAVEL_START) * 1000);
        } else {
            $context->duration = (int) ((microtime(true) - $context->timestamp) * 1000);
        }
        $context->memoryPeak = memory_get_peak_usage(true);
    }

    protected function captureRequestBody(Request $request): array
    {
        $maxBytes = (int) config('lookout.ingestion.max_request_body_bytes', 16384);
        $contentLength = $this->contentLength($request);

        if ($maxBytes <= 0) {
            return $this->truncatedRequestBody('', $contentLength);
        }

        if ($contentLength !== null && $contentLength > $maxBytes) {
            return $this->truncatedRequestBody($request->getContent(), $contentLength);
        }

        $body = $request->except(
            array_keys($request->file())
        );

        $encoded = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($encoded === false) {
            return [
                '_lookout_truncated' => true,
                '_lookout_original_size' => null,
                '_lookout_preview' => '',
            ];
        }

        if (strlen($encoded) <= $maxBytes) {
            return $this->redactor->redact($body);
        }

        return $this->truncatedRequestBody($encoded, strlen($encoded));
    }

    protected function contentLength(Request $request): ?int
    {
        $contentLength = $request->server('CONTENT_LENGTH');

        if (is_array($contentLength) || $contentLength === null) {
            return null;
        }

        $contentLength = (string) $contentLength;

        if (preg_match('/^\d+$/', $contentLength) === 1) {
            return (int) $contentLength;
        }

        return null;
    }

    protected function truncatedRequestBody(string $content, ?int $originalSize): array
    {
        $maxBytes = (int) config('lookout.ingestion.max_request_body_bytes', 16384);
        $preview = $maxBytes > 0 ? substr($content, 0, $maxBytes) : '';

        return [
            '_lookout_truncated' => true,
            '_lookout_original_size' => $originalSize,
            '_lookout_preview' => $preview !== '' ? $this->redactor->redact($preview) : '',
        ];
    }
}
