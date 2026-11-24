<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Employees\EmployeeMasterFileQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeMasterFileController extends Controller
{
    public function csv(Request $request, EmployeeMasterFileQuery $masterFile): StreamedResponse
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'office_id' => $request->query('office_id'),
            'department_id' => $request->query('department_id'),
            'employment_type_id' => $request->query('employment_type_id'),
            'payroll_group_id' => $request->query('payroll_group_id'),
            'employment_status' => $request->query('employment_status'),
            'active' => (string) $request->query('active', ''),
            'sort' => (string) $request->query('sort', 'last_name'),
            'direction' => (string) $request->query('direction', 'asc'),
        ];

        $csv = $masterFile->csv($filters);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'employee-master-file-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
