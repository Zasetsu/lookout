<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Support\TraceDispatcher;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class JobRecorder implements RecorderContract
{
    private array $jobStartTimes = [];

    private array $parentContexts = [];

    private array $parentEvents = [];

    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(JobProcessing::class, [$this, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [$this, 'handleJobProcessed']);
        Event::listen(JobFailed::class, [$this, 'handleJobFailed']);
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobClass = $event->job->resolveName();
        $jobKey = $this->jobKey($event->job);

        if (str_starts_with($jobClass, 'Zasetsu\\Lookout\\')) {
            return;
        }

        $this->jobStartTimes[$jobKey] = microtime(true);

        if ($this->buffer->getContext() !== null) {
            $this->parentContexts[$jobKey] = $this->buffer->getContext();
            $this->parentEvents[$jobKey] = $this->buffer->getEvents();
            $this->buffer->clear();
        }

        $context = new ExecutionContext;
        $context->type = 'job';
        $context->name = $event->job->resolveName();

        $this->buffer->setContext($context);
        $this->buffer->markSampled();
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->recordJob($event->job, 'job_processed', $this->jobKey($event->job));
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->recordJob($event->job, 'job_failed', $this->jobKey($event->job), $event->exception);
    }

    protected function recordJob($job, string $type, string $jobKey, ?\Throwable $exception = null): void
    {
        $traceId = $this->buffer->getContext()?->traceId;

        if ($traceId === null) {
            return;
        }

        $startTime = $this->jobStartTimes[$jobKey] ?? microtime(true);
        $duration = (int) ((microtime(true) - $startTime) * 1000);

        $payload = [
            'job_id' => (string) $job->getJobId(),
            'job_class' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'attempts' => $job->attempts(),
        ];

        if ($exception) {
            $payload['exception'] = [
                'class' => get_class($exception),
                'message' => $this->redactor->redact($exception->getMessage()),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $childEvent = ChildEvent::make($traceId, $type)
            ->withDuration($duration)
            ->withLabel($job->resolveName().' — '.number_format($duration, 2).'ms')
            ->withPayload($payload);

        $this->buffer->addEvent($childEvent);
        unset($this->jobStartTimes[$jobKey]);

        $context = $this->buffer->getContext();
        if ($context) {
            $context->finish();

            if ($type === 'job_failed') {
                $context->markFailed();
            }

            $data = $this->buffer->flush();

            if ($data['context'] !== null) {
                TraceDispatcher::dispatch($data['context'], $data['events']);
            }
        }

        if (isset($this->parentContexts[$jobKey])) {
            $this->buffer->setContext($this->parentContexts[$jobKey]);
            foreach ($this->parentEvents[$jobKey] as $evt) {
                $this->buffer->addEvent($evt);
            }
            $this->buffer->markSampled();
            unset($this->parentContexts[$jobKey], $this->parentEvents[$jobKey]);
        }
    }

    protected function jobKey($job): string
    {
        $jobId = (string) $job->getJobId();

        if ($jobId !== '') {
            return 'id:'.$jobId;
        }

        return 'object:'.spl_object_id($job);
    }
}
