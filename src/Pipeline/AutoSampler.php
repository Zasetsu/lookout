<?php

namespace Zasetsu\Lookout\Pipeline;

use Illuminate\Support\Facades\Cache;

class AutoSampler
{
    public function recordRequest(): void
    {
        $key = 'lookout:autosampler:'.now()->format('YmdHi');
        $store = Cache::store();

        $store->add($key, 0, now()->addMinutes(6));
        $store->increment($key);
    }

    public function getRequestsPerSecond(): float
    {
        $total = 0;
        for ($i = 0; $i < 5; $i++) {
            $key = 'lookout:autosampler:'.now()->subMinutes($i)->format('YmdHi');
            $total += (int) Cache::store()->get($key, 0);
        }

        return $total / 300;
    }

    public function getRate(): float
    {
        $rps = $this->getRequestsPerSecond();

        return match (true) {
            $rps < 10 => 1.0,
            $rps < 100 => 0.5,
            $rps < 500 => 0.1,
            default => 0.05,
        };
    }
}
