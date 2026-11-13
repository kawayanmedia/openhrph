<?php

namespace App\Livewire\Admin\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExportCsv extends Action
{
    public string $name = 'Export As CSV';

    public string $confirmButtonText = 'Download';

    public string $confirmText = 'Download the selected rows as a CSV file?';

    /** @var callable|null */
    public $formatCallback = null;

    public static function make(): static
    {
        return new static;
    }

    public function withFormat(callable $callback): static
    {
        $this->formatCallback = $callback;

        return $this;
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $rows = $models->map(function ($model) {
            if ($this->formatCallback !== null) {
                return (array) call_user_func($this->formatCallback, $model);
            }

            return collect($model->toArray())
                ->except(['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])
                ->all();
        })->values();

        $headers = $rows->isEmpty() ? [] : array_keys($rows->first());

        $name = $rows->isEmpty()
            ? 'export'
            : Str::kebab(class_basename($models->first()));

        $filename = $name.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $values = array_map(function ($value) {
                    if (is_array($value) || is_object($value)) {
                        return json_encode($value);
                    }

                    return $value;
                }, array_values($row));

                fputcsv($handle, $values);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
