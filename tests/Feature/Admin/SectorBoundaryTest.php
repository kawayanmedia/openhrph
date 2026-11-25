<?php

use App\Console\Commands\SectorCommand;
use App\Enums\Sector;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Sectors\Contracts\DocumentTemplate;
use App\Sectors\Contracts\RemittanceForm;
use App\Sectors\Contracts\SectorReadiness;
use App\Sectors\SectorManager;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

it('keeps sector branching inside the sector modules', function () {
    $allowed = [
        'Enums/Sector.php',
        'Support/Installation/Installation.php',
        'Services/Payroll/Computation/Config.php',
        'Services/Payroll/Engine/Legacy/LegacyInput.php',
    ];

    $offenders = [];

    foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
        $relative = str_replace(app_path().'/', '', $file->getPathname());

        if (str_starts_with($relative, 'Sectors/')
            || str_contains($relative, '/Government/')
            || in_array($relative, $allowed, true)) {
            continue;
        }

        if (preg_match('/\bisGovernment\b|is_government/', $file->getContents()) === 1) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([], 'Sector branching leaked into: '.implode(', ', $offenders));
});

it('resolves every class contributed by a sector feature pack', function () {
    $manager = app(SectorManager::class);

    foreach ([Sector::Government, Sector::Private] as $sector) {
        $pack = $manager->for($sector)->featurePack();

        foreach ($pack->adminResources() as $class) {
            expect(class_exists($class))->toBeTrue("Missing resource {$class}");
            expect(is_subclass_of($class, Resource::class))->toBeTrue("{$class} is not an admin resource");
        }

        foreach ($pack->commands() as $class) {
            expect(class_exists($class))->toBeTrue("Missing command {$class}");
            expect(is_subclass_of($class, Command::class))->toBeTrue("{$class} is not a command");
        }

        foreach ($pack->readiness() as $class) {
            expect(class_exists($class))->toBeTrue("Missing readiness contributor {$class}");
            expect(is_subclass_of($class, SectorReadiness::class))->toBeTrue("{$class} is not a readiness contributor");
        }

        foreach ($pack->remittanceForms() as $class) {
            expect(class_exists($class))->toBeTrue("Missing remittance form {$class}");
            expect(is_subclass_of($class, RemittanceForm::class))->toBeTrue("{$class} is not a remittance form");
        }

        foreach ($pack->documents() as $class) {
            expect(class_exists($class))->toBeTrue("Missing document template {$class}");
            expect(is_subclass_of($class, DocumentTemplate::class))->toBeTrue("{$class} is not a document template");
        }
    }
});

it('tags every sector-gated resource with a valid sector', function () {
    $sectors = array_map(static fn (Sector $sector): string => $sector->value, Sector::cases());

    foreach (ResourceRegistry::available() as $class) {
        if ($class::$sector === null) {
            continue;
        }

        expect(in_array($class::$sector, $sectors, true))->toBeTrue("{$class} has an invalid sector tag");
    }
});

it('guards government commands with the sector command base', function () {
    $pack = app(SectorManager::class)->for(Sector::Government)->featurePack();

    foreach ($pack->commands() as $class) {
        expect(is_subclass_of($class, SectorCommand::class))->toBeTrue("{$class} must extend SectorCommand");

        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        expect($defaults['sector'] ?? null)->toBe('Government');
    }
});
