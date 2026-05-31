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

        $title = ($context['test'] ?? false) === true
            ? 'Lookout Alert Test'
            : 'Lookout Alert';
        $details = [
            "*{$this->escapeMrkdwn($title)}: {$this->escapeMrkdwn($threshold->name)}*",
            "Metric: `{$this->escapeMrkdwn($threshold->metric)}`",
            "Condition: `{$this->escapeMrkdwn($threshold->condition)} {$this->escapeMrkdwn($threshold->value)}`",
            "Window: `{$this->escapeMrkdwn($threshold->window_minutes)}m`",
        ];

        foreach ($this->contextLines($context) as $line) {
            $details[] = $line;
        }

        Http::timeout(5)
            ->retry(2, 100)
            ->post($webhookUrl, [
                'text' => "[{$title}] {$this->plainText($threshold->name)}",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => implode("\n", $details),
                        ],
                    ],
                ],
            ])
            ->throw();
    }

    protected function plainText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $value) ?? '');
        }

        return '[complex value]';
    }

    protected function escapeMrkdwn(mixed $value): string
    {
        return str_replace(['&', '<', '>', '`'], ['&amp;', '&lt;', '&gt;', "'"], $this->plainText($value));
    }

    /**
     * @return array<int, string>
     */
    protected function contextLines(array $context): array
    {
        $lines = [];

        foreach ($context as $key => $value) {
            if (in_array($key, ['threshold_id', 'name', 'metric', 'condition', 'value', 'threshold_value', 'window_minutes', 'cooldown_minutes', 'deliveries'], true)) {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', (string) $key));
            $lines[] = "{$this->escapeMrkdwn($label)}: `{$this->escapeMrkdwn($value)}`";
        }

        return $lines;
    }
}
