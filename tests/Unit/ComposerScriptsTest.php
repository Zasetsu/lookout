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
