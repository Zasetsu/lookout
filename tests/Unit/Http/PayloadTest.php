<?php

use Zasetsu\Lookout\Http\Support\Payload;

describe('Payload helper', function () {
    it('decodes JSON payload arrays and falls back safely for malformed input', function () {
        expect(Payload::decode('{"subject":"Welcome","to":["ops@example.test"]}'))->toBe([
            'subject' => 'Welcome',
            'to' => ['ops@example.test'],
        ])
            ->and(Payload::decode('{invalid json'))->toBe([])
            ->and(Payload::decode(null))->toBe([])
            ->and(Payload::decode('"scalar"'))->toBe([]);
    });

    it('normalizes scalar, list, boolean, and numeric payload fields safely', function () {
        $payload = [
            'subject' => 'Welcome',
            'to' => ['ops@example.test', 42, ['bad']],
            'failed' => true,
            'duration_ms' => '15.5',
            'nested' => ['bad'],
        ];

        expect(Payload::string($payload, 'subject', 'fallback'))->toBe('Welcome')
            ->and(Payload::string($payload, 'nested', 'fallback'))->toBe('fallback')
            ->and(Payload::stringList($payload, 'to'))->toBe(['ops@example.test', '42'])
            ->and(Payload::stringList(['to' => 'ops@example.test'], 'to'))->toBe(['ops@example.test'])
            ->and(Payload::bool($payload, 'failed'))->toBeTrue()
            ->and(Payload::number($payload, 'duration_ms'))->toBe(15.5)
            ->and(Payload::number($payload, 'nested', 0))->toBe(0.0);
    });
});
