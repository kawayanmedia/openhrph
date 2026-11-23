<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\Workbook\SampleWorkbook;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportTemplateController extends Controller
{
    public function __invoke(Request $request, SampleWorkbook $samples): BinaryFileResponse
    {
        abort_unless($request->user()?->can(config('admin.gate')), 403);

        $withExamples = ! $request->boolean('blank');
        $spreadsheet = $samples->build($withExamples);

        $path = tempnam(sys_get_temp_dir(), 'openhr-import-');

        if ($path === false) {
            abort(500, 'The import template could not be generated.');
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($path, 'openhrph-import-'.($withExamples ? 'sample' : 'template').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
