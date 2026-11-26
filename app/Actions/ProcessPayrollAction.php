<?php

namespace App\Actions;

use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPayrollAction extends BaseAction
{
    use AsAction;

    public function handle()
    {
        // Implement payroll processing logic here
    }

    // We can add this as controller route
    // Route::post('/process', ProcessPayrollAction::class)->middleware('auth');
    public function asController(Request $request)
    {
        $this->handle();

        return redirect()->back();
    }
}
