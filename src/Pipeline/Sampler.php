<?php

namespace Zasetsu\Lookout\Pipeline;

use Zasetsu\Lookout\Trace\ExecutionContext;

class Sampler
{
    public function shouldSample(ExecutionContext $context): bool
    {
        $rate = $this->getSampleRate($context);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $rate;
    }

    protected function getSampleRate(ExecutionContext $context): float
    {
        if ($context->type === 'request') {
            $manualRate = config('lookout.sampling.request');

            if ($manualRate !== null) {
                return (float) $manualRate;
            }

            if (config('lookout.sampling.auto', true)) {
                return app(AutoSampler::class)->getRate();
            }

            return 1.0;
        }

        return match ($context->type) {
            'command' => (float) config('lookout.sampling.command', 1.0),
            'scheduled_task' => (float) config('lookout.sampling.scheduled_task', 1.0),
            default => 1.0,
        };
    }

    public function recordSample(ExecutionContext $context): void
    {
        if ($context->type === 'request') {
            app(AutoSampler::class)->recordRequest();
        }
    }
}
