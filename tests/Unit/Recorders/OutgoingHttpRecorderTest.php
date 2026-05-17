<?php

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\OutgoingHttpRecorder;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

function outgoingRecorderWithBuffer(): array
{
    $buffer = new TraceBuffer;
    $context = new ExecutionContext;
    $context->type = 'request';
    $context->name = 'GET /';
    $buffer->setContext($context);
    $buffer->markSampled();

    return [new OutgoingHttpRecorder($buffer, new Redactor), $buffer];
}

it('redacts outgoing URL query and path secrets before recording responses', function () {
    [$recorder, $buffer] = outgoingRecorderWithBuffer();

    $request = new ClientRequest(new PsrRequest(
        'POST',
        'https://api.example.test/password/reset/super-secret-token?access_token=query-secret&api_key[]=array-secret'
    ));
    $response = new ClientResponse(new PsrResponse(200));

    $recorder->handleResponseReceived(new ResponseReceived($request, $response));

    $event = $buffer->getEvents()[0];
    $url = (string) $event->payload['url'];
    $labels = $event->labels ?? '';

    expect(str_contains($url, 'super-secret-token'))->toBeFalse()
        ->and(str_contains($url, 'query-secret'))->toBeFalse()
        ->and(str_contains($url, 'array-secret'))->toBeFalse()
        ->and($url)->toContain('/password/reset/***')
        ->and($url)->toContain('access_token=***')
        ->and($url)->toContain('api_key%5B%5D=***')
        ->and(str_contains($labels, 'super-secret-token'))->toBeFalse()
        ->and(str_contains($labels, 'query-secret'))->toBeFalse()
        ->and(str_contains($labels, 'array-secret'))->toBeFalse();
});

it('redacts outgoing URL path secrets before recording connection failures', function () {
    [$recorder, $buffer] = outgoingRecorderWithBuffer();

    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.signature';
    $request = new ClientRequest(new PsrRequest('GET', "https://api.example.test/invite/{$jwt}"));

    $recorder->handleConnectionFailed(new ConnectionFailed($request, new ConnectionException('connect failed')));

    $event = $buffer->getEvents()[0];
    $url = (string) $event->payload['url'];
    $labels = $event->labels ?? '';

    expect(str_contains($url, $jwt))->toBeFalse()
        ->and($url)->toContain('/invite/***')
        ->and(str_contains($labels, $jwt))->toBeFalse();
});
