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
});
