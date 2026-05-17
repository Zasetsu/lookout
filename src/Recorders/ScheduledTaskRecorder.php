<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Support\TraceDispatcher;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class ScheduledTaskRecorder implements RecorderContract
{
    private array $taskContexts = [];

    public function __construct(
        private TraceBuffer $buffer,
        private Sampler $sampler,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(ScheduledTaskStarting::class, [$this, 'handleStarting']);
        Event::listen(ScheduledTaskFinished::class, [$this, 'handleFinished']);
        Event::listen(ScheduledBackgroundTaskFinished::class, [$this, 'handleBackgroundFinished']);
        Event::listen(ScheduledTaskFailed::class, [$this, 'handleFailed']);
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $context = ExecutionContext::forScheduledTask(
            $this->redactor->redact($event->task->description ?? $event->task->command ?? 'scheduled-task')
        );

        if (! $this->sampler->shouldSample($context)) {
            return;
        }

        $key = $event->task->mutexName();
        $this->taskContexts[$key] = $context;

        if ($this->runsInBackground($event->task)) {
            $this->cacheTaskContext($key, $context);
        }

        $this->buffer->setContext($context);
        $this->buffer->markSampled();
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $key = $event->task->mutexName();

        if ($this->runsInBackground($event->task) && $event->task->exitCode === null) {
            $this->deferBackgroundTask($key);

            return;
        }

        $this->finishTask($event->task, $event->task->exitCode !== 0);
    }

    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $this->finishTask($event->task, $event->task->exitCode !== 0);
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->finishTask($event->task, true, $event->exception);
    }

    protected function finishTask(ScheduledEvent $task, bool $failed, ?\Throwable $exception = null): void
    {
        $key = $task->mutexName();
        $context = $this->taskContexts[$key] ?? null;
        $restoredFromCache = false;

        if ($context === null && $this->runsInBackground($task)) {
            $context = $this->pullCachedTaskContext($key);
            $restoredFromCache = $context !== null;
        }

        if ($context === null) {
            return;
        }

        unset($this->taskContexts[$key]);

        if ($this->runsInBackground($task) && ! $restoredFromCache) {
            $this->forgetCachedTaskContext($key);
        }

        $this->buffer->setContext($context);
        $this->buffer->markSampled();

        $context->finish();

        if ($failed) {
            $context->markFailed();
        }

        if ($exception !== null) {
            $message = $this->redactor->redact($exception->getMessage());

            $this->buffer->addEvent(
                ChildEvent::make($context->traceId, 'scheduled_task_failed')
                    ->withLabel(get_class($exception).': '.$message)
                    ->withPayload([
                        'exception_class' => get_class($exception),
                        'message' => $message,
                    ])
            );
        }

        if (! $this->buffer->shouldCollect()) {
            return;
        }

        $data = $this->buffer->flush();

        if ($data['context'] === null) {
            return;
        }

        TraceDispatcher::dispatch($data['context'], $data['events']);
    }

    protected function deferBackgroundTask(string $key): void
    {
        $context = $this->taskContexts[$key] ?? null;

        if ($context !== null && $this->buffer->getContext()?->traceId === $context->traceId) {
            $this->buffer->clear();
        }

        unset($this->taskContexts[$key]);
    }

    protected function runsInBackground(ScheduledEvent $task): bool
    {
        return (bool) $task->runInBackground;
    }

    protected function cacheTaskContext(string $key, ExecutionContext $context): void
    {
        try {
            Cache::put($this->cacheKey($key), $context, now()->addDay());
        } catch (\Throwable $e) {
            logger()->warning('Lookout scheduled task context cache failed', [
                'task_key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function pullCachedTaskContext(string $key): ?ExecutionContext
    {
        try {
            $context = Cache::pull($this->cacheKey($key));

            return $context instanceof ExecutionContext ? $context : null;
        } catch (\Throwable $e) {
            logger()->warning('Lookout scheduled task context restore failed', [
                'task_key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function forgetCachedTaskContext(string $key): void
    {
        try {
            Cache::forget($this->cacheKey($key));
        } catch (\Throwable $e) {
            logger()->warning('Lookout scheduled task context cleanup failed', [
                'task_key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function cacheKey(string $key): string
    {
        return 'lookout:scheduled-task:'.sha1($key);
    }
}
