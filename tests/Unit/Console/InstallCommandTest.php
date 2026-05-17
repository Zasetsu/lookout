<?php

use Illuminate\Support\Facades\File;
use Zasetsu\Lookout\Console\InstallCommand;

class RecordingLookoutInstallCommand extends InstallCommand
{
    public array $calls = [];

    public function call($command, array $arguments = [])
    {
        $this->calls[] = [
            'command' => $command,
            'arguments' => $arguments,
        ];

        return self::SUCCESS;
    }

    public function info($string, $verbosity = null): void {}

    public function line($string, $style = null, $verbosity = null): void {}

    public function newLine($count = 1)
    {
        return $this;
    }
}

describe('InstallCommand', function () {
    it('migrates published lookout migrations instead of vendor migration files', function () {
        $databasePath = sys_get_temp_dir().'/lookout-install-test-'.uniqid();
        $migrationsPath = $databasePath.'/migrations';

        try {
            app()->useDatabasePath($databasePath);
            config(['lookout.storage.path' => $databasePath.'/lookout.sqlite']);
            File::ensureDirectoryExists($migrationsPath);

            foreach ([
                '2026_01_01_000001_create_lookout_traces_table.php',
                '2026_01_01_000002_create_lookout_events_table.php',
                '2026_01_01_000003_create_lookout_exception_groups_table.php',
                '2026_01_01_000004_create_lookout_thresholds_table.php',
                '2026_01_01_000005_create_lookout_audit_log_table.php',
            ] as $fileName) {
                File::put($migrationsPath.'/'.$fileName, '<?php');
            }

            $command = new RecordingLookoutInstallCommand;
            $command->handle();

            $migrationPublishCall = collect($command->calls)->first(
                fn (array $call): bool => $call['command'] === 'vendor:publish'
                    && ($call['arguments']['--tag'] ?? null) === 'lookout-migrations'
            );
            $migrateCall = collect($command->calls)->firstWhere('command', 'migrate');

            expect($migrationPublishCall)->not->toBeNull()
                ->and($migrateCall)->not->toBeNull()
                ->and($migrateCall['arguments']['--path'])->toBeArray()
                ->and($migrateCall['arguments']['--realpath'])->toBeTrue()
                ->and(implode(' ', $migrateCall['arguments']['--path']))->toContain($migrationsPath)
                ->and(implode(' ', $migrateCall['arguments']['--path']))->not->toContain('vendor/zasetsu/lookout/database/migrations');
        } finally {
            File::deleteDirectory($databasePath);
        }
    });
});
