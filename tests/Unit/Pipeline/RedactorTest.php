<?php

use Zasetsu\Lookout\Pipeline\Redactor;

describe('Redactor', function () {
    it('redacts sensitive array keys', function () {
        config(['lookout.redaction.patterns' => ['password', 'token'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $data = ['password' => 'secret123', 'name' => 'John'];
        $result = $redactor->redact($data);
        expect($result['password'])->toBe('***');
        expect($result['name'])->toBe('John');
    });

    it('redacts nested arrays', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $data = ['user' => ['password' => 'secret', 'email' => 'test@test.com']];
        $result = $redactor->redact($data);
        expect($result['user']['password'])->toBe('***');
        expect($result['user']['email'])->toBe('test@test.com');
    });

    it('redacts strings with patterns', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $result = $redactor->redact('password=mysecret123');
        expect($result)->toContain('***');
    });

    it('redacts camelCase sensitive keys in strings', function () {
        config([
            'lookout.redaction.patterns' => ['api_key', 'credit_card'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redact('apiKey=key-secret creditCard=4111111111111111');

        expect($result)->toContain('apiKey=***')
            ->and($result)->toContain('creditCard=***')
            ->and($result)->not->toContain('key-secret')
            ->and($result)->not->toContain('4111111111111111');
    });

    it('redacts bearer and embedded access tokens in free form strings', function () {
        config([
            'lookout.redaction.patterns' => ['authorization', 'token'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redact('Authorization: Bearer abc.def.ghi callback=https://example.com?access_token=secret-token&state=public');

        expect($result)->toContain('Authorization: Bearer ***')
            ->and($result)->toContain('access_token=***')
            ->and($result)->toContain('state=public')
            ->and($result)->not->toContain('abc.def.ghi')
            ->and($result)->not->toContain('secret-token');
    });

    it('redacts bracketed sensitive query keys in URLs', function () {
        config([
            'lookout.redaction.patterns' => ['api_key', 'token'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redactUrl('https://api.example.test?api_key[]=key-secret&token[access]=token-secret&state=public');

        expect($result)->toContain('api_key[]=***')
            ->and($result)->toContain('token[access]=***')
            ->and($result)->toContain('state=public')
            ->and($result)->not->toContain('key-secret')
            ->and($result)->not->toContain('token-secret');
    });

    it('redacts encoded nested sensitive values in URL query parameters', function () {
        config([
            'lookout.redaction.patterns' => ['authorization', 'api_key', 'token'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redactUrl('https://api.example.test/callback?redirect=https%3A%2F%2Fclient.test%2Fcb%3Faccess_token%3Dnested-secret%26state%3Dok&headers=Authorization%3A%20Bearer%20encoded-secret&meta=%7B%22apiKey%22%3A%22json-secret%22%7D');

        expect($result)->not->toContain('nested-secret')
            ->and($result)->not->toContain('encoded-secret')
            ->and($result)->not->toContain('json-secret')
            ->and(rawurldecode($result))->toContain('access_token=***')
            ->and(rawurldecode($result))->toContain('Authorization: Bearer ***')
            ->and(rawurldecode($result))->toContain('"apiKey":"***"');
    });

    it('redacts standalone auth schemes and token words in free form strings', function () {
        config([
            'lookout.redaction.patterns' => ['authorization', 'token'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redact('Bearer abc.def.ghi token secret-token Authorization=Basic base64-secret public=visible');

        expect($result)->toContain('Bearer ***')
            ->and($result)->toContain('token ***')
            ->and($result)->toContain('Authorization=Basic ***')
            ->and($result)->toContain('public=visible')
            ->and(str_contains($result, 'abc.def.ghi'))->toBeFalse()
            ->and(str_contains($result, 'secret-token'))->toBeFalse()
            ->and(str_contains($result, 'base64-secret'))->toBeFalse();
    });

    it('redacts JSON strings', function () {
        config(['lookout.redaction.patterns' => ['token'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $result = $redactor->redactJson('{"token":"abc123","name":"test"}');
        $decoded = json_decode($result, true);
        expect($decoded['token'])->toBe('***');
        expect($decoded['name'])->toBe('test');
    });

    it('returns non-string non-array data unchanged', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        expect($redactor->redact(42))->toBe(42);
        expect($redactor->redact(null))->toBeNull();
        expect($redactor->redact(true))->toBeTrue();
    });

    it('merges custom patterns with defaults', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => ['api_key']]);
        $redactor = new Redactor;
        $data = ['password' => 'secret', 'api_key' => 'key123', 'name' => 'John'];
        $result = $redactor->redact($data);
        expect($result['password'])->toBe('***');
        expect($result['api_key'])->toBe('***');
        expect($result['name'])->toBe('John');
    });

    it('redacts case-insensitively', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $data = ['Password' => 'secret', 'PASSWORD' => 'also-secret'];
        $result = $redactor->redact($data);
        expect($result['Password'])->toBe('***');
        expect($result['PASSWORD'])->toBe('***');
    });

    it('redacts camelCase keys that match separator based patterns', function () {
        config([
            'lookout.redaction.patterns' => ['api_key', 'credit_card', 'xsrf-token'],
            'lookout.redaction.custom' => [],
        ]);

        $redactor = new Redactor;
        $result = $redactor->redact([
            'apiKey' => 'key-secret',
            'creditCard' => '4111111111111111',
            'xsrfToken' => 'csrf-secret',
            'publicValue' => 'visible',
        ]);

        expect($result['apiKey'])->toBe('***')
            ->and($result['creditCard'])->toBe('***')
            ->and($result['xsrfToken'])->toBe('***')
            ->and($result['publicValue'])->toBe('visible');
    });

    it('handles invalid JSON in redactJson', function () {
        config(['lookout.redaction.patterns' => ['password'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $result = $redactor->redactJson('{invalid json password=secret}');
        expect($result)->toBeString();
    });

    it('preserves non-sensitive values in nested structures', function () {
        config(['lookout.redaction.patterns' => ['secret'], 'lookout.redaction.custom' => []]);
        $redactor = new Redactor;
        $data = [
            'config' => [
                'secret_key' => 'hidden',
                'public_value' => 'visible',
            ],
            'metadata' => 'clean',
        ];
        $result = $redactor->redact($data);
        expect($result['config']['secret_key'])->toBe('***');
        expect($result['config']['public_value'])->toBe('visible');
        expect($result['metadata'])->toBe('clean');
    });
});
