<?php

use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('TraceBuffer', function () {
    it('starts empty', function () {
        $buffer = new TraceBuffer;
        expect($buffer->getContext())->toBeNull();
        expect($buffer->getEvents())->toBeEmpty();
        expect($buffer->isSampled())->toBeFalse();
    });

    it('sets context and events', function () {
        $buffer = new TraceBuffer;
        $ctx = new ExecutionContext;
        $buffer->setContext($ctx);
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'query'));
        $buffer->markSampled();

        expect($buffer->getContext())->toBe($ctx);
        expect($buffer->getEvents())->toHaveCount(1);
        expect($buffer->isSampled())->toBeTrue();
    });

    it('shouldCollect returns true when enabled, sampled and has context', function () {
        config(['lookout.enabled' => true]);
        $buffer = new TraceBuffer;
        $buffer->setContext(new ExecutionContext);
        $buffer->markSampled();
        expect($buffer->shouldCollect())->toBeTrue();
    });

    it('shouldCollect returns false when not sampled', function () {
        config(['lookout.enabled' => true]);
        $buffer = new TraceBuffer;
        $buffer->setContext(new ExecutionContext);
        expect($buffer->shouldCollect())->toBeFalse();
    });

    it('shouldCollect returns false when no context', function () {
        config(['lookout.enabled' => true]);
        $buffer = new TraceBuffer;
        $buffer->markSampled();
        expect($buffer->shouldCollect())->toBeFalse();
    });

    it('shouldCollect returns false when disabled', function () {
        config(['lookout.enabled' => false]);
        $buffer = new TraceBuffer;
        $buffer->setContext(new ExecutionContext);
        $buffer->markSampled();
        expect($buffer->shouldCollect())->toBeFalse();
    });

    it('flushes and clears', function () {
        $buffer = new TraceBuffer;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'test';
        $buffer->setContext($ctx);
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'query'));
        $buffer->markSampled();

        $data = $buffer->flush();
        expect($data['context'])->not->toBeNull();
        expect($data['context'])->toHaveKey('trace_id');
        expect($data['events'])->toHaveCount(1);
        expect($data['events'][0])->toHaveKey('trace_id');
        expect($buffer->getContext())->toBeNull();
        expect($buffer->getEvents())->toBeEmpty();
        expect($buffer->isSampled())->toBeFalse();
    });

    it('detects exceptions in events', function () {
        $buffer = new TraceBuffer;
        $ctx = new ExecutionContext;
        $buffer->setContext($ctx);
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'query'));
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'exception'));

        expect($buffer->hasException())->toBeTrue();
    });

    it('returns false when no exception events', function () {
        $buffer = new TraceBuffer;
        $ctx = new ExecutionContext;
        $buffer->setContext($ctx);
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'query'));
        $buffer->addEvent(ChildEvent::make($ctx->traceId, 'cache'));

        expect($buffer->hasException())->toBeFalse();
    });

    it('clear resets sampled flag', function () {
        $buffer = new TraceBuffer;
        $buffer->setContext(new ExecutionContext);
        $buffer->markSampled();
        expect($buffer->isSampled())->toBeTrue();

        $buffer->clear();
        expect($buffer->isSampled())->toBeFalse();
    });

    it('flush returns null context when no context set', function () {
        $buffer = new TraceBuffer;
        $data = $buffer->flush();
        expect($data['context'])->toBeNull();
        expect($data['events'])->toBeEmpty();
    });
});
