<?php

namespace App\Console\Commands;

use App\Support\Media\TenantPathGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class TenancyBackfillMedia extends Command
{
    protected $signature = 'tenancy:backfill-media {--dry-run : Report movements without touching storage}';

    protected $description = 'Move existing media files into tenant directories and tag them with a company id';

    public function handle(): int
    {
        if (! Schema::hasTable('media')) {
            $this->warn('No media table found.');

            return self::SUCCESS;
        }

        $default = new DefaultPathGenerator;
        $tenant = new TenantPathGenerator;

        Media::query()->orderBy('id')->each(function (Media $media) use ($default, $tenant): void {
            $custom = $media->custom_properties ?? [];
            $companyId = $custom['company_id'] ?? $this->resolveCompanyId($media);

            if ($companyId === null) {
                $this->warn("Media [{$media->getKey()}] has no resolvable company; skipped.");

                return;
            }

            $custom['company_id'] = (int) $companyId;
            $media->custom_properties = $custom;

            if (! $this->option('dry-run')) {
                $media->save();
            }

            $disk = Storage::disk($media->disk);

            $pairs = [
                [$default->getPath($media), $tenant->getPath($media), $media->file_name],
                [$default->getPathForConversions($media), $tenant->getPathForConversions($media), null],
                [$default->getPathForResponsiveImages($media), $tenant->getPathForResponsiveImages($media), null],
            ];

            foreach ($pairs as [$from, $to, $file]) {
                $files = $file !== null
                    ? [$file]
                    : collect($disk->files($from))->map(fn (string $path) => basename($path))->all();

                foreach ($files as $name) {
                    if (! $disk->exists($from.$name)) {
                        continue;
                    }

                    $this->line("Media [{$media->getKey()}]: {$from}{$name} -> {$to}{$name}");

                    if (! $this->option('dry-run')) {
                        $disk->move($from.$name, $to.$name);
                    }
                }
            }
        });

        return self::SUCCESS;
    }

    private function resolveCompanyId(Media $media): ?int
    {
        if (! class_exists($media->model_type)) {
            return null;
        }

        $query = $media->model_type::query();

        if (method_exists($query->getModel(), 'scopeWithoutTenant')) {
            $query->withoutTenant();
        }

        $model = $query->find($media->model_id);

        return $model?->company_id !== null ? (int) $model->company_id : null;
    }
}
