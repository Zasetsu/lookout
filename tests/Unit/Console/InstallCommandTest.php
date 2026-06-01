<?php

use Illuminate\Support\Facades\File;
use Zasetsu\Lookout\Console\InstallCommand;

class RecordingLookoutInstallCommand extends InstallCommand
{
    public array $calls = [];

    public array $infoLines = [];

    public function call($command, array $arguments = [])
    {
        $this->calls[] = [
            'command' => $command,
            'arguments' => $arguments,
        ];

        return 0;
    }

    public function info($string, $verbosity = null): void
    {
        $this->infoLines[] = $string;
    }

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
                '2026_01_01_000005_add_cooldown_minutes_to_lookout_thresholds_table.php',
                '2026_01_01_000006_create_lookout_deploy_markers_table.php',
                '2026_01_01_000007_create_lookout_audit_log_table.php',
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

    it('does not create sqlite files for host-managed database drivers', function () {
        $databasePath = sys_get_temp_dir().'/lookout-install-test-'.uniqid();
        $sqlitePath = $databasePath.'/lookout.sqlite';
        $migrationsPath = $databasePath.'/migrations';

        try {
            app()->useDatabasePath($databasePath);
            config([
                'lookout.storage.driver' => 'mysql',
                'lookout.storage.connection' => 'lookout_mysql',
                'lookout.storage.path' => $sqlitePath,
                'database.connections.lookout_mysql' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ]);
            File::ensureDirectoryExists($migrationsPath);

            File::put($migrationsPath.'/2026_01_01_000001_create_lookout_traces_table.php', '<?php');

            $command = new RecordingLookoutInstallCommand;
            $command->handle();

            $migrateCall = collect($command->calls)->firstWhere('command', 'migrate');

            expect(File::exists($sqlitePath))->toBeFalse()
                ->and($migrateCall['arguments']['--database'])->toBe('lookout_mysql')
                ->and(implode("\n", $command->infoLines))->toContain('Storage driver: mysql');
        } finally {
            File::deleteDirectory($databasePath);
        }
    });
});
