<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Files extends Field
{
    public string $component = 'files';

    public array $customPropertiesFields = [];

    public function __construct()
    {
        // Media collections are resolved on detail only; never on the index table.
        $this->showOnIndex = false;
    }

    public function customPropertiesFields(array $fields): static
    {
        $this->customPropertiesFields = $fields;

        return $this;
    }

    public function resolveForForm(Model $model): mixed
    {
        return null;
    }

    /**
     * @return array<int, array{name: string, url: string, size: int}>
     */
    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        if (! $model instanceof HasMedia) {
            return [];
        }

        if (! Schema::hasTable(
            config('media-library.table_name', 'media')
        )) {
            return [];
        }

        return $model->getMedia($this->attribute)
            ->map(fn (Media $media) => [
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
                'size' => $media->size,
            ])
            ->values()
            ->all();
    }

    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        if (! $model instanceof HasMedia) {
            return;
        }

        $files = is_array($requestValue) ? $requestValue : [$requestValue];

        $uploads = array_values(array_filter($files, fn ($file) => $file instanceof TemporaryUploadedFile));

        if ($uploads === []) {
            return;
        }

        // Media can only be attached once the model has a primary key.
        if (! $model->exists) {
            $model->save();
        }

        foreach ($uploads as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $model->addMedia($file->getRealPath())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection($this->attribute);
        }
    }
}
