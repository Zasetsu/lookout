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
        $app['config']->set('lookout.enabled', true);
        $app['config']->set('lookout.dashboard.enabled', true);
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
        $app['config']->set('database.connections.lookout', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        if (empty($app['config']->get('app.key'))) {
            $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        }
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageAliases($app)
    {
        return [];
    }
}
