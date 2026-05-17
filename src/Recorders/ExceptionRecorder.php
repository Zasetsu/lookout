<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Zasetsu\Lookout\Alerting\ThresholdEvaluator;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class ExceptionRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
        private StorageContract $storage,
    ) {}

    public function register(): void
    {
        $handler = app(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (\Throwable $e) {
                $this->handleException($e);
            });
        }
    }

    public function handleException(\Throwable $exception): void
    {
        $context = $this->buffer->getContext();
        $hadActiveContext = $context !== null;

        if ($context === null) {
            $context = ExecutionContext::forRequest(request());
            $context->type = 'exception';
            $context->name = get_class($exception);
            $context->markFailed();

            $this->buffer->setContext($context);
        }

        $this->buffer->markSampled();

        $context->markFailed();

        $traceLines = explode("\n", $exception->getTraceAsString());
        $redactedTrace = collect($traceLines)
            ->map(fn (string $line): string => $this->redactor->redact($line))
            ->take(20)
            ->values()
            ->all();

        $childEvent = ChildEvent::make($context->traceId, 'exception')
            ->withLabel(get_class($exception).': '.$this->redactor->redact($exception->getMessage()))
            ->withPayload($this->redactor->redact([
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'stack_trace' => $redactedTrace,
                'url' => ExecutionContext::sanitizedUrlForRequest(request()),
            ]));

        $this->buffer->addEvent($childEvent);

        $this->upsertExceptionGroup($exception);

        if (config('lookout.ingestion.sync_exceptions', true) && ! $hadActiveContext) {
            try {
                $context->requestHeaders = $this->redactor->redact($context->requestHeaders);

                $this->storage->storeTraceBatch(
                    $context->toArray(),
                    array_map(fn (ChildEvent $e) => $e->toArray(), $this->buffer->getEvents())
                );
                $this->buffer->clear();
            } catch (\Throwable $e) {
                logger()->warning('Lookout sync exception storage failed', [
                    'trace_id' => $context->traceId,
                    'error' => $e->getMessage(),
                ]);
            }

            if (config('lookout.alerting.enabled', false)) {
                try {
                    app(ThresholdEvaluator::class)->evaluate();
                } catch (\Throwable $e) {
                    logger()->warning('Lookout sync alert evaluation failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function upsertExceptionGroup(\Throwable $exception): void
    {
        try {
            $fingerprint = hash('sha256', get_class($exception).'|'.$exception->getFile().'|'.$exception->getLine());

            $this->storage->upsertExceptionGroup($fingerprint, [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $this->redactor->redact($exception->getMessage()),
                'first_seen' => now()->toDateTimeString(),
                'last_seen' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // Silent fail — don't crash the app for observability issues
        }
    }
}
