<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class File extends Field
{
    public string $component = 'file';

    public ?string $disk = null;

    public ?string $storagePath = null;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function path(string $path): static
    {
        $this->storagePath = $path;

        return $this;
    }

    public function store(callable $callback): static
    {
        return $this;
    }

    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        return $model->getAttribute($this->attribute);
    }

    public function resolveForForm(Model $model): mixed
    {
        // Existing paths are not useful as file-input values; keep them in storage.
        return $model->getAttribute($this->attribute);
    }

    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        if ($this->computed) {
            return;
        }

        if ($requestValue instanceof TemporaryUploadedFile) {
            $disk = $this->disk ?? 'public';
            $old = $model->getAttribute($this->attribute);

            $path = $requestValue->store($this->storagePath ?? 'documents', $disk);

            if ($path === false) {
                return;
            }

            $model->setAttribute($this->attribute, $path);

            if (is_string($old) && $old !== '' && $old !== $path) {
                Storage::disk($disk)->delete($old);
            }

            return;
        }

        // Strings are existing paths — keep them untouched.
    }
}
