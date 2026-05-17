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

        expect($html)->toContain('Error Rate')
            ->and($html)->toMatch('/Error Rate<\/div>\s*<div class="stat-value text-red-600">50(?:\.0)?<span/s')
            ->and($html)->toMatch('/Errors<\/div>\s*<div class="stat-value text-red-600">1<\/div>/s')
            ->and($html)->toContain('badge-red')
            ->and($html)->toContain('Failed')
            ->and($html)->not->toContain('badge-green">0</span>');
    });
});
