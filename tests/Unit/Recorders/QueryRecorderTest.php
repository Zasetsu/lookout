<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\QueryRecorder;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('QueryRecorder', function () {
    it('skips internal Lookout storage queries', function () {
        config(['lookout.storage.connection' => 'lookout']);

        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /fails';

        $buffer->setContext($context);
        $buffer->markSampled();

        $recorder = new QueryRecorder($buffer, new Redactor);
        $recorder->handleQuery(new QueryExecuted(
            'insert into lookout_exception_groups (fingerprint) values (?)',
            ['abc'],
            12.5,
            DB::connection('lookout')
        ));

        expect($buffer->getEvents())->toBe([]);
    });

    it('records application queries from non-Lookout connections', function () {
        config(['lookout.storage.connection' => 'lookout']);

        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /users';

        $buffer->setContext($context);
        $buffer->markSampled();

        $recorder = new QueryRecorder($buffer, new Redactor);
        $recorder->handleQuery(new QueryExecuted(
            'select * from users where id = ?',
            [1],
            5.0,
            DB::connection()
        ));

        expect($buffer->getEvents())->toHaveCount(1);
        expect($buffer->getEvents()[0]->payload['connection'])->toBe(DB::connection()->getName());
    });
});
