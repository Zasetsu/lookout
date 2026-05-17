<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Zasetsu\Lookout\Jobs\IngestTraceJob;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Recorders\CommandRecorder;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('CommandRecorder', function () {
    it('dispatches nested command traces before restoring the parent trace', function () {
        config([
            'lookout.enabled' => true,
            'lookout.filters.ignore_commands' => [],
            'lookout.sampling.command' => 1.0,
        ]);

        Queue::fake();

        $buffer = new TraceBuffer;
        $parentContext = new ExecutionContext;
        $parentContext->type = 'request';
        $parentContext->name = 'GET /parents';
        $parentEvent = ChildEvent::make($parentContext->traceId, 'query')
            ->withLabel('select 1');

        $buffer->setContext($parentContext);
        $buffer->markSampled();
        $buffer->addEvent($parentEvent);

        $recorder = new CommandRecorder($buffer, new Sampler, new Filter);
        $input = new ArrayInput([]);
        $output = new NullOutput;

        $recorder->handleStarting(new CommandStarting('nested:command', $input, $output));

        $commandTraceId = $buffer->getContext()?->traceId;
        $buffer->addEvent(
            ChildEvent::make($commandTraceId, 'log')
                ->withLabel('inside nested command')
        );

        $recorder->handleFinished(new CommandFinished('nested:command', $input, $output, 0));

        Queue::assertPushed(IngestTraceJob::class, function (IngestTraceJob $job) {
            return $job->context['type'] === 'command'
                && $job->context['name'] === 'nested:command'
                && $job->events[0]['event_type'] === 'log'
                && $job->events[0]['labels'] === 'inside nested command';
        });

        expect($buffer->getContext())->toBe($parentContext);
        expect($buffer->getEvents())->toBe([$parentEvent]);
    });

    it('uses stacked command frames when the same command is nested', function () {
        config([
            'lookout.enabled' => true,
            'lookout.filters.ignore_commands' => [],
            'lookout.sampling.command' => 1.0,
        ]);

        Queue::fake();

        $buffer = new TraceBuffer;
        $requestContext = new ExecutionContext;
        $requestContext->type = 'request';
        $requestContext->name = 'POST /commands';
        $requestEvent = ChildEvent::make($requestContext->traceId, 'query')
            ->withLabel('select parent');

        $buffer->setContext($requestContext);
        $buffer->markSampled();
        $buffer->addEvent($requestEvent);

        $recorder = new CommandRecorder($buffer, new Sampler, new Filter);
        $input = new ArrayInput([]);
        $output = new NullOutput;

        $recorder->handleStarting(new CommandStarting('nested:same', $input, $output));
        $outerTraceId = $buffer->getContext()?->traceId;
        $buffer->addEvent(ChildEvent::make($outerTraceId, 'log')->withLabel('outer'));

        $recorder->handleStarting(new CommandStarting('nested:same', $input, $output));
        $innerTraceId = $buffer->getContext()?->traceId;
        $buffer->addEvent(ChildEvent::make($innerTraceId, 'log')->withLabel('inner'));

        $recorder->handleFinished(new CommandFinished('nested:same', $input, $output, 0));

        expect($buffer->getContext()?->traceId)->toBe($outerTraceId)
            ->and($buffer->getEvents()[0]->labels)->toBe('outer');

        $recorder->handleFinished(new CommandFinished('nested:same', $input, $output, 0));

        Queue::assertPushed(IngestTraceJob::class, 2);
        expect($buffer->getContext())->toBe($requestContext)
            ->and($buffer->getEvents())->toBe([$requestEvent]);
    });
});
