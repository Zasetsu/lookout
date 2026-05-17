<?php

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Zasetsu\Lookout\Jobs\IngestTraceJob;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Recorders\ScheduledTaskRecorder;
use Zasetsu\Lookout\Trace\TraceBuffer;

class ScheduledTaskRecorderEventMutexFake implements EventMutex
{
    public function create(ScheduledEvent $event)
    {
        return true;
    }

    public function exists(ScheduledEvent $event)
    {
        return false;
    }

    public function forget(ScheduledEvent $event): void {}
}

function makeScheduledTaskForRecorder(string $command = 'php artisan reports:send'): ScheduledEvent
{
    return new ScheduledEvent(new ScheduledTaskRecorderEventMutexFake, $command);
}

describe('ScheduledTaskRecorder', function () {
    beforeEach(function () {
        config([
            'lookout.enabled' => true,
            'lookout.sampling.scheduled_task' => 1.0,
        ]);

        Cache::flush();
        Queue::fake();
    });

    it('defers background scheduled tasks until the background completion event', function () {
        $buffer = new TraceBuffer;
        $recorder = new ScheduledTaskRecorder($buffer, new Sampler, new Redactor);
        $task = makeScheduledTaskForRecorder()->runInBackground();

        $recorder->handleStarting(new ScheduledTaskStarting($task));
        $recorder->handleFinished(new ScheduledTaskFinished($task, 0.01));

        Queue::assertNothingPushed();
        expect($buffer->getContext())->toBeNull();

        $task->exitCode = 0;
        $recorder->handleBackgroundFinished(new ScheduledBackgroundTaskFinished($task));

        Queue::assertPushed(IngestTraceJob::class, function (IngestTraceJob $job) {
            return $job->context['type'] === 'scheduled_task'
                && $job->context['status'] === 'success'
                && $job->context['duration'] !== null;
        });
    });

    it('finalizes failed scheduled tasks when Laravel emits the failed event', function () {
        $buffer = new TraceBuffer;
        $recorder = new ScheduledTaskRecorder($buffer, new Sampler, new Redactor);
        $task = makeScheduledTaskForRecorder();

        $recorder->handleStarting(new ScheduledTaskStarting($task));
        $recorder->handleFailed(new ScheduledTaskFailed($task, new RuntimeException('scheduler callback failed')));

        Queue::assertPushed(IngestTraceJob::class, function (IngestTraceJob $job) {
            return $job->context['type'] === 'scheduled_task'
                && $job->context['status'] === 'error'
                && $job->events[0]['event_type'] === 'scheduled_task_failed'
                && $job->events[0]['payload'] === json_encode([
                    'exception_class' => RuntimeException::class,
                    'message' => 'scheduler callback failed',
                ]);
        });

        expect($buffer->getContext())->toBeNull();
    });

    it('redacts scheduled task commands and failure exception messages', function () {
        $buffer = new TraceBuffer;
        $recorder = new ScheduledTaskRecorder($buffer, new Sampler, new Redactor);
        $task = makeScheduledTaskForRecorder('php artisan report:send --token=super-secret-token');

        $recorder->handleStarting(new ScheduledTaskStarting($task));

        expect($buffer->getContext()?->name)->toContain('token=***')
            ->and($buffer->getContext()?->name)->not->toContain('super-secret-token');

        $recorder->handleFailed(new ScheduledTaskFailed($task, new RuntimeException('failed token=super-secret-token')));

        Queue::assertPushed(IngestTraceJob::class, function (IngestTraceJob $job) {
            return $job->events[0]['labels'] === 'RuntimeException: failed token=***'
                && $job->events[0]['payload'] === json_encode([
                    'exception_class' => RuntimeException::class,
                    'message' => 'failed token=***',
                ]);
        });
    });
});
