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
                ->subject('[Lookout Alert] '.$this->safeText($threshold->name));
        });
    }

    protected function buildMessage(object $threshold, array $context): string
    {
        $title = ($context['test'] ?? false) === true
            ? 'Lookout Alert Test'
            : 'Lookout Alert Triggered';

        $message = "{$title}\n\n".
            "Name: {$this->safeText($threshold->name)}\n".
            "Metric: {$this->safeText($threshold->metric)}\n".
            "Condition: {$this->safeText($threshold->condition)} {$this->safeText($threshold->value)}\n".
            "Window: {$this->safeText($threshold->window_minutes)} minutes\n";

        foreach ($this->contextLines($context) as $line) {
            $message .= $line."\n";
        }

        return $message."\n".
            'Check your Lookout dashboard for details.';
    }

    protected function safeText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $value) ?? '');
        }

        return '[complex value]';
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

            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$this->safeText($value);
        }

        return $lines;
    }
}
