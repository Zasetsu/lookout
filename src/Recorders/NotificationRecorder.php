<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\TraceBuffer;

class NotificationRecorder implements RecorderContract
{
    public function __construct(
        private TraceBuffer $buffer,
    ) {}

    public function register(): void
    {
        Event::listen(NotificationSent::class, [$this, 'handleNotificationSent']);
    }

    public function handleNotificationSent(NotificationSent $event): void
    {
        $traceId = $this->buffer->getContext()?->traceId;
        if ($traceId === null) {
            return;
        }

        $childEvent = ChildEvent::make($traceId, 'notification')
            ->withLabel('Notification: '.get_class($event->notification).' via '.$event->channel)
            ->withPayload([
                'notification' => get_class($event->notification),
                'channel' => $event->channel,
                'notifiable' => get_class($event->notifiable).':'.(method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : 'unknown'),
            ]);

        $this->buffer->addEvent($childEvent);
    }
}
