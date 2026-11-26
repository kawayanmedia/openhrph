<?php

use App\Services\Desktop\DesktopEnvironment;
use App\Services\Desktop\DesktopUpdater;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrationPath = storage_path('framework/testing/desktop-migrations-'.uniqid());

    File::ensureDirectoryExists($this->migrationPath);

    $this->app->make('migrator')->path($this->migrationPath);
});

afterEach(function () {
    File::deleteDirectory($this->migrationPath);
});

function writeDesktopProbeMigration(string $directory): string
{
    $name = '2026_01_01_000000_create_desktop_probe_table';

    File::put($directory.'/'.$name.'.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_probe', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_probe');
    }
};
PHP);

    return $name;
}

it('applies pending migrations when the bundled version changes', function () {
    config(['nativephp-internal.running' => true, 'nativephp.version' => '3.0.0']);

    $name = writeDesktopProbeMigration($this->migrationPath);

    expect(DesktopUpdater::pendingMigrations())->toContain($name);

    DesktopEnvironment::ensureDatabaseIsMigrated();

    expect(Schema::hasTable('desktop_probe'))->toBeTrue()
        ->and(Installation::get('desktop_migrated_version'))->toBe('3.0.0')
        ->and(DesktopUpdater::pendingMigrations())->toBe([]);
});

it('skips migrations for a version that is already applied', function () {
    config(['nativephp-internal.running' => true, 'nativephp.version' => '3.0.0']);

    Installation::set('desktop_migrated_version', '3.0.0');

    writeDesktopProbeMigration($this->migrationPath);

    DesktopEnvironment::ensureDatabaseIsMigrated();

    expect(Schema::hasTable('desktop_probe'))->toBeFalse();
});

it('records the version even when no migration is waiting', function () {
    config(['nativephp-internal.running' => true, 'nativephp.version' => '3.1.0']);

    DesktopEnvironment::ensureDatabaseIsMigrated();

    expect(Installation::get('desktop_migrated_version'))->toBe('3.1.0');
});

it('does nothing outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false, 'nativephp.version' => '3.0.0']);

    writeDesktopProbeMigration($this->migrationPath);

    DesktopEnvironment::ensureDatabaseIsMigrated();

    expect(Schema::hasTable('desktop_probe'))->toBeFalse()
        ->and(Installation::get('desktop_migrated_version'))->toBeNull();
});
