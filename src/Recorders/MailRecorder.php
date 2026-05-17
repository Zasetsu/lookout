<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class MailRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
        private Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(MessageSent::class, [$this, 'handleMessageSent']);
    }

    public function handleMessageSent(MessageSent $event): void
    {
        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $message = $event->message;
        $subject = $this->redactor->redact($message->getSubject() ?? 'No Subject');

        $childEvent = ChildEvent::make($traceId, 'mail')
            ->withLabel('Mail: '.$subject)
            ->withPayload([
                'subject' => $subject,
                'to' => $this->redactor->redact(array_map(fn ($addr) => $addr->toString(), $message->getTo())),
                'from' => $this->redactor->redact(array_map(fn ($addr) => $addr->toString(), $message->getFrom())),
                'cc' => $this->redactor->redact(array_map(fn ($addr) => $addr->toString(), $message->getCc())),
            ]);

        $this->buffer->addEvent($childEvent);
    }
}
