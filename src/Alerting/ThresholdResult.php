<?php

namespace Zasetsu\Lookout\Alerting;

class ThresholdResult
{
    public function __construct(
        public readonly int $thresholdId,
        public readonly string $name,
        public readonly string $metric,
        public readonly string $condition,
        public readonly float $thresholdValue,
        public readonly float $currentValue,
        public readonly int $windowMinutes,
        public readonly int $cooldownMinutes,
        public readonly bool $triggered,
    ) {}

    /**
     * @return array{
     *     threshold_id: int,
     *     name: string,
     *     metric: string,
     *     condition: string,
     *     threshold_value: float,
     *     current_value: float,
     *     window_minutes: int,
     *     cooldown_minutes: int,
     *     triggered: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'threshold_id' => $this->thresholdId,
            'name' => $this->name,
            'metric' => $this->metric,
            'condition' => $this->condition,
            'threshold_value' => $this->thresholdValue,
            'current_value' => $this->currentValue,
            'window_minutes' => $this->windowMinutes,
            'cooldown_minutes' => $this->cooldownMinutes,
            'triggered' => $this->triggered,
        ];
    }
}
