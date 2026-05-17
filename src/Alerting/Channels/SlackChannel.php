<?php

namespace Zasetsu\Lookout\Alerting\Channels;

use Illuminate\Support\Facades\Http;

class SlackChannel implements ChannelContract
{
    public function send(object $threshold, array $context): void
    {
        $webhookUrl = config('lookout.alerting.channels.slack');

        if (empty($webhookUrl)) {
            return;
        }

        Http::timeout(5)
            ->retry(2, 100)
            ->post($webhookUrl, [
                'text' => "[Lookout Alert] {$threshold->name}",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Alert: {$threshold->name}*\nMetric: `{$threshold->metric}` | Condition: `{$threshold->condition} {$threshold->value}` | Window: `{$threshold->window_minutes}m`",
                        ],
                    ],
                ],
            ])
            ->throw();
    }
}
