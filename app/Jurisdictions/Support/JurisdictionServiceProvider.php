<?php

namespace App\Jurisdictions\Support;

use App\Jurisdictions\Contracts\Jurisdiction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registers every jurisdiction module discovered under app/Jurisdictions.
 *
 * A third-party package can call JurisdictionRegistry::register() from its own
 * service provider; a module dropped into app/Jurisdictions needs no boot code.
 */
class JurisdictionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JurisdictionRegistry::class);
        $this->app->singleton(JurisdictionManager::class);

        $this->registerConfigured();
        $this->registerDiscovered();
    }

    private function registerConfigured(): void
    {
        $registry = $this->app->make(JurisdictionRegistry::class);

        foreach ((array) config('jurisdictions.register', []) as $class) {
            if (is_string($class) && class_exists($class)) {
                $this->registerClass($registry, $class);
            }
        }
    }

    private function registerDiscovered(): void
    {
        $path = app_path('Jurisdictions');

        if (! is_dir($path)) {
            return;
        }

        $registry = $this->app->make(JurisdictionRegistry::class);

        foreach (File::allFiles($path) as $file) {
            $name = $file->getFilename();

            if (! Str::endsWith($name, 'Jurisdiction.php')) {
                continue;
            }

            $relative = Str::of($file->getRelativePathname())
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->replaceLast('.php', '')
                ->toString();

            if (Str::startsWith($relative, ['Generic\\', 'Support\\', 'Contracts\\'])) {
                continue;
            }

            $class = 'App\\Jurisdictions\\'.$relative;

            if (! class_exists($class) || ! is_subclass_of($class, Jurisdiction::class)) {
                continue;
            }

            $this->registerClass($registry, $class);
        }
    }

    /**
     * @param  class-string<Jurisdiction>  $class
     */
    private function registerClass(JurisdictionRegistry $registry, string $class): void
    {
        try {
            $jurisdiction = $this->app->make($class);
            $registry->register($jurisdiction);

            if ($provider = $jurisdiction->serviceProvider()) {
                if (class_exists($provider) && ! $this->app->providerIsLoaded($provider)) {
                    $this->app->register($provider);
                }
            }
        } catch (Throwable) {
            // A broken optional module must not take the whole application down.
        }
    }
}
