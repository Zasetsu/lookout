<?php

it('runs phpstan with the same memory limit as CI', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($composer['scripts']['analyse'])->toContain('--memory-limit=512M');
});

it('aligns test tooling with the advertised Laravel versions', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect(str_contains($composer['require']['illuminate/contracts'], '^10.0'))->toBeFalse();

    expect($composer['require']['illuminate/contracts'])
        ->toContain('^11.0')
        ->toContain('^12.0')
        ->toContain('^13.0');

    expect(str_contains($composer['require-dev']['orchestra/testbench'], '^8.0'))->toBeFalse();

    expect($composer['require-dev']['orchestra/testbench'])
        ->toContain('^9.0')
        ->toContain('^10.0')
        ->toContain('^11.0');

    expect($composer['require-dev']['pestphp/pest'])
        ->toContain('^3.8')
        ->toContain('^4.4');

    expect($composer['require-dev']['pestphp/pest-plugin-laravel'])
        ->toContain('^3.2')
        ->toContain('^4.1');
});

it('runs CI against every advertised Laravel version', function () {
    $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

    expect(str_contains($workflow, 'laravel: 10.*'))->toBeFalse()
        ->and(str_contains($workflow, 'testbench: 8.*'))->toBeFalse();

    expect($workflow)
        ->toContain('laravel: 11.*')
        ->toContain('laravel: 12.*')
        ->toContain('laravel: 13.*')
        ->toContain('testbench: 9.*')
        ->toContain('testbench: 10.*')
        ->toContain('testbench: 11.*')
        ->toContain('pest: ^3.8')
        ->toContain('pest: ^4.4')
        ->toContain('pest_laravel: ^3.2')
        ->toContain('pest_laravel: ^4.1');
});

it('keeps release workflow behind the same quality gates as local verification', function () {
    $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/release.yml');

    expect($workflow)
        ->toContain('vendor/bin/pest --parallel')
        ->toContain('vendor/bin/phpstan analyse --memory-limit=512M')
        ->toContain('vendor/bin/pint --test')
        ->toContain('composer audit')
        ->toContain('npm install --no-audit --no-fund')
        ->toContain('npm audit --audit-level=moderate')
        ->toContain('npm run build');
});

it('builds dashboard css and js assets from committed sources', function () {
    $package = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/lookout.js');

    expect($package['scripts']['build'])->toContain('npm run build:css')
        ->toContain('npm run build:js')
        ->and($package['scripts'])->toHaveKey('build:css')
        ->and($package['scripts'])->toHaveKey('build:js')
        ->and($package['scripts']['build:js'])->toContain('resources/js/lookout.js')
        ->and($package['scripts']['build:js'])->toContain('resources/dist/lookout.js')
        ->and($source)->not->toContain('alpinejs')
        ->and($package['devDependencies'])->not->toHaveKey('alpinejs')
        ->and($package['devDependencies'])->not->toHaveKey('chart.js');
});
