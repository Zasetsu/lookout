<?php

describe('Outgoing HTTP dashboard', function () {
    it('counts connection failures as outgoing HTTP errors', function () {
        $html = view('lookout::outgoing.index', [
            'title' => 'Outgoing HTTP',
            'total' => 2,
            'requests' => [
                [
                    'timestamp' => '2026-05-11 10:00:00',
                    'payload' => json_encode([
                        'method' => 'GET',
                        'url' => 'https://api.example.test/users',
                        'response_status' => 200,
                        'duration_ms' => 120,
                    ]),
                ],
                [
                    'timestamp' => '2026-05-11 10:00:01',
                    'payload' => json_encode([
                        'method' => 'GET',
                        'url' => 'https://downstream.example.test',
                        'failed' => true,
                        'error' => 'Connection refused',
                        'duration_ms' => 75,
                    ]),
                ],
            ],
        ])->render();

        expect($html)->toContain('Failures')
            ->and($html)->toContain('50% visible error rate')
            ->and($html)->toContain('<span class="k-val s-err">1</span>')
            ->and($html)->toContain('badge err')
            ->and($html)->toContain('failed')
            ->and($html)->not->toContain('badge ok">0</span>');
    });
});
