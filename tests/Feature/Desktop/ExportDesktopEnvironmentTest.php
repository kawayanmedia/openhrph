<?php

use Illuminate\Support\Facades\File;

function writeEnv(string $path, string $contents): string
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/desktop-env'));
});

it('exports the packaged app environment without secrets', function () {
    $directory = storage_path('framework/testing/desktop-env');

    $source = writeEnv($directory.'/.env', implode(PHP_EOL, [
        '# comment that should not ship',
        'APP_NAME="OpenHR PH"',
        'APP_KEY=base64:abcdefghijklmnopqrstuvwxyz0123456789',
        'APP_TIMEZONE=Asia/Manila',
        'DESKTOP_DEBUG=true',
        'LOG_CHANNEL=stack',
        'LOG_LEVEL=debug',
        'AWS_SECRET_ACCESS_KEY=super-secret',
        'MAIL_PASSWORD=super-secret',
        '',
    ]));

    $output = $directory.'/desktop.env';

    $this->artisan('desktop:export-env', ['--source' => $source, '--output' => $output])
        ->assertSuccessful();

    $exported = File::get($output);

    expect($exported)
        ->toContain('APP_KEY=base64:abcdefghijklmnopqrstuvwxyz0123456789')
        ->toContain('APP_TIMEZONE=Asia/Manila')
        ->toContain('DESKTOP_DEBUG=true')
        ->not->toContain('AWS_SECRET_ACCESS_KEY')
        ->not->toContain('MAIL_PASSWORD')
        ->not->toContain('# comment')
        ->toContain('LOG_STACK=daily')
        ->toEndWith('LOG_LEVEL=warning'.PHP_EOL);
});

it('refuses to export an environment without an application key', function () {
    $directory = storage_path('framework/testing/desktop-env');

    $source = writeEnv($directory.'/.env', 'APP_NAME="OpenHR PH"'.PHP_EOL.'APP_KEY='.PHP_EOL);

    $this->artisan('desktop:export-env', [
        '--source' => $source,
        '--output' => $directory.'/desktop.env',
    ])->assertFailed();

    expect(File::exists($directory.'/desktop.env'))->toBeFalse();
});
