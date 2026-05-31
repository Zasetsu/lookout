<?php

namespace Zasetsu\Lookout\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Zasetsu\Lookout\LookoutServiceProvider;

class TestCase extends BaseTestCase
{
    private const LOOKOUT_MIGRATIONS = [
        'create_lookout_traces_table.php',
        'create_lookout_events_table.php',
        'create_lookout_exception_groups_table.php',
        'create_lookout_thresholds_table.php',
        'create_lookout_audit_log_table.php',
    ];

    private const LOOKOUT_TABLES_REVERSE = [
        'lookout_events',
        'lookout_exception_groups',
        'lookout_thresholds',
        'lookout_audit_log',
        'lookout_traces',
    ];

    protected function getPackageProviders($app)
    {
        return [
            LookoutServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $storageDriver = env('LOOKOUT_TEST_STORAGE_DRIVER', 'sqlite');
        $storageConnection = env('LOOKOUT_STORAGE_CONNECTION', 'lookout');

        $app['config']->set('lookout.enabled', true);
        $app['config']->set('lookout.dashboard.enabled', true);
        $app['config']->set('lookout.storage.driver', $storageDriver);
        $app['config']->set('lookout.storage.connection', $storageConnection);
        $app['config']->set('lookout.storage.path', ':memory:');
        $app['config']->set('lookout.recorders', [
            'request' => false,
            'query' => false,
            'exception' => false,
            'job' => false,
            'scheduled_task' => false,
            'command' => false,
            'cache' => false,
            'mail' => false,
            'notification' => false,
            'log' => false,
            'outgoing_http' => false,
        ]);
        $app['config']->set("database.connections.{$storageConnection}", $this->lookoutDatabaseConnection($storageDriver));

        if (empty($app['config']->get('app.key'))) {
            $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        }
    }

    protected function defineDatabaseMigrations()
    {
        if (config('lookout.storage.driver', 'sqlite') !== 'sqlite') {
            $connection = (string) config('lookout.storage.connection', 'lookout');

            $this->resetExternalLookoutTables($connection);

            foreach (self::LOOKOUT_MIGRATIONS as $migration) {
                (require __DIR__.'/../database/migrations/'.$migration)->up();
            }

            $this->beforeApplicationDestroyed(
                fn () => $this->resetExternalLookoutTables($connection)
            );

            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function resetExternalLookoutTables(string $connection): void
    {
        Schema::connection($connection)->disableForeignKeyConstraints();

        foreach (self::LOOKOUT_TABLES_REVERSE as $table) {
            Schema::connection($connection)->dropIfExists($table);
        }

        Schema::connection($connection)->enableForeignKeyConstraints();
    }

    protected function lookoutDatabaseConnection(string $storageDriver): array
    {
        if ($storageDriver === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => env('LOOKOUT_TEST_DB_HOST', '127.0.0.1'),
                'port' => env('LOOKOUT_TEST_DB_PORT', '3306'),
                'database' => env('LOOKOUT_TEST_DB_DATABASE', 'lookout'),
                'username' => env('LOOKOUT_TEST_DB_USERNAME', 'lookout'),
                'password' => env('LOOKOUT_TEST_DB_PASSWORD', 'password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ];
        }

        if ($storageDriver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => env('LOOKOUT_TEST_DB_HOST', '127.0.0.1'),
                'port' => env('LOOKOUT_TEST_DB_PORT', '5432'),
                'database' => env('LOOKOUT_TEST_DB_DATABASE', 'lookout'),
                'username' => env('LOOKOUT_TEST_DB_USERNAME', 'lookout'),
                'password' => env('LOOKOUT_TEST_DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ];
        }

        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [];
    }
}
