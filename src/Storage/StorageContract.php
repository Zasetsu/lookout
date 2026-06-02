<?php

namespace Zasetsu\Lookout\Storage;

interface StorageContract
{
    public function storeTrace(array $context): void;

    public function storeEvents(string $traceId, array $events): void;

    public function storeTraceBatch(array $context, array $events): void;

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array;

    public function getTrace(string $traceId): ?array;

    public function getEvents(string $traceId): array;

    public function getExceptionGroups(array $filters = [], int $limit = 25, int $offset = 0): array;

    public function getExceptionGroupStatusCounts(): array;

    public function getExceptionGroup(int $groupId): ?array;

    public function resolveExceptionGroup(int $groupId): bool;

    public function ignoreExceptionGroup(int $groupId): bool;

    public function getSlowQueries(int $threshold = 500, int $limit = 25): array;

    public function getSummary(string $since = '-24 hours'): array;

    public function prune(int $olderThanDays = 14): int;

    public function upsertExceptionGroup(string $fingerprint, array $data): void;

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void;

    public function getAuditLog(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function getHealth(): array;

    public function getPayloadBudgetStats(): array;

    public function upsertDeployMarker(array $attributes): array;

    public function getDeployMarkers(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function getLatestDeployMarker(?string $environment = null): ?array;

    public function getDeployMarkersBetween(string $from, string $to, ?string $environment = null): array;

    public function getEnabledThresholds(): array;

    public function getThresholdRules(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function getThresholdRule(int $ruleId): ?array;

    public function createThresholdRule(array $attributes): array;

    public function updateThresholdRule(int $ruleId, array $attributes): array;

    public function setThresholdRuleEnabled(int $ruleId, bool $enabled): array;

    public function deleteThresholdRule(int $ruleId): bool;

    public function getThresholdMetricValue(string $metric, int $windowMinutes): float;

    public function claimThresholdDispatchSlot(int $thresholdId, int $cooldownMinutes): bool;

    public function releaseThresholdDispatchSlot(int $thresholdId, ?string $previousLastTriggeredAt, ?string $expectedLastTriggeredAt = null): void;

    public function getEventsByType(string $eventType, array $filters = [], int $limit = 25, int $offset = 0): array;

    public function getCacheStats(string $since = '-24 hours'): array;

    public function getRequestVolumeByHour(string $since = '-24 hours'): array;

    public function getStatusDistribution(string $since = '-24 hours'): array;

    public function getTopExceptions(int $limit = 5): array;

    public function getEventsByHour(string $eventType, string $since = '-24 hours'): array;

    public function getQueryDurationBuckets(int $limit = 50): array;

    public function getTotalEventsCount(string $eventType): int;
}
