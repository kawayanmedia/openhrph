<?php

namespace App\Livewire\Admin\Support;

use App\Livewire\Admin\Resource;
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

        return new $class;
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
        $candidate = $namespace . '\\' . Str::studly(Str::replace('-', ' ', $uriKey));

        if (class_exists($candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * All resource classes discovered under the admin resources directory.
     *
     * @return array<int, class-string<Resource>>
     */
    public static function available(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $namespace = config('admin.resources_namespace');
        $path = app_path('Livewire/Admin/Resources');
        $classes = [];

        if (is_dir($path)) {
            foreach ((new Finder)->files()->in($path)->name('*.php') as $file) {
                $relative = Str::replace('/', '\\', $file->getRelativePathname());
                $class = $namespace . '\\' . Str::replaceLast('.php', '', $relative);

                if (class_exists($class) && is_subclass_of($class, Resource::class)) {
                    $classes[] = $class;
                }
            }
        }

        usort($classes, fn ($a, $b) => ($a::$priority ?? 9999) <=> ($b::$priority ?? 9999));

        return $cache = $classes;
    }
}
