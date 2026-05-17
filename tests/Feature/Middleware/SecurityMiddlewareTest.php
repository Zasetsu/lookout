<?php

use Illuminate\Support\Facades\Gate;

describe('Authorize Middleware', function () {
    it('returns 404 when dashboard is disabled', function () {
        config(['lookout.dashboard.enabled' => false]);

        $response = $this->get('/lookout');

        $response->assertStatus(404);
    });

    it('allows access when dashboard is enabled with gate allowing', function () {
        config(['lookout.dashboard.enabled' => true]);
        Gate::define('viewLookout', fn ($user = null) => true);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });

    it('returns 404 when viewLookout gate denies', function () {
        config(['lookout.dashboard.enabled' => true]);
        Gate::define('viewLookout', fn ($user = null) => false);

        $response = $this->get('/lookout');

        $response->assertStatus(404);
    });

    it('allows access when viewLookout gate allows', function () {
        config(['lookout.dashboard.enabled' => true]);
        Gate::define('viewLookout', fn ($user = null) => true);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });

    it('returns 404 when no gate is defined and no auth layer is configured', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => null,
            'lookout.dashboard.basic_auth.pass' => null,
            'lookout.dashboard.allowed_ips' => [],
            'lookout.dashboard.localhost_only' => false,
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(404);
    });

    it('allows access when no gate is defined but basic auth is configured', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => 'admin',
            'lookout.dashboard.basic_auth.pass' => 'secret',
        ]);

        $response = $this->withHeaders([
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret',
        ])->get('/lookout');

        $response->assertStatus(200);
    });
});

describe('IpWhitelist Middleware', function () {
    it('trims configured IPs loaded from the environment', function () {
        putenv('LOOKOUT_ALLOWED_IPS=127.0.0.1, 10.0.0.2');
        $_ENV['LOOKOUT_ALLOWED_IPS'] = '127.0.0.1, 10.0.0.2';
        $_SERVER['LOOKOUT_ALLOWED_IPS'] = '127.0.0.1, 10.0.0.2';

        $config = require dirname(__DIR__, 3).'/config/lookout.php';

        expect($config['dashboard']['allowed_ips'])->toBe([
            '127.0.0.1',
            '10.0.0.2',
        ]);

        putenv('LOOKOUT_ALLOWED_IPS');
        unset($_ENV['LOOKOUT_ALLOWED_IPS'], $_SERVER['LOOKOUT_ALLOWED_IPS']);
    });

    it('allows when no IPs configured', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.allowed_ips' => [],
            'lookout.dashboard.localhost_only' => true,
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });

    it('blocks when IP not in whitelist', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.allowed_ips' => ['10.0.0.1'],
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(403);
    });

    it('allows when IP is in whitelist', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.allowed_ips' => ['127.0.0.1'],
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });
});

describe('BasicAuth Middleware', function () {
    it('returns 404 when no auth configured and no other auth layer', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => null,
            'lookout.dashboard.basic_auth.pass' => null,
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(404);
    });

    it('returns 404 when only user is configured without password', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => 'admin',
            'lookout.dashboard.basic_auth.pass' => null,
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(404);
    });

    it('blocks without credentials when auth is configured', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => 'admin',
            'lookout.dashboard.basic_auth.pass' => 'secret',
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(401);
    });

    it('blocks with wrong credentials', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => 'admin',
            'lookout.dashboard.basic_auth.pass' => 'secret',
        ]);

        $response = $this->withHeaders([
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'wrong',
        ])->get('/lookout');

        $response->assertStatus(401);
    });

    it('allows with correct credentials', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.basic_auth.user' => 'admin',
            'lookout.dashboard.basic_auth.pass' => 'secret',
        ]);

        $response = $this->withHeaders([
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret',
        ])->get('/lookout');

        $response->assertStatus(200);
    });
});

describe('LocalhostOnly Middleware', function () {
    it('allows when localhost_only is false with gate defined', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.localhost_only' => false,
        ]);
        Gate::define('viewLookout', fn ($user = null) => true);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });

    it('allows when localhost_only is true and request is from localhost', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.localhost_only' => true,
        ]);

        $response = $this->get('/lookout');

        $response->assertStatus(200);
    });

    it('blocks when localhost_only is true and request is not from localhost', function () {
        config([
            'lookout.dashboard.enabled' => true,
            'lookout.dashboard.localhost_only' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->get('/lookout');

        $response->assertStatus(403);
    });
});
