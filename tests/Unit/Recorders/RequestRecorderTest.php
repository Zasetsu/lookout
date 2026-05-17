<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Recorders\RequestRecorder;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('RequestRecorder', function () {
    it('truncates large request bodies before storing them in the trace context', function () {
        config([
            'lookout.ingestion.max_request_body_bytes' => 120,
            'lookout.sampling.auto' => false,
            'lookout.sampling.request' => 1.0,
        ]);

        $buffer = new TraceBuffer;
        $recorder = new RequestRecorder($buffer, new Sampler, new Filter, new Redactor);

        $request = Request::create('/checkout', 'POST', [
            'payload' => str_repeat('a', 300),
            'password' => 'secret',
        ]);

        $recorder->handleRequest(new RequestHandled($request, new Response('ok', 200)));

        $body = $buffer->getContext()?->requestBody;

        expect($body)->toBeArray()
            ->and($body['_lookout_truncated'])->toBeTrue()
            ->and($body['_lookout_original_size'])->toBeGreaterThan(120)
            ->and(strlen(json_encode($body) ?: ''))->toBeLessThanOrEqual(240)
            ->and(json_encode($body))->not->toContain(str_repeat('a', 300))
            ->and(json_encode($body))->not->toContain('secret');
    });

    it('refreshes the authenticated user id after downstream middleware runs', function () {
        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /account';
        $context->userId = null;
        $buffer->setContext($context);
        $buffer->markSampled();

        $request = Request::create('/account', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public function getAuthIdentifier(): int
            {
                return 42;
            }
        });

        $recorder = new RequestRecorder($buffer, new Sampler, new Filter, new Redactor);
        $recorder->handleRequest(new RequestHandled($request, new Response('ok', 200)));

        expect($buffer->getContext()?->userId)->toBe('42');
    });
});
