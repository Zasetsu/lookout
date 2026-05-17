<?php

namespace Zasetsu\Lookout\Alerting\Channels;

use Illuminate\Support\Facades\Mail;

class EmailChannel implements ChannelContract
{
    public function send(object $threshold, array $context): void
    {
        $to = config('lookout.alerting.channels.email');

        if (empty($to)) {
            return;
        }

        Mail::raw($this->buildMessage($threshold, $context), function ($message) use ($to, $threshold) {
            $message->to($to)
                ->subject("[Lookout Alert] {$threshold->name}");
        });
    }

    protected function buildMessage(object $threshold, array $context): string
    {
        return "Lookout Alert Triggered\n\n".
            "Name: {$threshold->name}\n".
            "Metric: {$threshold->metric}\n".
            "Condition: {$threshold->condition} {$threshold->value}\n".
            "Window: {$threshold->window_minutes} minutes\n\n".
            'Check your Lookout dashboard for details.';
    }
}
