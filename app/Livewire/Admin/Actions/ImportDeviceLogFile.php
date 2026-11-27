<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\File;
use App\Models\Device;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use KawayanMedia\Attendance\Import\DeviceLogImporter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Imports a device export for any vendor: ZKTeco DAT, generic CSV, or the
 * Hikvision and Suprema report exports.
 */
class ImportDeviceLogFile extends Action
{
    public string $name = 'Import Device Log';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $device = $models->first();

        if (! $device instanceof Device) {
            return self::message('Select the device that produced the file first.');
        }

        $file = $fields['log_file'] ?? null;

        if (! $file instanceof TemporaryUploadedFile) {
            return self::message('Choose a log file to import.');
        }

        $path = $file->store('device-logs', 'local');

        if (! is_string($path) || $path === '') {
            return self::message('The file could not be stored. Please try again.');
        }

        try {
            $contents = (string) Storage::disk('local')->get($path);
            $descriptor = app(EloquentDeviceRepository::class)->descriptorFor($device);
            $summary = app(DeviceLogImporter::class)->import($descriptor, $contents, basename($path));
        } catch (Throwable $exception) {
            report($exception);

            return self::message('Import failed: '.$exception->getMessage());
        } finally {
            Storage::disk('local')->delete($path);
        }

        return self::message(sprintf(
            'Imported with the %s parser: %d row(s) read, %d accepted, %d duplicate(s), %d unmatched, %d invalid, %d rejected.',
            $summary['parser'],
            $summary['records'],
            $summary['accepted'],
            $summary['duplicates'],
            $summary['unmatched'],
            $summary['invalid'],
            $summary['rejected'],
        ));
    }

    /**
     * @return array<int, File>
     */
    public function fields(Request $request): array
    {
        return [
            File::make('Log File', 'log_file')
                ->rules('required', 'file', 'max:20480', 'extensions:csv,txt,dat,log')
                ->help('ZKTeco .dat exports, CSV reports from Hikvision or Suprema, or any CSV with employee and timestamp columns.'),
        ];
    }
}
