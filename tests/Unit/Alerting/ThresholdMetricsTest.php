<?php

use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Storage\DatabaseStorage;

function insertMetricTrace(string $traceId, array $overrides = []): void
{
    DB::connection('lookout')->table('lookout_traces')->insert(array_merge([
        'trace_id' => $traceId,
        'type' => 'request',
        'name' => 'GET /metrics',
        'status' => 'ok',
        'timestamp' => now()->toDateTimeString(),
        'duration' => 100,
        'response_status' => 200,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ], $overrides));
}

function insertMetricEvent(string $traceId, array $payload, array $overrides = []): void
{
    DB::connection('lookout')->table('lookout_events')->insert(array_merge([
        'trace_id' => $traceId,
        'event_type' => 'outgoing_http',
        'timestamp' => now()->toDateTimeString(),
        'duration' => 50,
        'labels' => null,
        'payload' => json_encode($payload),
        'tags' => null,
        'created_at' => now()->toDateTimeString(),
    ], $overrides));
}

describe('Threshold metrics', function () {
    it('calculates request duration p95 in PHP for portable storage drivers', function () {
        foreach (range(1, 20) as $index) {
            insertMetricTrace("00000000-0000-0000-0000-0000000000{$index}", [
                'duration' => $index * 10,
            ]);
        }

        insertMetricTrace('00000000-0000-0000-0000-000000000099', [
            'duration' => 999,
            'timestamp' => now()->subMinutes(20)->toDateTimeString(),
        ]);

        expect(app(DatabaseStorage::class)->getThresholdMetricValue('request_duration_p95', 15))
            ->toBe(190.0);
    });

    it('calculates request error rate as a percentage of failing request traces', function () {
        insertMetricTrace('00000000-0000-0000-0000-000000000101', ['response_status' => 200]);
        insertMetricTrace('00000000-0000-0000-0000-000000000102', ['response_status' => 302]);
        insertMetricTrace('00000000-0000-0000-0000-000000000103', ['response_status' => 404]);
        insertMetricTrace('00000000-0000-0000-0000-000000000104', ['response_status' => 500]);
        insertMetricTrace('00000000-0000-0000-0000-000000000105', [
            'type' => 'command',
            'response_status' => 500,
        ]);

        expect(app(DatabaseStorage::class)->getThresholdMetricValue('error_rate', 15))
            ->toBe(50.0);
    });

    it('counts outgoing http failures from failed payload flags and response status codes', function () {
        insertMetricTrace('00000000-0000-0000-0000-000000000201');

        insertMetricEvent('00000000-0000-0000-0000-000000000201', ['failed' => true]);
        insertMetricEvent('00000000-0000-0000-0000-000000000201', ['response_status' => 500]);
        insertMetricEvent('00000000-0000-0000-0000-000000000201', ['failed' => false, 'response_status' => 200]);
        insertMetricEvent('00000000-0000-0000-0000-000000000201', ['failed' => true], [
            'timestamp' => now()->subMinutes(20)->toDateTimeString(),
        ]);
        insertMetricEvent('00000000-0000-0000-0000-000000000201', ['failed' => true], [
            'event_type' => 'query',
        ]);

        expect(app(DatabaseStorage::class)->getThresholdMetricValue('outgoing_http_failure_count', 15))
            ->toBe(2.0);
    });

    it('counts outgoing http failures without selecting every payload into PHP', function () {
        insertMetricTrace('00000000-0000-0000-0000-000000000301');

        foreach (range(1, 40) as $index) {
            insertMetricEvent('00000000-0000-0000-0000-000000000301', [
                'failed' => false,
                'response_status' => 200,
            ]);
        }

        insertMetricEvent('00000000-0000-0000-0000-000000000301', ['failed' => true]);
        insertMetricEvent('00000000-0000-0000-0000-000000000301', ['response_status' => 404]);
        insertMetricEvent('00000000-0000-0000-0000-000000000301', ['response_status' => 503]);

        DB::connection('lookout')->flushQueryLog();
        DB::connection('lookout')->enableQueryLog();

        expect(app(DatabaseStorage::class)->getThresholdMetricValue('outgoing_http_failure_count', 15))
            ->toBe(3.0);

        $queries = collect(DB::connection('lookout')->getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query));

        DB::connection('lookout')->disableQueryLog();

        expect($queries->contains(fn (string $query): bool => str_contains($query, 'select "payload"') && str_contains($query, 'from "lookout_events"')))
            ->toBeFalse();
    });
});
