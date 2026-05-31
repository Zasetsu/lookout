<?php

namespace Zasetsu\Lookout\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Zasetsu\Lookout\LookoutServiceProvider;

class TestCase extends BaseTestCase
{
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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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
