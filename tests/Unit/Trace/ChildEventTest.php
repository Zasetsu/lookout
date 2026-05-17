<?php

use Zasetsu\Lookout\Trace\ChildEvent;

describe('ChildEvent', function () {
    it('creates with trace ID and event type', function () {
        $event = ChildEvent::make('test-trace-id', 'query');
        expect($event->traceId)->toBe('test-trace-id');
        expect($event->eventType)->toBe('query');
        expect($event->timestamp)->toBeFloat();
    });

    it('chains fluent setters', function () {
        $event = ChildEvent::make('id', 'query')
            ->withDuration(50)
            ->withLabel('SELECT * FROM users')
            ->withPayload(['sql' => 'SELECT *'])
            ->withTags(['slow']);

        expect($event->duration)->toBe(50);
        expect($event->labels)->toBe('SELECT * FROM users');
        expect($event->payload)->toBe(['sql' => 'SELECT *']);
        expect($event->tags)->toBe(['slow']);
    });

    it('converts to array', function () {
        $event = ChildEvent::make('id', 'cache')
            ->withDuration(10)
            ->withPayload(['key' => 'test']);

        $arr = $event->toArray();
        expect($arr)->toHaveKey('trace_id');
        expect($arr)->toHaveKey('event_type');
        expect($arr)->toHaveKey('timestamp');
        expect($arr)->toHaveKey('duration');
        expect($arr)->toHaveKey('payload');
        expect($arr['trace_id'])->toBe('id');
        expect($arr['event_type'])->toBe('cache');
        expect($arr['duration'])->toBe(10);
        expect($arr['payload'])->toBe(json_encode(['key' => 'test']));
        expect($arr['tags'])->toBeNull();
    });

    it('serializes tags as JSON when non-empty', function () {
        $event = ChildEvent::make('id', 'query')
            ->withTags(['slow', 'expensive']);

        $arr = $event->toArray();
        expect($arr['tags'])->toBe(json_encode(['slow', 'expensive']));
    });

    it('has null labels and duration by default', function () {
        $event = ChildEvent::make('id', 'log');
        expect($event->duration)->toBeNull();
        expect($event->labels)->toBeNull();
        expect($event->payload)->toBe([]);
        expect($event->tags)->toBe([]);
    });
});
