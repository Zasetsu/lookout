<?php

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Zasetsu\Lookout\Jobs\IngestTraceJob;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\JobRecorder;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

function makeSyncJobForRecorder(string $name): SyncJob
{
    return new SyncJob(app(), json_encode([
        'job' => $name,
        'data' => [],
    ]), 'sync', 'sync');
}

describe('JobRecorder', function () {
    it('restores parent traces when nested sync jobs have empty Laravel job IDs', function () {
        config(['lookout.enabled' => true]);
        Queue::fake();

        $buffer = new TraceBuffer;
        $requestContext = new ExecutionContext;
        $requestContext->type = 'request';
        $requestContext->name = 'POST /dispatch';
        $requestEvent = ChildEvent::make($requestContext->traceId, 'query')
            ->withLabel('select parent');

        $buffer->setContext($requestContext);
        $buffer->markSampled();
        $buffer->addEvent($requestEvent);

        $recorder = new JobRecorder($buffer, new Redactor);
        $parentJob = makeSyncJobForRecorder('App\\Jobs\\ParentJob');
        $childJob = makeSyncJobForRecorder('App\\Jobs\\ChildJob');

        $recorder->handleJobProcessing(new JobProcessing('sync', $parentJob));
        $recorder->handleJobProcessing(new JobProcessing('sync', $childJob));
        $recorder->handleJobProcessed(new JobProcessed('sync', $childJob));

        expect($buffer->getContext()?->name)->toBe('App\\Jobs\\ParentJob');

        $recorder->handleJobProcessed(new JobProcessed('sync', $parentJob));

        Queue::assertPushed(IngestTraceJob::class, 2);
        expect($buffer->getContext())->toBe($requestContext)
            ->and($buffer->getEvents())->toBe([$requestEvent]);
    });
});
