<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Services\Payroll\RemittanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RemittanceDownloadController extends Controller
{
    public function download(Request $request, Payroll $payroll, string $format, RemittanceReport $report): StreamedResponse
    {
        $this->authorizeAdmin();

        abort_unless(array_key_exists($format, $report->formats()), 404);

        $csv = $report->csv($payroll, $format, [
            'agency' => $request->query('agency'),
            'control_totals' => true,
        ]);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'remittance-'.$format.'-'.$payroll->getKey().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadAll(Payroll $payroll, RemittanceReport $report): StreamedResponse
    {
        $this->authorizeAdmin();

        abort_unless(class_exists(ZipArchive::class), 422, 'ZIP support is not available on this server.');

        $path = tempnam(sys_get_temp_dir(), 'remittances-');

        abort_if($path === false, 500, 'Unable to create the remittance bundle.');

        $zipPath = $path.'.zip';
        $zip = new ZipArchive;

        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Unable to create the remittance bundle.');

        foreach ($report->sectorFormats($payroll) as $format) {
            $zip->addFromString('remittance-'.$format.'.csv', $report->csv($payroll, $format));
        }

        $zip->close();
        @unlink($path);

        return response()->streamDownload(function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, 'remittances-'.$payroll->getKey().'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
    }
}
