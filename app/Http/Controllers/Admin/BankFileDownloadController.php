<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankFileTemplate;
use App\Models\CompanyBank;
use App\Models\Payroll;
use App\Services\Payroll\BankFileGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankFileDownloadController extends Controller
{
    public function download(Request $request, Payroll $payroll, BankFileGenerator $generator): StreamedResponse
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'debit_account_id' => ['nullable', 'integer'],
            'payment_date' => ['nullable', 'date'],
        ]);

        $template = BankFileTemplate::query()->findOrFail($data['template_id']);
        $account = isset($data['debit_account_id'])
            ? CompanyBank::query()->find($data['debit_account_id'])
            : null;

        $result = $generator->build($payroll, $template, $account, $data['payment_date'] ?? null);

        abort_if($result['included'] === 0, 422, 'No employees with a bank account are payable in this payroll.');

        $csv = $generator->csv($template, $result);
        $name = 'bank-file-'.$payroll->getKey().'-'.Str::slug($template->name).'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
