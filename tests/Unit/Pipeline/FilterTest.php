<?php

use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;

describe('Filter', function () {
    it('keeps normal requests', function () {
        config(['lookout.filters.ignore_routes' => ['lookout/*']]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'api/users';
        expect($filter->shouldKeepTrace($ctx))->toBeTrue();
    });

    it('filters ignored routes with fnmatch pattern', function () {
        config(['lookout.filters.ignore_routes' => ['lookout/*']]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'lookout/requests';
        expect($filter->shouldKeepTrace($ctx))->toBeFalse();
    });

    it('filters ignored routes with Str::is pattern', function () {
        config(['lookout.filters.ignore_routes' => ['telescope::*']]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'telescope::something';
        expect($filter->shouldKeepTrace($ctx))->toBeFalse();
    });

    it('filters ignored commands', function () {
        config(['lookout.filters.ignore_commands' => ['lookout:*']]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'command';
        $ctx->name = 'lookout:work';
        expect($filter->shouldKeepTrace($ctx))->toBeFalse();
    });

    it('keeps commands not in ignore list', function () {
        config(['lookout.filters.ignore_commands' => ['lookout:*']]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'command';
        $ctx->name = 'migrate';
        expect($filter->shouldKeepTrace($ctx))->toBeTrue();
    });

    it('keeps scheduled tasks regardless', function () {
        config([
            'lookout.filters.ignore_routes' => ['*'],
            'lookout.filters.ignore_commands' => ['*'],
        ]);
        $filter = new Filter;
        $ctx = new ExecutionContext;
        $ctx->type = 'scheduled_task';
        $ctx->name = 'cleanup';
        expect($filter->shouldKeepTrace($ctx))->toBeTrue();
    });

    it('always keeps events', function () {
        $filter = new Filter;
        $event = ChildEvent::make('id', 'query');
        expect($filter->shouldKeepEvent($event))->toBeTrue();
    });
});
