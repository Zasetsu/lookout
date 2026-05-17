<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Log\LogManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class LogRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        $logger = app(LogManager::class)->getLogger();

        if (! $logger instanceof Logger) {
            return;
        }

        $handler = new class($this->buffer, $this->redactor) extends AbstractProcessingHandler
        {
            public function __construct(
                private TraceBuffer $buffer,
                private Redactor $redactor,
            ) {
                parent::__construct(Level::Debug, true);
            }

            protected function write(LogRecord $record): void
            {
                $traceId = $this->buffer->getContext()?->traceId;
                if ($traceId === null) {
                    return;
                }

                $message = $this->redactor->redact($record->message);

                $childEvent = ChildEvent::make($traceId, 'log')
                    ->withLabel("[{$record->level->getName()}] ".$message)
                    ->withPayload([
                        'level' => $record->level->getName(),
                        'message' => $message,
                        'context' => $this->redactor->redact($record->context),
                        'channel' => $record->channel,
                    ]);

                $this->buffer->addEvent($childEvent);
            }
        };

        $logger->pushHandler($handler);
    }
}
