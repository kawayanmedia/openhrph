<?php

namespace App\Livewire\Admin\Support;

use App\Jurisdictions\Support\JurisdictionManager;
use App\Livewire\Admin\Resource;
use App\Sectors\SectorManager;
use App\Support\Installation\Installation;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ResourceRegistry
{
    /**
     * Resolve a resource class by its URI key.
     */
    public static function resolve(string $uriKey): Resource
    {
        $class = static::find($uriKey);

        abort_unless($class, 404);
        abort_unless(static::sectorAllows($class), 404);
        abort_unless(static::jurisdictionAllows($class), 404);

        return new $class;
    }

    /**
     * A sector-tagged resource is invisible to the other sector.
     */
    protected static function sectorAllows(string $class): bool
    {
        $sector = $class::$sector ?? null;

        if ($sector === null) {
            return true;
        }

        return strcasecmp((string) $sector, app(SectorManager::class)->currentSector()->value) === 0;
    }

    /**
     * A jurisdiction-tagged resource is invisible to other countries.
     */
    protected static function jurisdictionAllows(string $class): bool
    {
        $jurisdiction = $class::$jurisdiction ?? null;

        if ($jurisdiction === null) {
            return true;
        }

        return strcasecmp((string) $jurisdiction, Installation::jurisdiction()->code()) === 0;
    }

    public static function find(string $uriKey): ?string
    {
        $namespace = config('admin.resources_namespace');

        foreach (static::available() as $class) {
            if ($class::uriKey() === $uriKey) {
                return $class;
            }
        }

        // Fall back to a direct class-name match for namespaces not on disk yet.
        $candidate = $namespace.'\\'.Str::studly(Str::replace('-', ' ', $uriKey));

        if (class_exists($candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * All resource classes discovered under the admin resources directory.
     *
     * @return array<int, class-string<resource>>
     */
    public static function available(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $classes = [];
        $paths = [
            config('admin.resources_namespace') => app_path('Livewire/Admin/Resources'),
            'App\\Jurisdictions' => app_path('Jurisdictions'),
        ];

        foreach ($paths as $namespace => $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = (new Finder)->files()->in($path)->name('*.php');

            if ($namespace === 'App\\Jurisdictions') {
                $finder = $finder->path('/Admin/Resources/');
            }

            foreach ($finder as $file) {
                $relative = Str::replace('/', '\\', $file->getRelativePathname());
                $class = $namespace.'\\'.Str::replaceLast('.php', '', $relative);

                if (class_exists($class) && is_subclass_of($class, Resource::class)) {
                    $classes[] = $class;
                }
            }
        }

        $jurisdiction = app(JurisdictionManager::class)->current();

        foreach ($jurisdiction->adminResources() as $class) {
            if (class_exists($class) && is_subclass_of($class, Resource::class)) {
                $classes[] = $class;
            }
        }

        $classes = array_values(array_unique($classes));

        usort($classes, fn ($a, $b) => ($a::$priority ?? 9999) <=> ($b::$priority ?? 9999));

        return $cache = $classes;
    }
}
