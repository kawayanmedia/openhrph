<?php

namespace App\Http\Controllers;

use App\Gateways\PayslipGateway;
use App\Models\PayrollDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $detail = PayrollDetail::query()->findOrFail($id);
        $user = Auth::user();

        abort_unless($user !== null, 403);

        $isOwnPayslip = (int) $detail->employee?->user_id === (int) $user->getKey();
        $isAdmin = $user->can(config('admin.gate'));

        abort_unless($isOwnPayslip || $isAdmin, 403);
        abort_unless($isOwnPayslip || (int) $detail->company_id === (int) $user->company_id, 404);

        $payslipGateway = new PayslipGateway;
        $payslipGateway->get($id);

        // Process the pdf
        return $payslipGateway->generatePdf();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PayrollDetail $payrollDetail)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PayrollDetail $payrollDetail)
    {
        //
    }
}
