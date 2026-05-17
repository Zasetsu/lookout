<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Zasetsu\Lookout\Storage\StorageContract;

Route::get('/', function () {
    return redirect('/demo/seed');
});

Route::get('/demo/seed', function (StorageContract $storage) {
    $now = now();
    $results = [];

    $traceIds = [];

    for ($i = 1; $i <= 8; $i++) {
        $methods = ['GET', 'GET', 'GET', 'POST', 'PUT', 'DELETE', 'GET', 'PATCH'];
        $routes = [
            '/api/users', '/api/products', '/api/orders', '/api/auth/login',
            '/api/users/1', '/api/orders/5', '/dashboard', '/api/settings',
        ];
        $statuses = [200, 200, 200, 201, 200, 204, 200, 200];
        $durations = [45, 120, 890, 2100, 340, 55, 1800, 67];
        $ts = $now->copy()->subMinutes(8 - $i)->toDateTimeString();

        $traceId = \Illuminate\Support\Str::uuid()->toString();
        $traceIds[] = $traceId;

        $storage->storeTrace([
            'trace_id' => $traceId,
            'type' => 'request',
            'name' => $routes[$i - 1],
            'status' => 'success',
            'timestamp' => $ts,
            'duration' => $durations[$i - 1],
            'memory_peak' => rand(4000000, 16000000),
            'user_id' => (string) rand(1, 5),
            'ip' => '192.168.1.' . rand(1, 254),
            'method' => $methods[$i - 1],
            'url' => 'http://localhost' . $routes[$i - 1],
            'request_headers' => json_encode(['accept' => ['application/json']]),
            'request_body' => $i === 4 ? json_encode(['email' => 'test@test.com', 'password' => '***']) : null,
            'response_status' => $statuses[$i - 1],
            'response_headers' => json_encode(['content-type' => ['application/json']]),
            'tags' => null,
            'environment' => 'local',
        ]);

        $events = [];

        $events[] = [
            'event_type' => 'query',
            'timestamp' => $ts,
            'duration' => rand(5, $durations[$i - 1] - 10),
            'labels' => 'SELECT * FROM ' . ['users', 'products', 'orders', 'users', 'users', 'orders', 'settings', 'settings'][$i - 1] . ' WHERE id = ?',
            'payload' => json_encode([
                'sql' => 'SELECT * FROM ' . ['users', 'products', 'orders', 'users', 'users', 'orders', 'settings', 'settings'][$i - 1] . ' WHERE id = ?',
                'bindings' => [rand(1, 100)],
                'connection' => 'mysql',
            ]),
            'tags' => null,
        ];

        if ($i % 2 === 0) {
            $events[] = [
                'event_type' => 'cache',
                'timestamp' => $ts,
                'duration' => rand(1, 5),
                'labels' => 'Cache hit: ' . ['user_1', 'product_2', 'order_3', 'user_1', 'user_1', 'order_5', 'settings', 'config'][$i - 1],
                'payload' => json_encode(['key' => ['user_1', 'product_2', 'order_3', 'user_1', 'user_1', 'order_5', 'settings', 'config'][$i - 1], 'store' => 'redis', 'operation' => 'cache_hit']),
                'tags' => null,
            ];
        } else {
            $events[] = [
                'event_type' => 'cache',
                'timestamp' => $ts,
                'duration' => rand(1, 3),
                'labels' => 'Cache miss: ' . ['user_2', 'product_5', 'order_8', 'user_3', 'user_4', 'order_9', 'dashboard', 'features'][$i - 1],
                'payload' => json_encode(['key' => ['user_2', 'product_5', 'order_8', 'user_3', 'user_4', 'order_9', 'dashboard', 'features'][$i - 1], 'store' => 'redis', 'operation' => 'cache_miss']),
                'tags' => null,
            ];
        }

        if ($i === 3 || $i === 5 || $i === 7) {
            $events[] = [
                'event_type' => 'outgoing_http',
                'timestamp' => $ts,
                'duration' => rand(50, 300),
                'labels' => 'POST https://api.stripe.com/v1/charges — 200',
                'payload' => json_encode([
                    'method' => 'POST',
                    'url' => 'https://api.stripe.com/v1/charges',
                    'headers' => ['authorization' => '***'],
                    'response_status' => 200,
                    'response_headers' => ['content-type' => ['application/json']],
                    'duration_ms' => rand(50, 300),
                ]),
                'tags' => null,
            ];
        }

        if ($i === 2 || $i === 6) {
            $events[] = [
                'event_type' => 'mail',
                'timestamp' => $ts,
                'duration' => null,
                'labels' => 'Mail: Order Confirmation #' . rand(100, 999),
                'payload' => json_encode([
                    'subject' => 'Order Confirmation #' . rand(100, 999),
                    'to' => ['customer' . $i . '@example.com'],
                    'from' => ['noreply@store.com'],
                    'cc' => [],
                ]),
                'tags' => null,
            ];
        }

        if ($i === 4 || $i === 8) {
            $events[] = [
                'event_type' => 'notification',
                'timestamp' => $ts,
                'duration' => null,
                'labels' => 'Notification: OrderShipped via mail',
                'payload' => json_encode([
                    'notification' => 'App\\Notifications\\OrderShipped',
                    'channel' => 'mail',
                    'notifiable' => 'App\\Models\\User:' . rand(1, 5),
                ]),
                'tags' => null,
            ];
        }

        if ($i % 3 === 0) {
            $events[] = [
                'event_type' => 'log',
                'timestamp' => $ts,
                'duration' => null,
                'labels' => '[info] Order processed successfully',
                'payload' => json_encode([
                    'level' => 'info',
                    'message' => 'Order #' . rand(100, 999) . ' processed successfully',
                    'context' => ['order_id' => rand(100, 999)],
                    'channel' => 'app',
                ]),
                'tags' => null,
            ];
        }

        if ($i === 1) {
            $events[] = [
                'event_type' => 'job_processed',
                'timestamp' => $ts,
                'duration' => rand(100, 500),
                'labels' => 'ProcessPaymentJob — 250ms',
                'payload' => json_encode([
                    'job_id' => (string) rand(10000, 99999),
                    'job_class' => 'App\\Jobs\\ProcessPaymentJob',
                    'queue' => 'payments',
                    'attempts' => 1,
                ]),
                'tags' => null,
            ];
        }

        if ($i === 6) {
            $events[] = [
                'event_type' => 'job_failed',
                'timestamp' => $ts,
                'duration' => rand(200, 800),
                'labels' => 'SendNewsletterJob — FAILED',
                'payload' => json_encode([
                    'job_id' => (string) rand(10000, 99999),
                    'job_class' => 'App\\Jobs\\SendNewsletterJob',
                    'queue' => 'emails',
                    'attempts' => 3,
                    'exception' => [
                        'class' => 'Swift_TransportException',
                        'message' => 'Connection refused to smtp.mailtrap.io:2525',
                        'file' => '/app/Jobs/SendNewsletterJob.php',
                        'line' => 42,
                    ],
                ]),
                'tags' => null,
            ];
        }

        $storage->storeEvents($traceId, $events);
        $results[] = "Trace {$i}: {$methods[$i - 1]} {$routes[$i - 1]} ({$durations[$i - 1]}ms) + " . count($events) . ' events';
    }

    $errorTraceId = \Illuminate\Support\Str::uuid()->toString();
    $storage->storeTrace([
        'trace_id' => $errorTraceId,
        'type' => 'request',
        'name' => '/api/checkout',
        'status' => 'error',
        'timestamp' => $now->copy()->subMinutes(2)->toDateTimeString(),
        'duration' => 450,
        'memory_peak' => 8000000,
        'user_id' => '3',
        'ip' => '192.168.1.55',
        'method' => 'POST',
        'url' => 'http://localhost/api/checkout',
        'request_headers' => json_encode(['accept' => ['application/json']]),
        'request_body' => json_encode(['cart_id' => 99, 'payment_method' => 'card']),
        'response_status' => 500,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $storage->storeEvents($errorTraceId, [
        [
            'event_type' => 'query',
            'timestamp' => $now->copy()->subMinutes(2)->toDateTimeString(),
            'duration' => 120,
            'labels' => 'SELECT * FROM carts WHERE id = ?',
            'payload' => json_encode(['sql' => 'SELECT * FROM carts WHERE id = ?', 'bindings' => [99], 'connection' => 'mysql']),
            'tags' => null,
        ],
        [
            'event_type' => 'query',
            'timestamp' => $now->copy()->subMinutes(2)->toDateTimeString(),
            'duration' => 85,
            'labels' => 'SELECT * FROM products WHERE id IN (?, ?, ?)',
            'payload' => json_encode(['sql' => 'SELECT * FROM products WHERE id IN (?, ?, ?)', 'bindings' => [1, 2, 3], 'connection' => 'mysql']),
            'tags' => null,
        ],
        [
            'event_type' => 'exception',
            'timestamp' => $now->copy()->subMinutes(2)->toDateTimeString(),
            'duration' => null,
            'labels' => 'PaymentFailedException: Card declined',
            'payload' => json_encode([
                'class' => 'App\\Exceptions\\PaymentFailedException',
                'message' => 'Card declined. Insufficient funds.',
                'file' => '/app/Services/PaymentService.php',
                'line' => 87,
                'code' => 4002,
                'stack_trace' => ['#0 /app/Http/Controllers/CheckoutController.php(45): PaymentService->charge()', '#1 /app/Http/Controllers/CheckoutController.php(23): CheckoutController->processPayment()'],
                'url' => 'http://localhost/api/checkout',
            ]),
            'tags' => null,
        ],
    ]);
    $results[] = 'Error trace: POST /api/checkout (450ms, 500) + 3 events';

    $storage->upsertExceptionGroup(hash('sha256', 'RuntimeException|/app/Controllers/AuthController.php|42'), [
        'exception_class' => 'RuntimeException',
        'file' => '/app/Controllers/AuthController.php',
        'line' => 42,
        'message' => 'Invalid credentials provided',
        'first_seen' => $now->copy()->subHours(6)->toDateTimeString(),
        'last_seen' => $now->copy()->subMinutes(30)->toDateTimeString(),
    ]);
    $storage->upsertExceptionGroup(hash('sha256', 'RuntimeException|/app/Controllers/AuthController.php|42'), [
        'exception_class' => 'RuntimeException',
        'file' => '/app/Controllers/AuthController.php',
        'line' => 42,
        'message' => 'Invalid credentials provided',
        'first_seen' => $now->copy()->subHours(6)->toDateTimeString(),
        'last_seen' => $now->copy()->subMinutes(10)->toDateTimeString(),
    ]);

    $storage->upsertExceptionGroup(hash('sha256', 'PaymentFailedException|/app/Services/PaymentService.php|87'), [
        'exception_class' => 'App\\Exceptions\\PaymentFailedException',
        'file' => '/app/Services/PaymentService.php',
        'line' => 87,
        'message' => 'Card declined. Insufficient funds.',
        'first_seen' => $now->copy()->subHours(3)->toDateTimeString(),
        'last_seen' => $now->copy()->subMinutes(5)->toDateTimeString(),
    ]);
    for ($j = 0; $j < 3; $j++) {
        $storage->upsertExceptionGroup(hash('sha256', 'PaymentFailedException|/app/Services/PaymentService.php|87'), [
            'exception_class' => 'App\\Exceptions\\PaymentFailedException',
            'file' => '/app/Services/PaymentService.php',
            'line' => 87,
            'message' => 'Card declined. Insufficient funds.',
            'first_seen' => $now->copy()->subHours(3)->toDateTimeString(),
            'last_seen' => $now->copy()->subMinutes(rand(1, 10))->toDateTimeString(),
        ]);
    }

    $storage->upsertExceptionGroup(hash('sha256', 'ModelNotFoundException|/app/Models/Product.php|156'), [
        'exception_class' => 'Illuminate\\Database\\Eloquent\\ModelNotFoundException',
        'file' => '/app/Models/Product.php',
        'line' => 156,
        'message' => 'No query results for model [App\\Models\\Product]',
        'first_seen' => $now->copy()->subDays(1)->toDateTimeString(),
        'last_seen' => $now->copy()->subHours(1)->toDateTimeString(),
    ]);
    for ($j = 0; $j < 7; $j++) {
        $storage->upsertExceptionGroup(hash('sha256', 'ModelNotFoundException|/app/Models/Product.php|156'), [
            'exception_class' => 'Illuminate\\Database\\Eloquent\\ModelNotFoundException',
            'file' => '/app/Models/Product.php',
            'line' => 156,
            'message' => 'No query results for model [App\\Models\\Product]',
            'first_seen' => $now->copy()->subDays(1)->toDateTimeString(),
            'last_seen' => $now->copy()->subMinutes(rand(10, 120))->toDateTimeString(),
        ]);
    }

    $storage->upsertExceptionGroup(hash('sha256', 'TimeoutException|/app/Services/ExternalApiService.php|34'), [
        'exception_class' => 'App\\Exceptions\\TimeoutException',
        'file' => '/app/Services/ExternalApiService.php',
        'line' => 34,
        'message' => 'Connection to payment gateway timed out after 30s',
        'first_seen' => $now->copy()->subHours(12)->toDateTimeString(),
        'last_seen' => $now->copy()->subMinutes(45)->toDateTimeString(),
    ]);
    for ($j = 0; $j < 2; $j++) {
        $storage->upsertExceptionGroup(hash('sha256', 'TimeoutException|/app/Services/ExternalApiService.php|34'), [
            'exception_class' => 'App\\Exceptions\\TimeoutException',
            'file' => '/app/Services/ExternalApiService.php',
            'line' => 34,
            'message' => 'Connection to payment gateway timed out after 30s',
            'first_seen' => $now->copy()->subHours(12)->toDateTimeString(),
            'last_seen' => $now->copy()->subMinutes(rand(20, 60))->toDateTimeString(),
        ]);
    }
    $results[] = 'Exception groups: 4 created (with occurrence counts)';

    $storage->storeTrace([
        'trace_id' => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'scheduled_task',
        'name' => 'cleanup:expired-sessions',
        'status' => 'success',
        'timestamp' => $now->copy()->subMinutes(15)->toDateTimeString(),
        'duration' => 3200,
        'memory_peak' => 6000000,
        'user_id' => null,
        'ip' => null,
        'method' => null,
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => null,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $storage->storeTrace([
        'trace_id' => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'scheduled_task',
        'name' => 'reports:daily-summary',
        'status' => 'success',
        'timestamp' => $now->copy()->subMinutes(45)->toDateTimeString(),
        'duration' => 8500,
        'memory_peak' => 12000000,
        'user_id' => null,
        'ip' => null,
        'method' => null,
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => null,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $storage->storeTrace([
        'trace_id' => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'scheduled_task',
        'name' => 'sync:inventory',
        'status' => 'error',
        'timestamp' => $now->copy()->subHour()->toDateTimeString(),
        'duration' => 12000,
        'memory_peak' => 18000000,
        'user_id' => null,
        'ip' => null,
        'method' => null,
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => null,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $results[] = 'Scheduled tasks: 3 created (2 success, 1 error)';

    $storage->storeTrace([
        'trace_id' => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'command',
        'name' => 'migrate:status',
        'status' => 'success',
        'timestamp' => $now->copy()->subMinutes(20)->toDateTimeString(),
        'duration' => 450,
        'memory_peak' => 8000000,
        'user_id' => null,
        'ip' => null,
        'method' => null,
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => null,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $storage->storeTrace([
        'trace_id' => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'command',
        'name' => 'queue:restart',
        'status' => 'success',
        'timestamp' => $now->copy()->subMinutes(10)->toDateTimeString(),
        'duration' => 120,
        'memory_peak' => 6000000,
        'user_id' => null,
        'ip' => null,
        'method' => null,
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => null,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'local',
    ]);
    $results[] = 'Commands: 2 created';

    $moreEvents = [];
    $moreTraceId = $traceIds[0];
    for ($c = 0; $c < 5; $c++) {
        $ops = ['cache_hit', 'cache_miss', 'cache_write', 'cache_forget'];
        $op = $ops[$c % 4];
        $moreEvents[] = [
            'event_type' => 'cache',
            'timestamp' => $now->copy()->subMinutes(rand(1, 30))->toDateTimeString(),
            'duration' => rand(1, 8),
            'labels' => "Cache {$op}: key_{$c}",
            'payload' => json_encode(['key' => "user_session_{$c}", 'store' => 'redis', 'operation' => $op]),
            'tags' => null,
        ];
    }
    $storage->storeEvents($moreTraceId, $moreEvents);

    $logEvents = [];
    $logTraceId = $traceIds[1];
    $levels = ['debug', 'info', 'info', 'warning', 'warning', 'error', 'info', 'debug', 'warning', 'error'];
    $messages = [
        'User authenticated successfully',
        'Cache cleared for store: redis',
        'New order created: #1042',
        'Slow query detected (2.1s): SELECT * FROM orders',
        'Rate limit approaching for IP 192.168.1.55',
        'Failed to process webhook from Stripe',
        'Scheduled task completed: daily-report',
        'Redis connection pool: 8/10 connections in use',
        'Memory usage at 85% for worker-3',
        'Payment gateway returned unexpected 503',
    ];
    for ($l = 0; $l < 10; $l++) {
        $logEvents[] = [
            'event_type' => 'log',
            'timestamp' => $now->copy()->subMinutes(rand(1, 60))->toDateTimeString(),
            'duration' => null,
            'labels' => "[{$levels[$l]}] {$messages[$l]}",
            'payload' => json_encode([
                'level' => $levels[$l],
                'message' => $messages[$l],
                'context' => ['source' => 'app'],
                'channel' => 'app',
            ]),
            'tags' => null,
        ];
    }
    $storage->storeEvents($logTraceId, $logEvents);
    $results[] = 'Extra log events: 10 created';

    $httpEvents = [];
    $httpTraceId = $traceIds[2];
    $httpData = [
        ['GET', 'https://api.github.com/repos/laravel/framework', 200, 180],
        ['POST', 'https://api.stripe.com/v1/charges', 200, 340],
        ['GET', 'https://api.github.com/repos/laravel/framework/issues', 200, 220],
        ['GET', 'https://api.mapbox.com/geocoding/v5/mapbox.places/istanbul.json', 200, 150],
        ['POST', 'https://hooks.slack.com/services/T00/B00/xxx', 500, 5200],
        ['GET', 'https://api.weatherapi.com/v1/current.json?q=Istanbul', 200, 95],
    ];
    foreach ($httpData as $hd) {
        $httpEvents[] = [
            'event_type' => 'outgoing_http',
            'timestamp' => $now->copy()->subMinutes(rand(1, 45))->toDateTimeString(),
            'duration' => $hd[3],
            'labels' => "{$hd[0]} {$hd[1]} — {$hd[2]}",
            'payload' => json_encode([
                'method' => $hd[0],
                'url' => $hd[1],
                'headers' => ['authorization' => '***'],
                'response_status' => $hd[2],
                'response_headers' => ['content-type' => ['application/json']],
                'duration_ms' => $hd[3],
            ]),
            'tags' => null,
        ];
    }
    $storage->storeEvents($httpTraceId, $httpEvents);
    $results[] = 'Outgoing HTTP events: 6 created';

    $mailEvents = [];
    $mailTraceId = $traceIds[3];
    $mails = [
        ['Welcome to MyApp!', 'john@example.com', 'noreply@myapp.com'],
        ['Your order has shipped!', 'jane@example.com', 'orders@store.com'],
        ['Password reset request', 'bob@example.com', 'security@myapp.com'],
        ['Invoice #INV-2024-001', 'finance@company.com', 'billing@company.com'],
    ];
    foreach ($mails as $m) {
        $mailEvents[] = [
            'event_type' => 'mail',
            'timestamp' => $now->copy()->subMinutes(rand(5, 120))->toDateTimeString(),
            'duration' => null,
            'labels' => "Mail: {$m[0]}",
            'payload' => json_encode([
                'subject' => $m[0],
                'to' => [$m[1]],
                'from' => [$m[2]],
                'cc' => [],
            ]),
            'tags' => null,
        ];
    }
    $storage->storeEvents($mailTraceId, $mailEvents);
    $results[] = 'Mail events: 4 created';

    $notifEvents = [];
    $notifTraceId = $traceIds[4];
    $notifs = [
        ['OrderConfirmed', 'mail', 'App\\Models\\User:1'],
        ['PaymentReceived', 'database', 'App\\Models\\User:2'],
        ['ServerAlert', 'slack', 'App\\Models\\Admin:1'],
        ['NewComment', 'mail', 'App\\Models\\User:3'],
        ['BackupCompleted', 'mail', 'App\\Models\\Admin:1'],
    ];
    foreach ($notifs as $n) {
        $notifEvents[] = [
            'event_type' => 'notification',
            'timestamp' => $now->copy()->subMinutes(rand(5, 90))->toDateTimeString(),
            'duration' => null,
            'labels' => "Notification: {$n[0]} via {$n[1]}",
            'payload' => json_encode([
                'notification' => "App\\Notifications\\{$n[0]}",
                'channel' => $n[1],
                'notifiable' => $n[2],
            ]),
            'tags' => null,
        ];
    }
    $storage->storeEvents($notifTraceId, $notifEvents);
    $results[] = 'Notification events: 5 created';

    $jobEvents = [];
    $jobTraceId = $traceIds[5];
    $jobs = [
        ['ProcessPaymentJob', 'payments', 'job_processed', 250],
        ['GenerateInvoiceJob', 'default', 'job_processed', 1800],
        ['SendWelcomeEmailJob', 'emails', 'job_processed', 320],
        ['CleanupExpiredSessionsJob', 'default', 'job_processed', 4500],
        ['SyncInventoryJob', 'sync', 'job_failed', 8200],
    ];
    foreach ($jobs as $j) {
        $payload = [
            'job_id' => (string) rand(10000, 99999),
            'job_class' => "App\\Jobs\\{$j[0]}",
            'queue' => $j[1],
            'attempts' => $j[3] === 'job_failed' ? 3 : 1,
        ];
        if ($j[2] === 'job_failed') {
            $payload['exception'] = [
                'class' => 'App\\Exceptions\\SyncException',
                'message' => 'Failed to connect to inventory API',
                'file' => "/app/Jobs/{$j[0]}.php",
                'line' => rand(20, 80),
            ];
        }
        $jobEvents[] = [
            'event_type' => $j[2],
            'timestamp' => $now->copy()->subMinutes(rand(5, 60))->toDateTimeString(),
            'duration' => $j[3],
            'labels' => "{$j[0]} — " . number_format($j[3]) . 'ms' . ($j[2] === 'job_failed' ? ' FAILED' : ''),
            'payload' => json_encode($payload),
            'tags' => null,
        ];
    }
    $storage->storeEvents($jobTraceId, $jobEvents);
    $results[] = 'Job events: 5 created (4 processed, 1 failed)';

    return response()->json([
        'status' => 'Seeded successfully!',
        'summary' => $results,
        'total_traces' => DB::connection('lookout')->table('lookout_traces')->count(),
        'total_events' => DB::connection('lookout')->table('lookout_events')->count(),
        'exception_groups' => DB::connection('lookout')->table('lookout_exception_groups')->count(),
        'next' => 'Visit /lookout to see the dashboard',
    ]);
});

Route::get('/demo/slow', function () {
    usleep(500000);

    return response()->json(['status' => 'slow', 'took' => '500ms']);
});

Route::get('/demo/error', function () {
    throw new RuntimeException('Demo exception from Lookout sandbox!');
});

Route::get('/demo/reset', function () {
    DB::connection('lookout')->table('lookout_traces')->delete();
    DB::connection('lookout')->table('lookout_events')->delete();
    DB::connection('lookout')->table('lookout_exception_groups')->delete();
    DB::connection('lookout')->table('lookout_audit_log')->delete();

    return response()->json(['status' => 'All data cleared']);
});
