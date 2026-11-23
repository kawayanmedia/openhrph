<?php

namespace App\Livewire\Admin\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sends the user to the workbook importer, which handles every sheet in one
 * validated upload.
 */
class ImportData extends Action
{
    public string $name = 'Import Data';

    public string $confirmText = 'Open the data import page to add or update offices, employees and payroll records from one Excel workbook?';

    public string $confirmButtonText = 'Open data import';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        return redirect()->route('admin.import');
    }

    public function fields(Request $request): array
    {
        return [];
    }
}
