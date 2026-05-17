<?php

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\CacheRecorder;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('CacheRecorder', function () {
    it('does not crash on nullable store names when no trace is active', function () {
        $buffer = new TraceBuffer;
        $recorder = new CacheRecorder($buffer, new Redactor);

        $recorder->handleHit(new CacheHit(null, 'cache-key', 'cached-value'));

        expect($buffer->getEvents())->toBe([]);
    });

    it('records nullable store names with an unknown fallback', function () {
        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /cached';

        $buffer->setContext($context);
        $buffer->markSampled();

        $recorder = new CacheRecorder($buffer, new Redactor);
        $recorder->handleWritten(new KeyWritten(null, 'cache-key', 'cached-value', 60));

        $events = $buffer->getEvents();

        expect($events)->toHaveCount(1);
        expect($events[0]->payload)->toMatchArray([
            'key' => 'cache-key',
            'store' => 'unknown',
            'operation' => 'cache_write',
            'ttl' => 60,
        ]);
    });

    it('redacts sensitive cache keys in labels and payloads', function () {
        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /cached';

        $buffer->setContext($context);
        $buffer->markSampled();

        $recorder = new CacheRecorder($buffer, new Redactor);
        $recorder->handleHit(new CacheHit('redis', 'api_key=super-secret-token', 'cached-value'));

        $event = $buffer->getEvents()[0];

        expect($event->payload['key'])->toBe('api_key=***');
        expect($event->labels)->toContain('api_key=***');
        expect($event->labels)->not->toContain('super-secret-token');
    });
});
