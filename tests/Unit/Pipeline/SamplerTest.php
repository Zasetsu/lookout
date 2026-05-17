<?php

use Illuminate\Support\Facades\Cache;
use Zasetsu\Lookout\Pipeline\AutoSampler;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Trace\ExecutionContext;

describe('Sampler', function () {
    it('always samples at rate 1.0', function () {
        config(['lookout.sampling.request' => 1.0]);
        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        expect($sampler->shouldSample($ctx))->toBeTrue();
    });

    it('never samples at rate 0.0', function () {
        config(['lookout.sampling.request' => 0.0]);
        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        expect($sampler->shouldSample($ctx))->toBeFalse();
    });

    it('uses command sample rate when request rate is null', function () {
        config([
            'lookout.sampling.request' => null,
            'lookout.sampling.command' => 1.0,
            'lookout.sampling.auto' => false,
        ]);
        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'command';
        expect($sampler->shouldSample($ctx))->toBeTrue();
    });

    it('uses scheduled_task sample rate', function () {
        config([
            'lookout.sampling.request' => null,
            'lookout.sampling.scheduled_task' => 0.0,
            'lookout.sampling.auto' => false,
        ]);
        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'scheduled_task';
        expect($sampler->shouldSample($ctx))->toBeFalse();
    });

    it('defaults to 1.0 for unknown types', function () {
        config([
            'lookout.sampling.request' => null,
            'lookout.sampling.auto' => false,
        ]);
        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'unknown_type';
        expect($sampler->shouldSample($ctx))->toBeTrue();
    });

    it('uses auto sampler for requests when auto is enabled', function () {
        config([
            'lookout.sampling.request' => null,
            'lookout.sampling.auto' => true,
        ]);
        $autoSampler = new AutoSampler;
        app()->instance(AutoSampler::class, $autoSampler);

        $sampler = new Sampler;
        $ctx = new ExecutionContext;
        $ctx->type = 'request';
        expect($sampler->shouldSample($ctx))->toBeTrue();
    });
});

describe('AutoSampler', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('returns 1.0 for low traffic', function () {
        $sampler = new AutoSampler;
        expect($sampler->getRate())->toBe(1.0);
    });

    it('returns 0.5 for medium traffic', function () {
        $key = 'lookout:autosampler:'.now()->format('YmdHi');
        Cache::put($key, 15000, now()->addMinutes(2));

        $sampler = new AutoSampler;
        expect($sampler->getRate())->toBe(0.5);
    });

    it('returns 0.1 for higher traffic', function () {
        $key = 'lookout:autosampler:'.now()->format('YmdHi');
        Cache::put($key, 75000, now()->addMinutes(2));

        $sampler = new AutoSampler;
        expect($sampler->getRate())->toBe(0.1);
    });

    it('returns 0.05 for very high traffic', function () {
        $key = 'lookout:autosampler:'.now()->format('YmdHi');
        Cache::put($key, 180000, now()->addMinutes(2));

        $sampler = new AutoSampler;
        expect($sampler->getRate())->toBe(0.05);
    });

    it('records requests and affects rate', function () {
        $sampler = new AutoSampler;
        expect($sampler->getRate())->toBe(1.0);

        $key = 'lookout:autosampler:'.now()->format('YmdHi');
        Cache::put($key, 15000, now()->addMinutes(2));

        expect($sampler->getRate())->toBe(0.5);
    });
});
