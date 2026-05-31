<?php

namespace Zasetsu\Lookout;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Zasetsu\Lookout\Http\Middleware\BootstrapTrace;
use Zasetsu\Lookout\Pipeline\AutoSampler;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Recorders\CacheRecorder;
use Zasetsu\Lookout\Recorders\CommandRecorder;
use Zasetsu\Lookout\Recorders\ExceptionRecorder;
use Zasetsu\Lookout\Recorders\JobRecorder;
use Zasetsu\Lookout\Recorders\LogRecorder;
use Zasetsu\Lookout\Recorders\MailRecorder;
use Zasetsu\Lookout\Recorders\NotificationRecorder;
use Zasetsu\Lookout\Recorders\OutgoingHttpRecorder;
use Zasetsu\Lookout\Recorders\QueryRecorder;
use Zasetsu\Lookout\Recorders\RequestRecorder;
use Zasetsu\Lookout\Recorders\ScheduledTaskRecorder;
use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Storage\StorageDriverResolver;
use Zasetsu\Lookout\Support\TraceDispatcher;
use Zasetsu\Lookout\Trace\TraceBuffer;

class LookoutServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lookout')
            ->hasConfigFile('lookout')
            ->hasMigrations([
                'create_lookout_traces_table',
                'create_lookout_events_table',
                'create_lookout_exception_groups_table',
                'create_lookout_thresholds_table',
                'add_cooldown_minutes_to_lookout_thresholds_table',
                'create_lookout_audit_log_table',
            ])
            ->hasCommands([
                Console\InstallCommand::class,
                Console\WorkCommand::class,
                Console\PruneCommand::class,
            ])
            ->hasViews()
            ->hasAssets();
    }

    protected function bootPackageRoutes(): self
    {
        if (! config('lookout.enabled', true)) {
            return $this;
        }

        if (config('lookout.dashboard.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (config('lookout.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        return $this;
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(StorageDriverResolver::class);
        $this->app->singleton(StorageContract::class, fn ($app) => $app->make($app->make(StorageDriverResolver::class)->storageClass()));
        $this->app->singleton(TraceBuffer::class);
        $this->app->singleton(Sampler::class);
        $this->app->singleton(AutoSampler::class);
        $this->app->singleton(Filter::class);
        $this->app->singleton(Redactor::class);

        $this->registerLookoutConnection();
    }

    public function packageBooted(): void
    {
        if (! config('lookout.enabled')) {
            return;
        }

        $this->registerMiddleware();
        $this->registerRecorders();
        $this->registerTerminatingCallback();
    }

    protected function registerMiddleware(): void
    {
        $this->prependMiddlewareToGroup('web', BootstrapTrace::class);
        $this->prependMiddlewareToGroup('api', BootstrapTrace::class);
    }

    protected function prependMiddlewareToGroup(string $group, string $middleware): void
    {
        $router = $this->app['router'];
        $groups = $router->getMiddlewareGroups();
        $middlewares = $groups[$group] ?? [];
        $middlewares = array_values(array_filter(
            $middlewares,
            fn (string $existing): bool => $existing !== $middleware
        ));

        array_unshift($middlewares, $middleware);

        $router->middlewareGroup($group, $middlewares);
    }

    protected function registerLookoutConnection(): void
    {
        if (! $this->app->make(StorageDriverResolver::class)->shouldRegisterManagedSqliteConnection()) {
            return;
        }

        $connectionName = config('lookout.storage.connection', 'lookout');

        $this->app['config']->set("database.connections.{$connectionName}", [
            'driver' => 'sqlite',
            'database' => config('lookout.storage.path', storage_path('lookout/lookout.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function registerRecorders(): void
    {
        $recorders = config('lookout.recorders', []);

        if (($recorders['request'] ?? false) !== false) {
            $this->app->make(RequestRecorder::class)->register();
        }
        if (($recorders['query'] ?? false) !== false) {
            $this->app->make(QueryRecorder::class)->register();
        }
        if (($recorders['exception'] ?? false) !== false) {
            $this->app->make(ExceptionRecorder::class)->register();
        }
        if (($recorders['job'] ?? false) !== false) {
            $this->app->make(JobRecorder::class)->register();
        }
        if (($recorders['scheduled_task'] ?? false) !== false) {
            $this->app->make(ScheduledTaskRecorder::class)->register();
        }
        if (($recorders['command'] ?? false) !== false) {
            $this->app->make(CommandRecorder::class)->register();
        }
        if (($recorders['cache'] ?? false) !== false) {
            $this->app->make(CacheRecorder::class)->register();
        }
        if (($recorders['mail'] ?? false) !== false) {
            $this->app->make(MailRecorder::class)->register();
        }
        if (($recorders['notification'] ?? false) !== false) {
            $this->app->make(NotificationRecorder::class)->register();
        }
        if (($recorders['log'] ?? false) !== false) {
            $this->app->make(LogRecorder::class)->register();
        }
        if (($recorders['outgoing_http'] ?? false) !== false) {
            $this->app->make(OutgoingHttpRecorder::class)->register();
        }
    }

    protected function registerTerminatingCallback(): void
    {
        $this->app->terminating(function () {
            $buffer = $this->app->make(TraceBuffer::class);

            if (! $buffer->shouldCollect()) {
                return;
            }

            $data = $buffer->flush();

            if ($data['context'] === null) {
                return;
            }

            TraceDispatcher::dispatch($data['context'], $data['events']);
        });
    }
}
