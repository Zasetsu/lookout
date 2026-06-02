<?php

namespace Zasetsu\Lookout\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    private const MIGRATION_FILE_NAMES = [
        'create_lookout_traces_table',
        'create_lookout_events_table',
        'create_lookout_exception_groups_table',
        'create_lookout_thresholds_table',
        'add_cooldown_minutes_to_lookout_thresholds_table',
        'create_lookout_deploy_markers_table',
        'create_lookout_audit_log_table',
    ];

    protected $signature = 'lookout:install';

    protected $description = 'Install the Lookout observability package';

    public function handle(): int
    {
        $driver = (string) config('lookout.storage.driver', 'sqlite');
        $connection = (string) config('lookout.storage.connection', 'lookout');

        $this->info("Storage driver: {$driver}");
        $this->info("Storage connection: {$connection}");

        if ($driver === 'sqlite') {
            $storagePath = config('lookout.storage.path', storage_path('lookout/lookout.sqlite'));

            $directory = dirname($storagePath);
            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created storage directory: {$directory}");
            }

            if (! File::exists($storagePath)) {
                File::put($storagePath, '');
                $this->info("Created SQLite database: {$storagePath}");
            }
        }

        $this->call('vendor:publish', [
            '--tag' => 'lookout-migrations',
        ]);

        $publishedMigrations = $this->publishedMigrationPaths();

        if ($publishedMigrations !== []) {
            $this->call('migrate', [
                '--database' => config('lookout.storage.connection', 'lookout'),
                '--path' => $publishedMigrations,
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $this->call('vendor:publish', [
            '--tag' => 'lookout-assets',
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('Lookout installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Configure dashboard auth in .env (choose one):');
        $this->line('     - LOOKOUT_BASIC_AUTH_USER=admin LOOKOUT_BASIC_AUTH_PASS=secret');
        $this->line('     - LOOKOUT_LOCALHOST_ONLY=true');
        $this->line('     - Define a viewLookout Gate in your AuthServiceProvider');
        $this->line('  2. Add LOOKOUT_DASHBOARD_ENABLED=true to your .env');
        $this->line('  3. Run: php artisan lookout:work');
        $this->line('  4. Visit: /lookout');

        return 0;
    }

    protected function publishedMigrationPaths(): array
    {
        $paths = [];

        foreach (self::MIGRATION_FILE_NAMES as $migrationFileName) {
            $matches = glob(database_path("migrations/*_{$migrationFileName}.php")) ?: [];
            sort($matches);

            if ($matches !== []) {
                $paths[] = $matches[0];
            }
        }

        return $paths;
    }
}
