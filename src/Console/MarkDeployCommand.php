<?php

namespace Zasetsu\Lookout\Console;

use Illuminate\Console\Command;
use Zasetsu\Lookout\Deployments\DeployMarkerPayload;
use Zasetsu\Lookout\Storage\StorageContract;

class MarkDeployCommand extends Command
{
    private const STRING_LIMITS = [
        'version' => 120,
        'environment' => 80,
        'commit' => 120,
        'branch' => 120,
        'author' => 120,
        'source' => 120,
        'compare_url' => 2048,
        'notes' => 5000,
    ];

    protected $signature = 'lookout:mark-deploy
        {--release= : Deploy version or release name}
        {--environment= : Deploy environment}
        {--commit= : Commit SHA}
        {--branch= : Source branch}
        {--author= : Deploy author}
        {--source= : Deploy source, such as github_actions}
        {--compare-url= : Compare URL}
        {--notes= : Notes}
        {--deployed-at= : Deploy timestamp}';

    protected $description = 'Create or update a Lookout deploy marker';

    public function handle(StorageContract $storage): int
    {
        $payload = DeployMarkerPayload::fromArray([
            'version' => $this->option('release'),
            'environment' => $this->option('environment'),
            'commit' => $this->option('commit'),
            'branch' => $this->option('branch'),
            'author' => $this->option('author'),
            'source' => $this->option('source'),
            'compare_url' => $this->option('compare-url'),
            'notes' => $this->option('notes'),
            'deployed_at' => $this->option('deployed-at') ?: now()->toDateTimeString(),
        ]);

        $error = $this->validatePayload($payload);

        if ($error !== null) {
            $this->error($error);

            return 1;
        }

        $payload['deployed_at'] = now()->parse((string) $payload['deployed_at'])->toDateTimeString();
        $result = $storage->upsertDeployMarker($payload);
        $marker = $result['marker'];

        $storage->logAudit('deploy_marker_created', null, null, [
            'marker_id' => (int) ($marker['id'] ?? 0),
            'version' => (string) ($marker['version'] ?? ''),
            'environment' => (string) ($marker['environment'] ?? ''),
            'commit' => $marker['commit'] ?? null,
            'source' => $marker['source'] ?? null,
            'created' => (bool) $result['created'],
            'via' => 'command',
        ]);

        $this->info(($result['created'] ? 'Created' : 'Updated').' deploy marker '.$marker['version'].' for '.$marker['environment'].'.');

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validatePayload(array $payload): ?string
    {
        if (($payload['version'] ?? '') === '') {
            return 'The --release option is required.';
        }

        if (($payload['environment'] ?? '') === '') {
            return 'The --environment option is required.';
        }

        foreach (self::STRING_LIMITS as $field => $limit) {
            $value = $payload[$field] ?? null;

            if (is_string($value) && strlen($value) > $limit) {
                return 'The --'.$this->optionNameForField($field).' option may not be greater than '.$limit.' characters.';
            }
        }

        if (($payload['compare_url'] ?? null) !== null && filter_var($payload['compare_url'], FILTER_VALIDATE_URL) === false) {
            return 'The --compare-url option must be a valid URL.';
        }

        try {
            now()->parse((string) $payload['deployed_at']);
        } catch (\Throwable) {
            return 'The --deployed-at option must be a valid date.';
        }

        return null;
    }

    protected function optionNameForField(string $field): string
    {
        return match ($field) {
            'version' => 'release',
            'compare_url' => 'compare-url',
            'deployed_at' => 'deployed-at',
            default => str_replace('_', '-', $field),
        };
    }
}
