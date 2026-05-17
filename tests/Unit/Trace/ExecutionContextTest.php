<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Zasetsu\Lookout\Trace\ExecutionContext;

describe('ExecutionContext', function () {
    it('creates with a UUID trace ID', function () {
        $ctx = new ExecutionContext;
        expect($ctx->traceId)->toBeString();
        expect(strlen($ctx->traceId))->toBe(36);
    });

    it('sets default status to success', function () {
        $ctx = new ExecutionContext;
        expect($ctx->status)->toBe('success');
    });

    it('marks as failed', function () {
        $ctx = new ExecutionContext;
        $ctx->markFailed();
        expect($ctx->status)->toBe('error');
    });

    it('calculates duration on finish', function () {
        $ctx = new ExecutionContext;
        $ctx->timestamp = microtime(true) - 0.1;
        $ctx->finish();
        expect($ctx->duration)->toBeInt();
        expect($ctx->duration)->toBeGreaterThanOrEqual(0);
    });

    it('sets memory peak on finish', function () {
        $ctx = new ExecutionContext;
        $ctx->finish();
        expect($ctx->memoryPeak)->toBeInt();
        expect($ctx->memoryPeak)->toBeGreaterThan(0);
    });

    it('converts to array', function () {
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = '/api/test';
        $ctx->finish();
        $arr = $ctx->toArray();
        expect($arr)->toHaveKey('trace_id');
        expect($arr)->toHaveKey('type');
        expect($arr)->toHaveKey('name');
        expect($arr)->toHaveKey('status');
        expect($arr)->toHaveKey('timestamp');
        expect($arr)->toHaveKey('duration');
        expect($arr)->toHaveKey('memory_peak');
        expect($arr)->toHaveKey('environment');
        expect($arr['type'])->toBe('request');
        expect($arr['name'])->toBe('/api/test');
        expect($arr['status'])->toBe('success');
        expect($arr['environment'])->toBe(app()->environment());
    });

    it('serializes optional fields as null when unset', function () {
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'test';
        $ctx->finish();
        $arr = $ctx->toArray();
        expect($arr['user_id'])->toBeNull();
        expect($arr['ip'])->toBeNull();
        expect($arr['method'])->toBeNull();
        expect($arr['url'])->toBeNull();
        expect($arr['request_headers'])->toBeNull();
        expect($arr['request_body'])->toBeNull();
        expect($arr['response_status'])->toBeNull();
        expect($arr['response_headers'])->toBeNull();
        expect($arr['tags'])->toBeNull();
    });

    it('serializes tags as JSON when non-empty', function () {
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        $ctx->name = 'test';
        $ctx->tags = ['slow', 'db-heavy'];
        $ctx->finish();
        $arr = $ctx->toArray();
        expect($arr['tags'])->toBe(json_encode(['slow', 'db-heavy']));
    });

    it('persists the route pattern instead of concrete secret request URLs', function () {
        $request = Request::create('https://app.test/password/reset/super-secret-token', 'GET');
        $request->setRouteResolver(fn () => new Route(['GET'], 'password/reset/{token}', fn () => null));

        $context = ExecutionContext::forRequest($request);

        expect($context->url)->toBe('https://app.test/password/reset/{token}')
            ->and($context->url)->not->toContain('super-secret-token')
            ->and($context->name)->toBe('password/reset/{token}');
    });

    it('redacts request headers when creating a request context', function () {
        $request = Request::create('/account', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi',
            'HTTP_COOKIE' => 'laravel_session=session-secret; XSRF-TOKEN=csrf-secret',
        ]);

        $context = ExecutionContext::forRequest($request);
        $headers = json_encode($context->requestHeaders);

        expect($headers)->toContain('***')
            ->and($headers)->not->toContain('abc.def.ghi')
            ->and($headers)->not->toContain('session-secret')
            ->and($headers)->not->toContain('csrf-secret');
    });
});
