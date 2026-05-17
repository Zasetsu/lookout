<?php

namespace Zasetsu\Lookout\Alerting\Channels;

use Illuminate\Support\Facades\Http;

class WebhookChannel implements ChannelContract
{
    public function send(object $threshold, array $context): void
    {
        $url = config('lookout.alerting.channels.webhook');

        if (empty($url)) {
            return;
        }

        $payload = json_encode($context);
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        Http::timeout(5)
            ->retry(2, 100)
            ->withHeaders([
                'X-Lookout-Signature' => $signature,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $context)
            ->throw();
    }
}
